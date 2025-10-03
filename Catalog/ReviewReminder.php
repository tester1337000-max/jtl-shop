<?php

declare(strict_types=1);

namespace JTL\Catalog;

use Exception;
use JTL\Catalog\Product\Artikel;
use JTL\Checkout\Bestellung;
use JTL\Customer\Customer;
use JTL\Customer\CustomerGroup;
use JTL\Exceptions\EmptyResultSetException;
use JTL\Exceptions\InvalidSettingException;
use JTL\Session\Frontend;
use JTL\Settings\Option\Review;
use JTL\Settings\Section;
use JTL\Settings\Settings;
use JTL\Shop;
use Monolog\Logger;
use stdClass;

/**
 * Class ReviewReminder
 * @package JTL
 */
class ReviewReminder
{
    private Settings $settings;

    /**
     * @var int[]
     */
    private array $customerGroups = [];

    private int $shippingDays = 0;

    private int $maxDays = 0;

    private string $sqlPartCustomerGroups = '';

    private string $sqlPartBundle1 = '';

    private string $sqlPartBundle2 = '';

    /**
     * @var stdClass[]
     */
    private array $orders = [];

    public function __construct()
    {
        $this->settings = Settings::fromSection(Section::REVIEW);
        $cgroups        = $this->settings->raw(Review::CUSTOMER_GROUPS);
        if (\is_array($cgroups)) {
            $this->customerGroups = \array_map('\intval', $cgroups);
        }
    }

    /**
     * @return array<int, object{tkunde: Customer, tbestellung: Bestellung}&stdClass>
     */
    public function getRecipients(): array
    {
        if ($this->settings->string(Review::REMINDER_USE) === 'N') {
            return [];
        }
        $this->checkNlBundle();

        try {
            $this->calculateSentTime();
            $this->getCustomerGroups();
            $this->fireSQL();
        } catch (EmptyResultSetException $e) {
            Shop::Container()->getLogService()->notice($e->getMessage());

            return [];
        } catch (Exception $e) {
            Shop::Container()->getLogService()->error($e->getMessage());

            return [];
        }
        $reciepients    = [];
        $defaultOptions = Artikel::getDefaultOptions();
        $db             = Shop::Container()->getDB();
        $cache          = Shop::Container()->getCache();
        $currency       = Frontend::getCurrency();
        $logger         = Shop::Container()->getLogService();
        foreach ($this->orders as $orderData) {
            $openReviews = [];
            $order       = new Bestellung((int)$orderData->kBestellung, false, $db);
            $order->fuelleBestellung(false);
            $customer      = new Customer($order->kKunde ?? 0, null, $db);
            $obj           = (object)['tkunde' => $customer, 'tbestellung' => $order];
            $customerGroup = new CustomerGroup($customer->getGroupID(), $db);
            foreach ($order->Positionen as $item) {
                if ($item->kArtikel <= 0) {
                    continue;
                }
                $productVisible = (new Artikel($db, $customerGroup, $currency, $cache))->fuelleArtikel(
                    (int)$item->kArtikel,
                    $defaultOptions,
                    (int)$customer->kKundengruppe
                );
                if ($productVisible !== null && $productVisible->kArtikel > 0) {
                    $res = $db->getSingleObject(
                        'SELECT kBewertung
                            FROM tbewertung
                            WHERE kArtikel = :pid
                                AND kKunde = :cid',
                        ['pid' => (int)$item->kArtikel, 'cid' => (int)$order->kKunde]
                    );
                    if ($res === null) {
                        $openReviews[] = $item;
                    }
                }
            }
            // there are no OPEN reviews for this order
            if (\count($openReviews) === 0) {
                continue;
            }
            $order->Positionen = $openReviews;
            // set the date of "review send" for the corresponding order
            $db->queryPrepared(
                'UPDATE tbestellung
                    SET dBewertungErinnerung = NOW()
                    WHERE kBestellung = :oid',
                ['oid' => (int)$orderData->kBestellung]
            );
            if ($logger instanceof Logger && $logger->isHandling(\JTLLOG_LEVEL_DEBUG)) {
                $logger->withName('Bewertungserinnerung')->debug(
                    'Kunde und Bestellung aus baueBewertungsErinnerung (Mail versendet): <pre>' .
                    \print_r($obj, true) .
                    '</pre>',
                    [$orderData->kBestellung]
                );
            }
            $reciepients[] = $obj;
        }

        return $reciepients;
    }

    /**
     * building finally the SQL-query
     * @throws EmptyResultSetException
     */
    private function fireSQL(): void
    {
        $sqlString    = 'SELECT kBestellung
            FROM tbestellung
                JOIN tkunde ON tkunde.kKunde = tbestellung.kKunde AND tkunde.nRegistriert = 1
                ' . $this->sqlPartBundle1 . '
            WHERE dVersandDatum IS NOT NULL
                AND DATE_ADD(dVersandDatum, INTERVAL ' . $this->shippingDays . ' DAY) <= NOW()
                AND DATE_ADD(dVersandDatum, INTERVAL ' . $this->maxDays . ' DAY) > NOW()
                AND cStatus = 4
                AND (' . $this->sqlPartCustomerGroups . ')
                AND dBewertungErinnerung IS NULL
                ' . $this->sqlPartBundle2;
        $this->orders = Shop::Container()->getDB()->getObjects($sqlString);
        if (\count($this->orders) === 0) {
            throw new EmptyResultSetException('Keine Bestellungen für Bewertungserinnerungen gefunden.');
        }
    }

    /**
     * check, if "bound to newsletter" consent is configured
     */
    private function checkNlBundle(): void // naming ?! --TODO--
    {
        if ($this->settings->string(Review::REMINDER_USE) === 'B') {
            $this->sqlPartBundle1 = 'LEFT JOIN tnewsletterempfaenger ON tkunde.kKunde = tnewsletterempfaenger.kKunde';
            $this->sqlPartBundle2 = 'AND tnewsletterempfaenger.nAktiv = 1';
        }
    }

    /**
     * define for which customer groups we will to send
     */
    private function getCustomerGroups(): void
    {
        $this->sqlPartCustomerGroups = '';
        if (\count($this->customerGroups) > 0) {
            foreach ($this->customerGroups as $i => $groupID) {
                if (!\is_numeric($groupID)) {
                    continue;
                }
                if ($i > 0) {
                    $this->sqlPartCustomerGroups .= ' OR tkunde.kKundengruppe = ' . (int)$groupID;
                } else {
                    $this->sqlPartCustomerGroups .= ' tkunde.kKundengruppe = ' . (int)$groupID;
                }
            }
        } else {
            // Hole standard Kundengruppe
            $defaultGroup = CustomerGroup::getDefault();
            if ($defaultGroup !== null && $defaultGroup->kKundengruppe > 0) {
                $this->sqlPartCustomerGroups = ' tkunde.kKundengruppe = ' . (int)$defaultGroup->kKundengruppe;
            }
        }
        if (empty($this->sqlPartCustomerGroups)) {
            throw new EmptyResultSetException('Keine Kundengruppe für Bewertungserinnerungen gefunden');
        }
    }

    /**
     * set the time difference from delivery date to send the review reminder
     */
    private function calculateSentTime(): void
    {
        $this->shippingDays = $this->settings->int(Review::SHIPPING_DAYS);
        if ($this->shippingDays <= 0) {
            throw new InvalidSettingException('Setting bewertungserinnerung_versandtage has value 0');
        }
        $this->maxDays = $this->shippingDays * 2;
        if ($this->shippingDays === 1) {
            $this->maxDays = 4;
        }
    }
}
