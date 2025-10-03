<?php

declare(strict_types=1);

namespace JTL\Helpers;

use JTL\Checkout\Zahlungsart;
use JTL\Plugin\Payment\FallbackMethod;
use JTL\Plugin\Payment\LegacyMethod;
use JTL\Session\Frontend;
use JTL\Shop;
use stdClass;

/**
 * Class PaymentMethod
 * @package JTL\Helpers
 */
class PaymentMethod
{
    /**
     * @param Zahlungsart|(object{kZahlungsart: int, cName: string, cModulId: string, cKundengruppen: string,
     *     cZusatzschrittTemplate: string, cPluginTemplate: string, cBild: string, nSort: int, nMailSenden: 1|0,
     *     nActive: 1|0, cAnbieter: string, cTSCode: string, nWaehrendBestellung: 1|0, nCURL: 1|0, nSOAP: 1|0,
     *     nSOCKETS: 1|0, nNutzbar: 1|0}&stdClass) $paymentMethod
     */
    public static function shippingMethodWithValidPaymentMethod(
        Zahlungsart|stdClass $paymentMethod,
        int|null $customerOrderCount = null,
        float|null $cartGrossValue = null
    ): bool {
        if (!isset($paymentMethod->cModulId)) {
            return false;
        }
        require_once \PFAD_ROOT . \PFAD_INCLUDES . 'bestellvorgang_inc.php';
        $customer   = Frontend::getCustomer();
        $customerID = $customer->getID();
        /** @var array<string, int|float> $conf */
        $conf                         = Shop::getSettingSection(\CONF_ZAHLUNGSARTEN);
        $paymentMethod->einstellungen = $conf;
        return match ($paymentMethod->cModulId) {
            'za_ueberweisung_jtl' => self::checkOrderValues(
                (int)($conf['zahlungsart_ueberweisung_min_bestellungen'] ?? 0),
                (float)($conf['zahlungsart_ueberweisung_min'] ?? 0),
                (float)($conf['zahlungsart_ueberweisung_max'] ?? 0),
                $customerID,
                $customerOrderCount,
                $cartGrossValue,
            ),
            'za_nachnahme_jtl'    => self::checkOrderValues(
                (int)($conf['zahlungsart_nachnahme_min_bestellungen'] ?? 0),
                (float)($conf['zahlungsart_nachnahme_min'] ?? 0),
                (float)($conf['zahlungsart_nachnahme_max'] ?? 0),
                $customerID,
                $customerOrderCount,
                $cartGrossValue,
            ),
            'za_rechnung_jtl'     => self::checkOrderValues(
                (int)($conf['zahlungsart_rechnung_min_bestellungen'] ?? 0),
                (float)($conf['zahlungsart_rechnung_min'] ?? 0),
                (float)($conf['zahlungsart_rechnung_max'] ?? 0),
                $customerID,
                $customerOrderCount,
                $cartGrossValue,
            ),
            'za_lastschrift_jtl'  => self::checkOrderValues(
                (int)($conf['zahlungsart_lastschrift_min_bestellungen'] ?? 0),
                (float)($conf['zahlungsart_lastschrift_min'] ?? 0),
                (float)($conf['zahlungsart_lastschrift_max'] ?? 0),
                $customerID,
                $customerOrderCount,
                $cartGrossValue,
            ),
            'za_barzahlung_jtl'   => self::checkOrderValues(
                (int)($conf['zahlungsart_barzahlung_min_bestellungen'] ?? 0),
                (float)($conf['zahlungsart_barzahlung_min'] ?? 0),
                (float)($conf['zahlungsart_barzahlung_max'] ?? 0),
                $customerID,
                $customerOrderCount,
                $cartGrossValue,
            ),
            'za_null_jtl'         => (new FallbackMethod('za_null_jtl'))->isValid(
                $customer,
                Frontend::getCart()
            ),
            default               => self::checkOrderValuesPlugin(
                $paymentMethod,
                $customerID,
                $customerOrderCount,
                $cartGrossValue,
            )
        };
    }

    /**
     * @param Zahlungsart|(object{kZahlungsart: int, cName: string, cModulId: string, cKundengruppen: string,
     *      cZusatzschrittTemplate: string, cPluginTemplate: string, cBild: string, nSort: int, nMailSenden: 1|0,
     *      nActive: 1|0, cAnbieter: string, cTSCode: string, nWaehrendBestellung: 1|0, nCURL: 1|0, nSOAP: 1|0,
     *      nSOCKETS: 1|0, nNutzbar: 1|0}&stdClass) $paymentMethod
     */
    private static function checkOrderValuesPlugin(
        Zahlungsart|stdClass $paymentMethod,
        int $customerID,
        int|null $customerOrderCount,
        float|null $cartGrossValue
    ): bool {
        try {
            /** @var array<string, int|float> $conf */
            $conf = Shop::getSettingSection(\CONF_PLUGINZAHLUNGSARTEN);
        } catch (\InvalidArgumentException) {
            $conf = [];
        }
        if (
            self::checkOrderValues(
                (int)($conf[$paymentMethod->cModulId . '_min_bestellungen'] ?? 0),
                (float)($conf[$paymentMethod->cModulId . '_min'] ?? 0),
                (float)($conf[$paymentMethod->cModulId . '_max'] ?? 0),
                $customerID,
                $customerOrderCount,
                $cartGrossValue
            )
        ) {
            $payMethod = LegacyMethod::create($paymentMethod->cModulId ?? '');
            return $payMethod === null || $payMethod->isValidIntern(
                [
                    Frontend::getCustomer(),
                    Frontend::getCart()
                ]
            );
        }

        return true;
    }

    private static function checkOrderValues(
        float|int|string $minOrders,
        float|int|string $minOrderValue,
        float|int|string $maxOrderValue,
        int $customerID,
        int|null $customerOrderCount,
        float|null $cartGrossValue
    ): bool {
        return self::checkMinOrders((int)$minOrders, $customerID, $customerOrderCount)
            && self::checkMinOrderValue($minOrderValue, $cartGrossValue)
            && self::checkMaxOrderValue($maxOrderValue, $cartGrossValue);
    }

    public static function checkMinOrders(int $minOrders, int $customerID, int|null $customerOrderCount = null): bool
    {
        if ($minOrders <= 0) {
            return true;
        }
        if ($customerID <= 0 && $customerOrderCount === null) {
            Shop::Container()->getLogService()->debug(
                'pruefeZahlungsartMinBestellungen erhielt keinen kKunden'
            );

            return false;
        }
        $customerOrderCount ??= Shop::Container()->getDB()->getSingleInt(
            'SELECT COUNT(*) AS orderCount
                FROM tbestellung
                WHERE kKunde = :customerID
                    AND (cStatus = :statusPaid OR cStatus = :statusShipped)',
            'orderCount',
            [
                'customerID'    => $customerID,
                'statusPaid'    => \BESTELLUNG_STATUS_BEZAHLT,
                'statusShipped' => \BESTELLUNG_STATUS_VERSANDT
            ]
        );
        if ($customerOrderCount >= $minOrders) {
            return true;
        }

        Shop::Container()->getLogService()->debug(
            'pruefeZahlungsartMinBestellungen Bestellanzahl zu niedrig: Anzahl {cnt} < {min}',
            ['cnt' => $customerOrderCount, 'min' => $minOrders]
        );

        return false;
    }

    public static function checkMinOrderValue(float|int|string $minOrderValue, float|null $cartGrossValue = null): bool
    {
        if ($minOrderValue <= 0) {
            return true;
        }
        $cartGrossValue ??= Frontend::getCart()->gibGesamtsummeWarenOhne([\C_WARENKORBPOS_TYP_VERSANDPOS], true);
        if ($cartGrossValue >= $minOrderValue) {
            return true;
        }

        Shop::Container()->getLogService()->debug(
            'checkMinOrderValue Bestellwert zu niedrig: Wert {crnt} < {min}',
            ['crnt' => $cartGrossValue, 'min' => $minOrderValue]
        );

        return false;
    }

    public static function checkMaxOrderValue(float|int|string $maxOrderValue, float|null $cartGrossValue = null): bool
    {
        if ($maxOrderValue <= 0) {
            return true;
        }
        $cartGrossValue ??= Frontend::getCart()->gibGesamtsummeWarenOhne([\C_WARENKORBPOS_TYP_VERSANDPOS], true);
        if ($cartGrossValue < $maxOrderValue) {
            return true;
        }

        Shop::Container()->getLogService()->debug(
            'pruefeZahlungsartMaxBestellwert Bestellwert zu hoch: Wert {crnt} > {max}',
            ['crnt' => $cartGrossValue, 'max' => $maxOrderValue]
        );

        return false;
    }

    /**
     * @former pruefeZahlungsartNutzbarkeit()
     */
    public static function checkPaymentMethodAvailability(): void
    {
        foreach (
            Shop::Container()->getDB()->selectAll(
                'tzahlungsart',
                'nActive',
                1,
                'kZahlungsart, cModulId, nSOAP, nCURL, nSOCKETS, nNutzbar'
            ) as $paymentMethod
        ) {
            self::activatePaymentMethod($paymentMethod);
        }
    }

    /**
     * Bei SOAP oder CURL => versuche die Zahlungsart auf nNutzbar = 1 zu stellen, falls nicht schon geschehen.
     * Die Fallback-Zahlart 'za_null_jtl' wird immer auf nNutzbar = 0 (zurück-)gesetzt, falls nicht schon geschehen.
     * @former aktiviereZahlungsart()
     */
    public static function activatePaymentMethod(Zahlungsart|stdClass $paymentMethod): bool
    {
        if ((int)$paymentMethod->kZahlungsart === 0) {
            return false;
        }
        $soap     = (int)$paymentMethod->nSOAP;
        $curl     = (int)$paymentMethod->nCURL;
        $sockets  = (int)$paymentMethod->nSOCKETS;
        $isUsable = 0;
        if (($paymentMethod->cModulId ?? '') !== 'za_null_jtl') {
            $isUsable = match (true) {
                $soap === 0 && $curl === 0 && $sockets === 0  => 1,
                $soap === 1 && PHPSettings::checkSOAP()       => 1,
                $curl === 1 && PHPSettings::checkCURL()       => 1,
                $sockets === 1 && PHPSettings::checkSockets() => 1,
                default                                       => 0
            };
        }
        if (!isset($paymentMethod->nNutzbar) || $paymentMethod->nNutzbar !== $isUsable) {
            Shop::Container()->getDB()->update(
                'tzahlungsart',
                'kZahlungsart',
                (int)$paymentMethod->kZahlungsart,
                (object)['nNutzbar' => $isUsable]
            );
        }

        return (bool)$isUsable;
    }
}
