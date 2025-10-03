<?php

declare(strict_types=1);

namespace JTL\dbeS\Sync;

use DateTime;
use Exception;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use JTL\Cache\JTLCacheInterface;
use JTL\Campaign;
use JTL\Catalog\Product\Artikel;
use JTL\Customer\CustomerGroup;
use JTL\DB\DbInterface;
use JTL\dbeS\Mapper;
use JTL\dbeS\Starter;
use JTL\Helpers\GeneralObject;
use JTL\Helpers\Text;
use JTL\Language\LanguageHelper;
use JTL\Mail\Mail\Mail;
use JTL\Mail\Template\TemplateFactory;
use JTL\Optin\Optin;
use JTL\Optin\OptinAvailAgain;
use JTL\Redirect\Helpers\Normalizer;
use JTL\Redirect\Repositories\RedirectRefererRepository;
use JTL\Redirect\Repositories\RedirectRepository;
use JTL\Redirect\Services\RedirectService;
use JTL\Redirect\Type;
use JTL\Session\Frontend;
use JTL\Settings\Option\Globals;
use JTL\Settings\Settings;
use JTL\Shop;
use Psr\Log\LoggerInterface;
use stdClass;

/**
 * Class AbstractSync
 * @package JTL\dbeS\Sync
 */
abstract class AbstractSync
{
    protected Mapper $mapper;

    public function __construct(
        protected DbInterface $db,
        protected JTLCacheInterface $cache,
        protected LoggerInterface $logger
    ) {
        $this->mapper = new Mapper();
    }

    /**
     * @param Starter $starter
     * @return array<mixed>|void
     */
    abstract public function handle(Starter $starter);

    /**
     * @param array<mixed> $xml
     * @param string       $table
     * @param string       $toMap
     * @param string       $pk1
     * @param string|null  $pk2
     */
    protected function upsertXML(array $xml, string $table, string $toMap, string $pk1, ?string $pk2 = null): void
    {
        $idx = $table . ' attr';
        if (GeneralObject::isCountable($table, $xml) || GeneralObject::isCountable($idx, $xml)) {
            $this->upsert($table, $this->mapper->mapArray($xml, $table, $toMap), $pk1, $pk2);
        }
    }

    /**
     * @param array<mixed> $xml
     * @param string       $table
     * @param string       $toMap
     * @param string[]     $pks
     * @return array<string, array{}>
     */
    protected function insertOnExistsUpdateXMLinDB(array $xml, string $table, string $toMap, array $pks): array
    {
        $idx = $table . ' attr';
        if (GeneralObject::isCountable($table, $xml) || GeneralObject::isCountable($idx, $xml)) {
            return $this->insertOnExistUpdate($table, $this->mapper->mapArray($xml, $table, $toMap), $pks);
        }

        return \array_fill_keys($pks, []);
    }

    /**
     * @param string          $tablename
     * @param array<stdClass> $objects
     * @param string          $pk1
     * @param string|null     $pk2
     */
    protected function upsert(string $tablename, array $objects, string $pk1, ?string $pk2 = null): void
    {
        foreach ($objects as $object) {
            if (isset($object->$pk1) && !$pk2 && $pk1 && $object->$pk1) {
                $this->db->delete($tablename, $pk1, $object->$pk1);
            }
            if (isset($object->$pk2) && $pk1 && $pk2 && $object->$pk1 && $object->$pk2) {
                $this->db->delete($tablename, [$pk1, $pk2], [$object->$pk1, $object->$pk2]);
            }
            $key = $this->db->insert($tablename, $object);
            if (!$key) {
                $this->logger->error('Failed upsert@' . $tablename . ' with data: ' . \print_r($object, true));
            }
        }
    }

    /**
     * @param string     $tableName
     * @param stdClass[] $objects
     * @param string[]   $pks
     * @return array<string, string[]>
     */
    protected function insertOnExistUpdate(string $tableName, array $objects, array $pks): array
    {
        $result = \array_fill_keys($pks, []);
        foreach ($objects as $object) {
            foreach ($pks as $pk) {
                if (!isset($object->$pk)) {
                    $this->logger->error(
                        'PK not set on insertOnExistUpdate@' . $tableName . ' with data: ' . \print_r($object, true)
                    );

                    continue 2;
                }
                $result[$pk][] = $object->$pk;
            }

            if ($this->db->upsert($tableName, $object, $pks)) {
                $this->logger->error(
                    'Failed insertOnExistUpdate@' . $tableName . ' with data: ' . \print_r($object, true)
                );
            }
        }

        return $result;
    }

    /**
     * @param string             $tableName
     * @param array<string, int> $pks
     * @param string             $excludeKey
     * @param string[]|int[]     $excludeValues
     */
    protected function deleteByKey(
        string $tableName,
        array $pks,
        string $excludeKey = '',
        array $excludeValues = []
    ): void {
        $whereKeys = [];
        $params    = [];
        foreach ($pks as $name => $value) {
            $whereKeys[]   = $name . ' = :' . $name;
            $params[$name] = $value;
        }
        if (empty($excludeKey)) {
            $excludeValues = [];
        }
        $stmt = 'DELETE FROM ' . $tableName . '
                WHERE ' . \implode(' AND ', $whereKeys) . (\count($excludeValues) > 0 ? '
                    AND ' . $excludeKey . ' NOT IN (' . \implode(', ', $excludeValues) . ')' : '');
        if (!$this->db->queryPrepared($stmt, $params)) {
            $this->logger->error(
                'DBDeleteByKey fehlgeschlagen! Tabelle: ' . $tableName . ', PK: ' . \print_r($pks, true)
            );
        }
    }

    protected function sendAvailabilityMails(stdClass $data, int $minStock): void
    {
        if ($data->kArtikel <= 0) {
            return;
        }
        $productID      = (int)$data->kArtikel;
        $sendMails      = true;
        $stockRatio     = $minStock / 100;
        $stockRelevance = ($data->cLagerKleinerNull ?? '') !== 'Y' && ($data->cLagerBeachten ?? 'Y') === 'Y';
        $subscriptions  = $this->db->selectAll(
            'tverfuegbarkeitsbenachrichtigung',
            ['nStatus', 'kArtikel'],
            [0, $productID]
        );
        \executeHook(\HOOK_SYNC_SEND_AVAILABILITYMAILS, [
            'sendMails'     => &$sendMails,
            'product'       => $data,
            'subscriptions' => &$subscriptions,
        ]);
        $subCount = \count($subscriptions);
        if ($subCount === 0) {
            return;
        }
        $noStock = ($data->fLagerbestand <= 0 || ($data->fLagerbestand / $subCount) < $stockRatio);
        if ($sendMails === false || ($stockRelevance && $noStock)) {
            return;
        }
        require_once \PFAD_ROOT . \PFAD_INCLUDES . 'sprachfunktionen.php';

        $options                             = Artikel::getDefaultOptions();
        $options->nKeineSichtbarkeitBeachten = 1;
        $product                             = new Artikel($this->db, null, null, $this->cache);
        $product->fuelleArtikel($productID, $options);
        if ($product->kArtikel === null || $product->cURL === null) {
            return;
        }
        $currency = Frontend::getCurrency();
        $factory  = new TemplateFactory($this->db);
        $mailer   = Shop::Container()->getMailer();
        $campaign = new Campaign(\KAMPAGNE_INTERN_VERFUEGBARKEIT, $this->db);
        $upd      = (object)[
            'nStatus'           => 1,
            'dBenachrichtigtAm' => 'NOW()',
            'cAbgeholt'         => 'N'
        ];
        /**
         * @var int                       $languageID
         * @var Collection<int, stdClass> $byCustomerGroupID
         */
        foreach (\collect($subscriptions)->groupBy('kSprache') as $languageID => $byCustomerGroupID) {
            // if original language was deleted between ActivationOptIn and now, try to send it in english,
            // if there is no english, use the shop-default.
            $language = LanguageHelper::getAllLanguages(1)[$languageID]
                ?? LanguageHelper::getAllLanguages(2)['eng']
                ?? LanguageHelper::getDefaultLanguage();
            /**
             * @var int                       $customerGroupID
             * @var Collection<int, stdClass> $items
             */
            foreach ($byCustomerGroupID->groupBy('customerGroupID') as $customerGroupID => $items) {
                $customerGroup = new CustomerGroup($customerGroupID, $this->db);
                $product       = new Artikel($this->db, $customerGroup, $currency, $this->cache);
                try {
                    $product->fuelleArtikel($productID, null, $customerGroupID, $languageID);
                } catch (InvalidArgumentException) {
                    $product->fuelleArtikel($productID, null, 0, $languageID);
                }
                if ($product->cName === null) {
                    continue;
                }
                if ($product->cURL !== null && $campaign->kKampagne > 0) {
                    $sep           = !\str_contains($product->cURL, '.php') ? '?' : '&';
                    $product->cURL .= $sep . $campaign->cParameter . '=' . $campaign->cWert;
                }
                foreach ($items as $msg) {
                    /** @var OptinAvailAgain $availAgainOptin */
                    $availAgainOptin = (new Optin(OptinAvailAgain::class))->getOptinInstance();
                    $availAgainOptin->setProduct($product)
                        ->setEmail($msg->cMail);
                    if (!$availAgainOptin->isActive()) {
                        continue;
                    }
                    $availAgainOptin->finishOptin();
                    $tplData                                   = new stdClass();
                    $tplData->tverfuegbarkeitsbenachrichtigung = $msg;
                    $tplData->tartikel                         = $product;
                    $tplData->tartikel->cName                  = Text::htmlentitydecode($product->cName);
                    $tplMail                                   = new stdClass();
                    $tplMail->toEmail                          = $msg->cMail;
                    $tplMail->toName                           = ($msg->cVorname || $msg->cNachname)
                        ? ($msg->cVorname . ' ' . $msg->cNachname)
                        : $msg->cMail;
                    $tplData->mail                             = $tplMail;

                    $mail = new Mail();
                    $mail = $mail->createFromTemplateID(\MAILTEMPLATE_PRODUKT_WIEDER_VERFUEGBAR, $tplData, $factory);
                    $mail->setLanguage($language);
                    $mail->setCustomerGroupID($customerGroupID);
                    $mail->setToMail($tplMail->toEmail);
                    $mail->setToName($tplMail->toName);
                    $mailer->send($mail);
                    $this->db->update(
                        'tverfuegbarkeitsbenachrichtigung',
                        'kVerfuegbarkeitsbenachrichtigung',
                        $msg->kVerfuegbarkeitsbenachrichtigung,
                        $upd
                    );
                }
            }
        }
    }

    /**
     * @param array<mixed> $xml
     * @throws Exception
     */
    protected function handlePriceHistory(int $productID, array $xml): void
    {
        // Delete price history from not existing customer groups
        $this->db->queryPrepared(
            'DELETE tpreisverlauf
                FROM tpreisverlauf
                    LEFT JOIN tkundengruppe ON tkundengruppe.kKundengruppe = tpreisverlauf.kKundengruppe
                WHERE tpreisverlauf.kArtikel = :productID
                    AND tkundengruppe.kKundengruppe IS NULL',
            ['productID' => $productID]
        );
        // Insert new base price for each customer group - update existing history for today
        $this->db->queryPrepared(
            'INSERT INTO tpreisverlauf (kArtikel, kKundengruppe, fVKNetto, dDate)
                SELECT :productID, kKundengruppe, :nettoPrice, CURDATE()
                FROM tkundengruppe
                ON DUPLICATE KEY UPDATE
                    fVKNetto = :nettoPrice',
            [
                'productID'  => $productID,
                'nettoPrice' => (float)$xml['fStandardpreisNetto'],
            ]
        );
        // Handle price details from xml...
        $this->handlePriceDetails($productID, $xml);
        // Handle special prices from xml...
        $this->handleSpecialPrices($productID, $xml);
        // Delete last price history if price is same as next to last
        $this->db->queryPrepared(
            'DELETE FROM tpreisverlauf
                WHERE tpreisverlauf.kArtikel = :productID
                    AND (tpreisverlauf.kKundengruppe, tpreisverlauf.dDate) IN (SELECT * FROM (
                        SELECT tpv1.kKundengruppe, MAX(tpv1.dDate)
                        FROM tpreisverlauf tpv1
                        LEFT JOIN tpreisverlauf tpv2 ON tpv2.dDate > tpv1.dDate
                            AND tpv2.kArtikel = tpv1.kArtikel
                            AND tpv2.kKundengruppe = tpv1.kKundengruppe
                            AND tpv2.dDate < (
                                SELECT MAX(tpv3.dDate)
                                FROM tpreisverlauf tpv3
                                WHERE tpv3.kArtikel = tpv1.kArtikel
                                    AND tpv3.kKundengruppe = tpv1.kKundengruppe
                            )
                        WHERE tpv1.kArtikel = :productID
                            AND tpv2.kPreisverlauf IS NULL
                        GROUP BY tpv1.kKundengruppe
                        HAVING COUNT(DISTINCT tpv1.fVKNetto) = 1
                            AND COUNT(tpv1.kPreisverlauf) > 1
                    ) i)',
            ['productID' => $productID]
        );
    }

    /**
     * @param array<mixed> $xml
     */
    private function handlePriceDetails(int $productID, array $xml): void
    {
        $prices = isset($xml['tpreis']) ? $this->mapper->mapArray($xml, 'tpreis', 'mPreis') : [];
        foreach ($prices as $i => $price) {
            $details = empty($xml['tpreis'][$i])
                ? $this->mapper->mapArray($xml['tpreis'], 'tpreisdetail', 'mPreisDetail')
                : $this->mapper->mapArray($xml['tpreis'][$i], 'tpreisdetail', 'mPreisDetail');
            if (\count($details) > 0 && (int)$details[0]->nAnzahlAb === 0) {
                $this->db->queryPrepared(
                    'UPDATE tpreisverlauf SET
                        fVKNetto = :nettoPrice
                        WHERE kArtikel = :productID
                            AND kKundengruppe = :customerGroupID
                            AND dDate = CURDATE()',
                    [
                        'nettoPrice'      => $details[0]->fNettoPreis,
                        'productID'       => $productID,
                        'customerGroupID' => $price->kKundenGruppe,
                    ]
                );
            }
        }
    }

    /**
     * @param array<mixed> $xml
     * @throws Exception
     */
    private function handleSpecialPrices(int $productID, array $xml): void
    {
        $prices = isset($xml['tartikelsonderpreis'])
            ? $this->mapper->mapArray($xml, 'tartikelsonderpreis', 'mArtikelSonderpreis')
            : [];
        foreach ($prices as $i => $price) {
            if ($price->cAktiv !== 'Y') {
                continue;
            }
            try {
                $startDate = new DateTime($price->dStart);
            } catch (Exception) {
                $startDate = (new DateTime())->setTime(0, 0);
            }
            try {
                $endDate = new DateTime($price->dEnde);
            } catch (Exception) {
                $endDate = (new DateTime())->setTime(0, 0);
            }
            $today = (new DateTime())->setTime(0, 0);
            if (
                $startDate <= $today
                && $endDate >= $today
                && ((int)$price->nIstAnzahl === 0 || (int)$price->nAnzahl < (int)$xml['fLagerbestand'])
            ) {
                $specialPrices = empty($xml['tartikelsonderpreis'][$i])
                    ? $this->mapper->mapArray($xml['tartikelsonderpreis'], 'tsonderpreise', 'mSonderpreise')
                    : $this->mapper->mapArray($xml['tartikelsonderpreis'][$i], 'tsonderpreise', 'mSonderpreise');

                foreach ($specialPrices as $specialPrice) {
                    $this->db->queryPrepared(
                        'UPDATE tpreisverlauf
                            SET fVKNetto = :nettoPrice
                            WHERE kArtikel = :productID
                                AND kKundengruppe = :customerGroupID
                                AND dDate = CURDATE()',
                        [
                            'nettoPrice'      => $specialPrice->fNettoPreis,
                            'productID'       => $productID,
                            'customerGroupID' => $specialPrice->kKundengruppe,
                        ]
                    );
                }
            }
        }
    }

    protected function handlePriceFormat(int $productID, int $customerGroupID, int $customerID = 0): void
    {
        if ($customerID > 0) {
            $this->flushCustomerPriceCache($customerID);
        }
        $this->db->queryPrepared(
            'INSERT INTO tpreis (kArtikel, kKundengruppe, kKunde)
                VALUES (:productID, :customerGroup, :customerID)
                ON DUPLICATE KEY UPDATE
                    kKunde     = :customerID,
                    noDiscount = IF(:customerID > 0, 0, noDiscount)',
            [
                'productID'     => $productID,
                'customerGroup' => $customerGroupID,
                'customerID'    => $customerID,
            ]
        );
    }

    /**
     * Handle new PriceFormat (Wawi >= v.1.00):
     *
     * Sample XML:
     *  <tpreis kPreis="8" kArtikel="15678" kKundenGruppe="1" kKunde="0">
     *      <tpreisdetail kPreis="8">
     *          <nAnzahlAb>100</nAnzahlAb>
     *          <fNettoPreis>0.756303</fNettoPreis>
     *      </tpreisdetail>
     *      <tpreisdetail kPreis="8">
     *          <nAnzahlAb>250</nAnzahlAb>
     *          <fNettoPreis>0.714286</fNettoPreis>
     *      </tpreisdetail>
     *      <tpreisdetail kPreis="8">
     *          <nAnzahlAb>500</nAnzahlAb>
     *          <fNettoPreis>0.672269</fNettoPreis>
     *      </tpreisdetail>
     *      <tpreisdetail kPreis="8">
     *          <nAnzahlAb>750</nAnzahlAb>
     *          <fNettoPreis>0.630252</fNettoPreis>
     *      </tpreisdetail>
     *      <tpreisdetail kPreis="8">
     *          <nAnzahlAb>1000</nAnzahlAb>
     *          <fNettoPreis>0.588235</fNettoPreis>
     *      </tpreisdetail>
     *      <tpreisdetail kPreis="8">
     *          <nAnzahlAb>2000</nAnzahlAb>
     *          <fNettoPreis>0.420168</fNettoPreis>
     *      </tpreisdetail>
     *      <tpreisdetail kPreis="8">
     *          <nAnzahlAb>0</nAnzahlAb>
     *          <fNettoPreis>0.798319</fNettoPreis>
     *      </tpreisdetail>
     *  </tpreis>
     *
     * @param array<mixed> $xml
     */
    protected function handleNewPriceFormat(int $productID, array $xml): void
    {
        $prices = isset($xml['tpreis']) ? $this->mapper->mapArray($xml, 'tpreis', 'mPreis') : [];
        // Delete prices and price details from not existing customer groups
        $this->db->queryPrepared(
            'DELETE tpreis, tpreisdetail
                FROM tpreis
                    INNER JOIN tpreisdetail ON tpreisdetail.kPreis = tpreis.kPreis
                    LEFT JOIN tkundengruppe ON tkundengruppe.kKundengruppe = tpreis.kKundengruppe
                WHERE tpreis.kArtikel = :productID
                    AND tkundengruppe.kKundengruppe IS NULL',
            [
                'productID' => $productID,
            ]
        );
        // Delete all prices who are not base prices
        $this->db->queryPrepared(
            'DELETE tpreisdetail
                FROM tpreis
                    INNER JOIN tpreisdetail ON tpreisdetail.kPreis = tpreis.kPreis
                WHERE tpreis.kArtikel = :productID
                    AND tpreisdetail.nAnzahlAb > 0',
            ['productID' => $productID]
        );
        // Insert price record for each customer group - update existing
        $this->db->queryPrepared(
            'INSERT INTO tpreis (kArtikel, kKundengruppe, kKunde, noDiscount)
                SELECT :productID, kKundengruppe, 0, COALESCE(:noDiscount, 0)
                FROM tkundengruppe
                ON DUPLICATE KEY UPDATE
                    tpreis.noDiscount = COALESCE(:noDiscount, noDiscount)',
            [
                'productID'  => $productID,
                'noDiscount' => isset($xml['nNichtRabattfaehig']) ? (int)$xml['nNichtRabattfaehig'] : null,
            ]
        );
        // Insert base price for each price record - update existing
        $this->db->queryPrepared(
            'INSERT INTO tpreisdetail (kPreis, nAnzahlAb, fVKNetto)
                SELECT tpreis.kPreis, 0, :basePrice
                FROM tpreis
                WHERE tpreis.kArtikel = :productID
                ON DUPLICATE KEY UPDATE
                    tpreisdetail.fVKNetto = :basePrice',
            [
                'basePrice' => $xml['fStandardpreisNetto'],
                'productID' => $productID,
            ]
        );
        // Handle price details from xml...
        foreach ($prices as $i => $price) {
            $price->kKunde        = (int)($price->kKunde ?? 0);
            $price->kKundenGruppe = (int)($price->kKundenGruppe ?? 0);
            $this->handlePriceFormat((int)$price->kArtikel, $price->kKundenGruppe, $price->kKunde);
            $details = empty($xml['tpreis'][$i])
                ? $this->mapper->mapArray($xml['tpreis'], 'tpreisdetail', 'mPreisDetail')
                : $this->mapper->mapArray($xml['tpreis'][$i], 'tpreisdetail', 'mPreisDetail');

            foreach ($details as $preisdetail) {
                $this->db->queryPrepared(
                    'INSERT INTO tpreisdetail (kPreis, nAnzahlAb, fVKNetto)
                        SELECT tpreis.kPreis, :countingFrom, :nettoPrice
                        FROM tpreis
                        WHERE tpreis.kArtikel = :productID
                            AND tpreis.kKundengruppe = :customerGroup
                            AND tpreis.kKunde = :customerPrice
                        ON DUPLICATE KEY UPDATE
                            tpreisdetail.fVKNetto = :nettoPrice',
                    [
                        'countingFrom'  => $preisdetail->nAnzahlAb,
                        'nettoPrice'    => $preisdetail->fNettoPreis,
                        'productID'     => $productID,
                        'customerGroup' => $price->kKundenGruppe,
                        'customerPrice' => $price->kKunde,
                    ]
                );
            }
        }
    }

    protected function flushCustomerPriceCache(int $customerID): bool|int
    {
        return $this->cache->flush('custprice_' . $customerID);
    }

    protected function mapSalutation(string $salutation): string
    {
        $salutation = \strtolower($salutation);
        if ($salutation === 'm' || $salutation === 'herr' || $salutation === 'mr' || $salutation === 'mr.') {
            return 'm';
        }
        if ($salutation === 'w' || $salutation === 'frau' || $salutation === 'mrs' || $salutation === 'mrs.') {
            return 'w';
        }

        return '';
    }

    /**
     * @return ($langID is null ? stdClass[]|null : stdClass|null)
     */
    protected function getSeoFromDB(
        int $keyValue,
        string $keyName,
        ?int $langID = null,
        ?string $assoc = null
    ): array|stdClass|null {
        if ($langID > 0) {
            return $this->db->select('tseo', 'kKey', $keyValue, 'cKey', $keyName, 'kSprache', $langID);
        }
        $seo = $this->db->selectAll('tseo', ['kKey', 'cKey'], [$keyValue, $keyName]);
        if (\count($seo) === 0) {
            return null;
        }
        if ($assoc === null) {
            return $seo;
        }

        return \collect($seo)->keyBy($assoc)->toArray();
    }

    /**
     * @param array|mixed $arr
     * @param string[]    $excludes
     * @return array<string, mixed>
     */
    protected function buildAttributes(mixed &$arr, array $excludes = []): array
    {
        $attributes = [];
        if (!\is_array($arr)) {
            return $attributes;
        }
        foreach (\array_keys($arr) as $key) {
            if (!\in_array($key, $excludes, true) && $key[0] === 'k') {
                $attributes[$key] = $arr[$key];
                unset($arr[$key]);
            }
        }

        return $attributes;
    }

    /**
     * @param object{cHausnummer: string, cStrasse: string} $object
     */
    protected function extractStreet(object $object): void
    {
        $data  = \explode(' ', $object->cStrasse);
        $parts = \count($data);
        if ($parts > 1) {
            $object->cHausnummer = $data[$parts - 1];
            unset($data[$parts - 1]);
            $object->cStrasse = \implode(' ', $data);
        }
    }

    protected function checkDbeSXmlRedirect(string $oldSeo, string $newSeo): bool
    {
        // Insert into tredirect weil sich SEO von Kategorie oder Artikel geändert hat
        if ($oldSeo === $newSeo || $oldSeo === '' || $newSeo === '') {
            return false;
        }
        $redirectService = new RedirectService(
            new RedirectRepository($this->db),
            new RedirectRefererRepository($this->db),
            new Normalizer()
        );
        $do              = $redirectService->createDO(
            '/' . $oldSeo,
            $newSeo,
            Settings::intValue(Globals::REDIRECTS_AUTOMATIC_PARAM_HANDLING),
            Type::WAWI
        );

        return $redirectService->save($do, true);
    }

    /**
     * faster than flatten() with a depth of 1
     * @param array<string[]> $tags
     * @return string[]
     * @since 5.2.0
     */
    protected function flattenTags(array $tags): array
    {
        $res = [];
        foreach ($tags as $arr) {
            foreach ($arr as $tag) {
                $res[] = $tag;
            }
        }

        return \array_unique($res);
    }
}
