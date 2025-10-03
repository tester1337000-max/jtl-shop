<?php

declare(strict_types=1);

namespace JTL\dbeS\Sync;

use Illuminate\Support\Collection;
use JTL\Catalog\Product\Artikel;
use JTL\dbeS\Starter;
use JTL\Helpers\Product;
use JTL\Helpers\Seo;
use JTL\Language\LanguageHelper;
use JTL\Settings\Option\Globals as GlobalOption;
use JTL\Settings\Option\Product as ProductOption;
use JTL\Settings\Settings;
use JTL\Shop;
use JTL\XML;
use stdClass;

use function Functional\map;

/**
 * Class Products
 * @package JTL\dbeS\Sync
 */
final class Products extends AbstractSync
{
    protected Settings $settings;

    protected int $categoryVisibilityFilter;

    protected int $productVisibilityFilter;

    private bool $affectsSearchSpecials = false;

    public function handle(Starter $starter): void
    {
        $this->settings                 = new Settings(Shop::getSettings([\CONF_GLOBAL, \CONF_ARTIKELDETAILS]));
        $this->categoryVisibilityFilter = $this->settings->int(GlobalOption::EMPTY_CATEGORY_FILTER);
        $this->productVisibilityFilter  = $this->settings->int(GlobalOption::PRODUCT_VIEW_FILTER);
        $productIDs                     = [];
        $this->db->query('START TRANSACTION');
        /**
         * @var int                  $i
         * @var array<string, mixed> $item
         */
        foreach ($starter->getXML() as $i => $item) {
            /**
             * @var string               $file
             * @var array<string, mixed> $xml
             */
            [$file, $xml] = [\key($item), \reset($item)];
            if (\str_contains($file, 'artdel.xml')) {
                $productIDs[] = $this->handleDeletes($xml);
            } else {
                $productIDs[] = $this->handleInserts($xml);
            }
            if ($i === 0) {
                $this->db->query(
                    'UPDATE tsuchcache
                        SET dGueltigBis = DATE_ADD(NOW(), INTERVAL ' . \SUCHCACHE_LEBENSDAUER . ' MINUTE)
                        WHERE dGueltigBis IS NULL'
                );
            }
        }
        $this->db->query('COMMIT');
        $this->clearProductCaches(\array_merge(...$productIDs));
    }

    /**
     * @param array<mixed> $xml
     */
    private function checkCategoryCache(array $xml, int $productID): void
    {
        if (
            !isset($xml['tartikel']['tkategorieartikel'])
            || $this->categoryVisibilityFilter !== \EINSTELLUNGEN_KATEGORIEANZEIGEFILTER_NICHTLEERE
            || !$this->cache->isCacheGroupActive(\CACHING_GROUP_CATEGORY)
        ) {
            return;
        }
        // get list of all categories the product is currently associated with
        $currentCategoryIDs = $this->db->getInts(
            'SELECT kKategorie
                FROM tkategorieartikel
                WHERE kArtikel = :pid',
            'kKategorie',
            ['pid' => $productID]
        );
        // get list of all categories the product will be associated with after this update
        $newCategoryIDs = map(
            $this->mapper->mapArray(
                $xml['tartikel'],
                'tkategorieartikel',
                'mKategorieArtikel'
            ),
            static fn($e): int => (int)$e->kKategorie
        );
        $stockFilter    = Shop::getProductFilter()->getFilterSQL()->getStockFilterSQL();
        $flush          = $this->checkCategoryWillBeEmpty($currentCategoryIDs, $newCategoryIDs, $stockFilter)
            || $this->checkCategoryWasEmpty($currentCategoryIDs, $newCategoryIDs, $stockFilter)
            || $this->checkStockLevelChanges($productID, $xml, $newCategoryIDs, $stockFilter);

        if ($flush === true) {
            $this->flushCategoryTreeCache();
        }
    }

    /**
     * @param array<mixed> $xml
     * @param int[]        $newCategoryIDs
     */
    private function checkStockLevelChanges(
        int $productID,
        array $xml,
        array $newCategoryIDs,
        string $stockFilter
    ): bool {
        $filter = $this->productVisibilityFilter;
        if ($filter === \EINSTELLUNGEN_ARTIKELANZEIGEFILTER_ALLE || \count($newCategoryIDs) === 0) {
            return false;
        }
        $status = $this->db->getSingleObject(
            'SELECT cLagerBeachten, cLagerKleinerNull, fLagerbestand
                FROM tartikel
                WHERE kArtikel = :pid',
            ['pid' => $productID]
        );
        if ($status === null || $this->checkStock($status, $xml) !== true) {
            return false;
        }
        // get count of visible products in the product's future categories
        $productCountPerCategory = $this->db->getCollection(
            'SELECT tkategorie.kKategorie AS id, COUNT(tartikel.kArtikel) AS cnt
                FROM tkategorie
                LEFT JOIN tkategorieartikel
                    ON tkategorie.kKategorie = tkategorieartikel.kKategorie
                LEFT JOIN tartikel
                    ON tartikel.kArtikel = tkategorieartikel.kArtikel ' . $stockFilter . '
                WHERE tkategorie.kKategorie IN (' . \implode(',', $newCategoryIDs) . ')
                GROUP BY tkategorie.kKategorie'
        );
        /** @var stdClass $item */
        foreach ($productCountPerCategory as $item) {
            $cnt = (int)$item->cnt;
            if (($status->cLagerBeachten !== 'Y' && $cnt === 1) || ($status->cLagerBeachten === 'Y' && $cnt === 0)) {
                // there was just one product that is now sold out
                // or there were just sold out products and now it's not sold out anymore
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<mixed> $xml
     */
    private function checkStock(?stdClass $currentStatus, array $xml): bool
    {
        return $currentStatus !== null
            && (($currentStatus->fLagerbestand <= 0 && $xml['tartikel']['fLagerbestand'] > 0)
                // product was not in stock before but is now - check if flush is necessary
                || ($currentStatus->fLagerbestand > 0 && $xml['tartikel']['fLagerbestand'] <= 0)
                // product was in stock before but is not anymore - check if flush is necessary
                || ($this->productVisibilityFilter === \EINSTELLUNGEN_ARTIKELANZEIGEFILTER_LAGERNULL
                    && $currentStatus->cLagerKleinerNull !== $xml['tartikel']['cLagerKleinerNull'])
                // overselling status changed - check if flush is necessary
                || ($currentStatus->cLagerBeachten !== $xml['tartikel']['cLagerBeachten']
                    && $xml['tartikel']['fLagerbestand'] <= 0));
    }

    /**
     * @param int[]  $currentIDs
     * @param int[]  $newIDs
     * @param string $stockFilter
     * @return bool
     */
    private function checkCategoryWasEmpty(array $currentIDs, array $newIDs, string $stockFilter): bool
    {
        $diff = \array_diff($newIDs, $currentIDs);
        if (\count($diff) === 0) {
            return false;
        }
        $collection = $this->db->getCollection(
            'SELECT tkategorie.kKategorie, COUNT(tkategorieartikel.kArtikel) AS cnt
                FROM tkategorie
                LEFT JOIN  tkategorieartikel
                    ON tkategorie.kKategorie = tkategorieartikel.kKategorie
                LEFT JOIN tartikel
                    ON tartikel.kArtikel = tkategorieartikel.kArtikel
                WHERE tkategorie.kKategorie IN (' . \implode(',', $diff) . ') ' . $stockFilter . '
                GROUP BY tkategorie.kKategorie'
        );

        return $collection->contains('cnt', 0) || $collection->count() < \count($diff);
    }

    /**
     * @param int[]  $currentIDs
     * @param int[]  $newIDs
     * @param string $stockFilter
     * @return bool
     */
    private function checkCategoryWillBeEmpty(array $currentIDs, array $newIDs, string $stockFilter): bool
    {
        $diff = \array_diff($currentIDs, $newIDs);
        if (\count($diff) === 0) {
            return false;
        }
        // check if the product was the only one in at least one of these categories
        return $this->db->getCollection(
            'SELECT tkategorieartikel.kKategorie, COUNT(tkategorieartikel.kArtikel) AS cnt
                FROM tkategorieartikel
                LEFT JOIN tartikel
                    ON tartikel.kArtikel = tkategorieartikel.kArtikel
                WHERE tkategorieartikel.kKategorie IN (' . \implode(',', $diff) . ') ' . $stockFilter . '
                GROUP BY tkategorieartikel.kKategorie'
        )->contains('cnt', '1');
    }

    /**
     * @param stdClass[] $products
     * @return stdClass[]
     */
    private function addProduct(array $products): array
    {
        if (!$products[0]->cSeo) {
            // get seo path from productname, but replace slashes
            $products[0]->cSeo = Seo::checkSeo(Seo::getSeo(Seo::getFlatSeoPath($products[0]->cName)));
        } else {
            $products[0]->cSeo = Seo::checkSeo(Seo::getSeo($products[0]->cSeo, true));
        }
        // persistente werte
        $products[0]->dLetzteAktualisierung = 'NOW()';
        // mysql strict fixes
        if (empty($products[0]->dMHD)) {
            $products[0]->dMHD = '_DBNULL_';
        }
        if (isset($products[0]->dErstellt) && $products[0]->dErstellt === '') {
            $products[0]->dErstellt = 'NOW()';
        }
        if (empty($products[0]->dZulaufDatum)) {
            $products[0]->dZulaufDatum = '_DBNULL_';
        }
        if (empty($products[0]->dErscheinungsdatum)) {
            $products[0]->dErscheinungsdatum = '_DBNULL_';
        }
        if (!isset($products[0]->fLieferantenlagerbestand) || $products[0]->fLieferantenlagerbestand === '') {
            $products[0]->fLieferantenlagerbestand = 0;
        }
        if (!isset($products[0]->fZulauf) || $products[0]->fZulauf === '') {
            $products[0]->fZulauf = 0;
        }
        if (!isset($products[0]->fLieferzeit) || $products[0]->fLieferzeit === '') {
            $products[0]->fLieferzeit = 0;
        }
        // temp. fix for syncing with wawi 1.0
        if (isset($products[0]->kVPEEinheit) && \is_array($products[0]->kVPEEinheit)) {
            $products[0]->kVPEEinheit = $products[0]->kVPEEinheit[0];
        }
        if (isset($products[0]->fGewicht) && (int)$products[0]->fGewicht < 0) {
            $products[0]->fGewicht = 0;
            $this->logger->warning(
                'Artikel-Sync: fGewicht < 0 von kArtikel {pid} wurde auf 0 gesetzt.',
                ['pid' => (int)$products[0]->kArtikel]
            );
        }
        if (isset($products[0]->fArtikelgewicht) && (int)$products[0]->fArtikelgewicht < 0) {
            $products[0]->fArtikelgewicht = 0;
            $this->logger->warning(
                'Artikel-Sync: fArtikelgewicht < 0 von kArtikel {pid} wurde auf 0 gesetzt.',
                ['pid' => (int)$products[0]->kArtikel]
            );
        }
        // any new orders since last wawi-sync?
        if (isset($products[0]->fLagerbestand) && $products[0]->fLagerbestand > 0) {
            $delta = $this->db->getSingleObject(
                "SELECT SUM(pos.nAnzahl) AS totalquantity
                    FROM tbestellung b
                    JOIN twarenkorbpos pos
                        ON pos.kWarenkorb = b.kWarenkorb
                    WHERE b.cAbgeholt = 'N'
                        AND pos.kArtikel = :pid",
                ['pid' => (int)$products[0]->kArtikel]
            );
            if ($delta !== null && $delta->totalquantity > 0) {
                $products[0]->fLagerbestand -= $delta->totalquantity;
                $this->logger->debug(
                    'Artikel-Sync: Lagerbestand von kArtikel {pid} wurde '
                    . 'wegen nicht-abgeholter Bestellungen um {qty} auf {stk} reduziert.',
                    [
                        'pid' => (int)$products[0]->kArtikel,
                        'qty' => $delta->totalquantity,
                        'stk' => $products[0]->fLagerbestand
                    ]
                );
            }
        }
        $this->upsert('tartikel', $products, 'kArtikel');
        $this->affectsSearchSpecials = $this->affectsSearchSpecials
            || (($products[0]->cNeu ?? 'N') === 'Y')
            || (($products[0]->cTopArtikel ?? 'N') === 'Y');
        \executeHook(\HOOK_ARTIKEL_XML_BEARBEITEINSERT, ['oArtikel' => $products[0]]);

        return $products;
    }

    private function addSeo(?string $oldSeo, string $newSeo, int $productID): void
    {
        if ($oldSeo !== null) {
            $this->checkDbeSXmlRedirect($oldSeo, $newSeo);
        }
        $this->db->queryPrepared(
            "INSERT INTO tseo
                SELECT tartikel.cSeo, 'kArtikel', tartikel.kArtikel, tsprache.kSprache
                FROM tartikel, tsprache
                WHERE tartikel.kArtikel = :pid
                    AND tsprache.cStandard = 'Y'
                    AND tartikel.cSeo != ''",
            ['pid' => $productID]
        );
    }

    /**
     * @param array<mixed>    $xml
     * @param stdClass[]      $products
     * @param int             $productID
     * @param stdClass[]|null $oldSeoData
     */
    private function addProductLocalizations(array $xml, array $products, int $productID, ?array $oldSeoData): void
    {
        $seoData      = $oldSeoData ?? $this->getSeoFromDB($productID, 'kArtikel', null, 'kSprache');
        $localized    = $this->mapper->mapArray(
            $xml['tartikel'],
            'tartikelsprache',
            'mArtikelSprache'
        );
        $allLanguages = LanguageHelper::getAllLanguages(1);
        foreach ($localized as $item) {
            if (!LanguageHelper::isShopLanguage((int)$item->kSprache, $allLanguages)) {
                continue;
            }
            if ($item->cSeo) {
                $item->cSeo = Seo::getSeo($item->cSeo, true);
            } else {
                $item->cSeo = Seo::getSeo(Seo::getFlatSeoPath($item->cName));
                if (!$item->cSeo) {
                    $item->cSeo = Seo::getSeo($products[0]->cSeo, true);
                }
                if (!$item->cSeo) {
                    $item->cSeo = Seo::getSeo($products[0]->cName);
                }
            }
            $item->cSeo = Seo::checkSeo($item->cSeo);

            $this->upsert('tartikelsprache', [$item], 'kArtikel', 'kSprache');
            $this->db->delete(
                'tseo',
                ['cKey', 'kKey', 'kSprache'],
                ['kArtikel', (int)$item->kArtikel, (int)$item->kSprache]
            );

            $seo           = new stdClass();
            $seo->cSeo     = $item->cSeo;
            $seo->cKey     = 'kArtikel';
            $seo->kKey     = $item->kArtikel;
            $seo->kSprache = $item->kSprache;
            $this->db->insert('tseo', $seo);
            // Insert into tredirect weil sich das SEO vom Artikel geändert hat
            if (isset($seoData[$item->kSprache])) {
                $this->checkDbeSXmlRedirect(
                    $seoData[$item->kSprache]->cSeo,
                    $item->cSeo
                );
            }
        }
    }

    /**
     * @param array<mixed> $xml
     */
    private function addAttributes(array $xml): void
    {
        if (!isset($xml['tartikel']['tattribut']) || !\is_array($xml['tartikel']['tattribut'])) {
            return;
        }
        $attributes = $this->mapper->mapArray(
            $xml['tartikel'],
            'tattribut',
            'mAttribut'
        );
        $attrCount  = \count($attributes);
        for ($i = 0; $i < $attrCount; ++$i) {
            if ($attrCount < 2) {
                $this->deleteAttribute((int)$xml['tartikel']['tattribut attr']['kAttribut']);
                $this->upsertXML(
                    $xml['tartikel']['tattribut'],
                    'tattributsprache',
                    'mAttributSprache',
                    'kAttribut',
                    'kSprache'
                );
            } else {
                $this->deleteAttribute((int)$xml['tartikel']['tattribut'][$i . ' attr']['kAttribut']);
                $this->upsertXML(
                    $xml['tartikel']['tattribut'][$i],
                    'tattributsprache',
                    'mAttributSprache',
                    'kAttribut',
                    'kSprache'
                );
            }
        }
        $this->upsert('tattribut', $attributes, 'kAttribut');
    }

    /**
     * @param array<mixed> $xml
     */
    private function addMediaFiles(array $xml): void
    {
        $source = $xml['tartikel']['tmediendatei'] ?? null;
        if (!\is_array($source)) {
            return;
        }
        $mediaFiles = $this->mapper->mapArray($xml['tartikel'], 'tmediendatei', 'mMediendatei');
        $mediaCount = \count($mediaFiles);
        for ($i = 0; $i < $mediaCount; ++$i) {
            if ($mediaCount < 2) {
                $this->deleteMediaFile((int)$xml['tartikel']['tmediendatei attr']['kMedienDatei']);
                $this->upsertXML(
                    $source,
                    'tmediendateisprache',
                    'mMediendateisprache',
                    'kMedienDatei',
                    'kSprache'
                );
                $this->upsertXML(
                    $source,
                    'tmediendateiattribut',
                    'mMediendateiattribut',
                    'kMedienDateiAttribut'
                );
            } else {
                $this->deleteMediaFile((int)$source[$i . ' attr']['kMedienDatei']);
                $this->upsertXML(
                    $source[$i],
                    'tmediendateisprache',
                    'mMediendateisprache',
                    'kMedienDatei',
                    'kSprache'
                );
                $this->upsertXML(
                    $source[$i],
                    'tmediendateiattribut',
                    'mMediendateiattribut',
                    'kMedienDateiAttribut'
                );
            }
        }
        $this->upsert('tmediendatei', $mediaFiles, 'kMedienDatei');
    }

    /**
     * @param array<mixed> $xml
     * @param int[]        $downloadKeys
     * @param int          $productID
     */
    private function addDownloads(array $xml, array $downloadKeys, int $productID): void
    {
        if (isset($xml['tartikel']['tArtikelDownload']) && \is_array($xml['tartikel']['tArtikelDownload'])) {
            $downloads = [];
            $this->deleteDownload($productID);
            $dlData = $xml['tartikel']['tArtikelDownload']['kDownload'];
            if (\is_array($dlData)) {
                foreach ($dlData as $downloadID) {
                    $download            = new stdClass();
                    $download->kDownload = (int)$downloadID;
                    $download->kArtikel  = $productID;
                    $downloads[]         = $download;
                    if (($idx = \array_search($download->kDownload, $downloadKeys, true)) !== false) {
                        unset($downloadKeys[$idx]);
                    }
                }
            } else {
                $download            = new stdClass();
                $download->kDownload = (int)$dlData;
                $download->kArtikel  = $productID;
                $downloads[]         = $download;
                if (($idx = \array_search($download->kDownload, $downloadKeys, true)) !== false) {
                    unset($downloadKeys[$idx]);
                }
            }
            $this->upsert('tartikeldownload', $downloads, 'kArtikel', 'kDownload');
        }
        foreach ($downloadKeys as $downloadID) {
            $this->deleteDownload($productID, $downloadID);
        }
    }

    /**
     * @param array<mixed> $xml
     */
    private function addUploads(array $xml): void
    {
        if (!isset($xml['tartikel']['tartikelupload']) || !\is_array($xml['tartikel']['tartikelupload'])) {
            return;
        }
        $uploads = $this->mapper->mapArray($xml['tartikel'], 'tartikelupload', 'mArtikelUpload');
        foreach ($uploads as $upload) {
            $upload->nTyp          = 3;
            $upload->kUploadSchema = $upload->kArtikelUpload;
            $upload->kCustomID     = $upload->kArtikel;
            unset($upload->kArtikelUpload, $upload->kArtikel);
        }
        $this->upsert('tuploadschema', $uploads, 'kUploadSchema', 'kCustomID');
        $ulCount = \count($uploads);
        if ($ulCount < 2) {
            $localizedUploads = $this->mapper->mapArray(
                $xml['tartikel']['tartikelupload'],
                'tartikeluploadsprache',
                'mArtikelUploadSprache'
            );
            $this->upsert('tuploadschemasprache', $localizedUploads, 'kArtikelUpload', 'kSprache');
        } else {
            for ($i = 0; $i < $ulCount; ++$i) {
                $localizedUploads = $this->mapper->mapArray(
                    $xml['tartikel']['tartikelupload'][$i],
                    'tartikeluploadsprache',
                    'mArtikelUploadSprache'
                );
                $this->upsert('tuploadschemasprache', $localizedUploads, 'kArtikelUpload', 'kSprache');
            }
        }
    }

    /**
     * @param array<mixed> $xml
     */
    private function addPartList(array $xml): void
    {
        if (!isset($xml['tartikel']['tstueckliste']) || !\is_array($xml['tartikel']['tstueckliste'])) {
            return;
        }
        $partlists = $this->mapper->mapArray($xml['tartikel'], 'tstueckliste', 'mStueckliste');
        $cacheIDs  = [];
        if (\count($partlists) > 0) {
            $this->deletePartList((int)$partlists[0]->kStueckliste);
        }
        $this->upsert('tstueckliste', $partlists, 'kStueckliste', 'kArtikel');
        foreach ($partlists as $_sl) {
            if (isset($_sl->kArtikel)) {
                $cacheIDs[] = \CACHING_GROUP_ARTICLE . '_' . (int)$_sl->kArtikel;
            }
        }
        if (\count($cacheIDs) > 0) {
            $this->cache->flushTags($cacheIDs);
        }
    }

    /**
     * @param array<mixed> $xml
     */
    private function addConfigGroups(array $xml): void
    {
        if (!isset($xml['tartikel']['tartikelkonfiggruppe']) || !\is_array($xml['tartikel']['tartikelkonfiggruppe'])) {
            return;
        }
        $productConfig = $this->mapper->mapArray(
            $xml['tartikel'],
            'tartikelkonfiggruppe',
            'mArtikelkonfiggruppe'
        );
        $this->upsert('tartikelkonfiggruppe', $productConfig, 'kArtikel', 'kKonfiggruppe');
    }

    /**
     * @param array<mixed> $xml
     * @throws \Exception
     */
    private function addPrices(array $xml, int $productID): void
    {
        if (
            isset($xml['tartikel']['tartikelsonderpreis']['dEnde'])
            && $xml['tartikel']['tartikelsonderpreis']['dEnde'] === ''
        ) {
            $xml['tartikel']['tartikelsonderpreis']['dEnde'] = '_DBNULL_';
        }

        $this->handleNewPriceFormat($productID, $xml['tartikel']);
        $this->handlePriceHistory($productID, $xml['tartikel']);
        $this->upsertXML(
            $xml['tartikel'],
            'tartikelsonderpreis',
            'mArtikelSonderpreis',
            'kArtikelSonderpreis'
        );
        if (isset($xml['tartikel']['tartikelsonderpreis']) && \is_array($xml['tartikel']['tartikelsonderpreis'])) {
            $productSpecialPrices = $this->mapper->mapArray(
                $xml['tartikel'],
                'tartikelsonderpreis',
                'mArtikelSonderpreis'
            );
            $this->upsertXML(
                $xml['tartikel']['tartikelsonderpreis'],
                'tsonderpreise',
                'mSonderpreise',
                'kArtikelSonderpreis',
                'kKundengruppe'
            );
            $this->upsert('tartikelsonderpreis', $productSpecialPrices, 'kArtikelSonderpreis');
            $this->affectsSearchSpecials = true;
        }
    }

    /**
     * @param array<mixed> $xml
     */
    private function addCharacteristics(array $xml): void
    {
        $source = $xml['tartikel']['teigenschaft'] ?? null;
        if (!\is_array($source)) {
            return;
        }
        $characteristics = $this->mapper->mapArray($xml['tartikel'], 'teigenschaft', 'mEigenschaft');
        $cCount          = \count($characteristics);
        foreach ($characteristics as $characteristic) {
            if (empty($characteristic->cTyp)) {
                $this->logger->error('teigenschaft.cTyp cannot be NULL. Product: ' . $xml['tartikel']['cName']);
                break;
            }
        }
        for ($i = 0; $i < $cCount; ++$i) {
            if ($cCount < 2) {
                $this->deleteProperty((int)$xml['tartikel']['teigenschaft attr']['kEigenschaft']);
                $this->upsertXML($source, 'teigenschaftsprache', 'mEigenschaftSprache', 'kEigenschaft', 'kSprache');
                $this->upsertXML(
                    $source,
                    'teigenschaftsichtbarkeit',
                    'mEigenschaftsichtbarkeit',
                    'kEigenschaft',
                    'kKundengruppe'
                );
                $propValues = $this->mapper->mapArray($source, 'teigenschaftwert', 'mEigenschaftWert');
                $pvCount    = \count($propValues);
                for ($o = 0; $o < $pvCount; ++$o) {
                    if ($pvCount < 2) {
                        $this->deletePropertyValue((int)$source['teigenschaftwert attr']['kEigenschaftWert']);
                        $item = $source['teigenschaftwert'];
                    } else {
                        $this->deletePropertyValue((int)$source['teigenschaftwert'][$o . ' attr']['kEigenschaftWert']);
                        $item = $source['teigenschaftwert'][$o];
                    }
                    $this->upsertXML(
                        $item,
                        'teigenschaftwertsprache',
                        'mEigenschaftWertSprache',
                        'kEigenschaftWert',
                        'kSprache'
                    );
                    $this->upsertXML(
                        $item,
                        'teigenschaftwertaufpreis',
                        'mEigenschaftWertAufpreis',
                        'kEigenschaftWert',
                        'kKundengruppe'
                    );
                    $this->upsertXML(
                        $item,
                        'teigenschaftwertsichtbarkeit',
                        'mEigenschaftWertSichtbarkeit',
                        'kEigenschaftWert',
                        'kKundengruppe'
                    );
                    $this->upsertXML(
                        $item,
                        'teigenschaftwertabhaengigkeit',
                        'mEigenschaftWertAbhaengigkeit',
                        'kEigenschaftWert',
                        'kEigenschaftWertZiel'
                    );
                }
                $this->upsert('teigenschaftwert', $propValues, 'kEigenschaftWert');
            } else {
                $idx = $i . ' attr';
                if (isset($source[$idx])) {
                    $this->deleteProperty((int)$source[$idx]['kEigenschaft']);
                }
                if (isset($source[$i])) {
                    $current = $source[$i];
                    $this->upsertXML(
                        $current,
                        'teigenschaftsprache',
                        'mEigenschaftSprache',
                        'kEigenschaft',
                        'kSprache'
                    );
                    $this->upsertXML(
                        $current,
                        'teigenschaftsichtbarkeit',
                        'mEigenschaftsichtbarkeit',
                        'kEigenschaft',
                        'kKundengruppe'
                    );
                    $propValues = $this->mapper->mapArray(
                        $current,
                        'teigenschaftwert',
                        'mEigenschaftWert'
                    );
                    $pvCount    = \count($propValues);
                    for ($o = 0; $o < $pvCount; ++$o) {
                        if ($pvCount < 2) {
                            $this->deletePropertyValue((int)$current['teigenschaftwert attr']['kEigenschaftWert']);
                            $item = $current['teigenschaftwert'];
                        } else {
                            $this->deletePropertyValue(
                                (int)$current['teigenschaftwert'][$o . ' attr']['kEigenschaftWert']
                            );
                            $item = $current['teigenschaftwert'][$o];
                        }
                        $this->upsertXML(
                            $item,
                            'teigenschaftwertsprache',
                            'mEigenschaftWertSprache',
                            'kEigenschaftWert',
                            'kSprache'
                        );
                        $this->upsertXML(
                            $item,
                            'teigenschaftwertaufpreis',
                            'mEigenschaftWertAufpreis',
                            'kEigenschaftWert',
                            'kKundengruppe'
                        );
                        $this->upsertXML(
                            $item,
                            'teigenschaftwertsichtbarkeit',
                            'mEigenschaftWertSichtbarkeit',
                            'kEigenschaftWert',
                            'kKundengruppe'
                        );
                        $this->upsertXML(
                            $item,
                            'teigenschaftwertabhaengigkeit',
                            'mEigenschaftWertAbhaengigkeit',
                            'kEigenschaftWert',
                            'kEigenschaftWertZiel'
                        );
                    }
                    $this->upsert('teigenschaftwert', $propValues, 'kEigenschaftWert');
                }
            }
        }
        $this->upsert('teigenschaft', $characteristics, 'kEigenschaft');
    }

    /**
     * @param array<mixed> $xml
     */
    private function addWarehouseData(array $xml, int $productID): void
    {
        $this->db->delete('tartikelwarenlager', 'kArtikel', $productID);
        if (!isset($xml['tartikel']['tartikelwarenlager']) || !\is_array($xml['tartikel']['tartikelwarenlager'])) {
            return;
        }
        $storages = $this->mapper->mapArray($xml['tartikel'], 'tartikelwarenlager', 'mArtikelWarenlager');
        foreach ($storages as $storage) {
            if (empty($storage->dZulaufDatum)) {
                $storage->dZulaufDatum = null;
            }
            // Prevent SQL-Exception if duplicate datasets will be sent falsely
            $this->db->queryPrepared(
                'INSERT INTO tartikelwarenlager (kArtikel, kWarenlager, fBestand, fZulauf, dZulaufDatum)
                    VALUES (:kArtikel, :kWarenlager, :fBestand, :fZulauf, :dZulaufDatum)
                    ON DUPLICATE KEY UPDATE
                    fBestand = :fBestand,
                    fZulauf = :fZulauf,
                    dZulaufDatum = :dZulaufDatum',
                [
                    'kArtikel'     => $storage->kArtikel,
                    'kWarenlager'  => $storage->kWarenlager,
                    'fBestand'     => $storage->fBestand,
                    'fZulauf'      => $storage->fZulauf,
                    'dZulaufDatum' => $storage->dZulaufDatum ?? null,
                ]
            );
        }
    }

    /**
     * @param array<mixed> $xml
     */
    private function handleSQL(array $xml): void
    {
        if (isset($xml['tartikel']['SQLDEL']) && \strlen($xml['tartikel']['SQLDEL']) > 10) {
            $this->logger->debug('SQLDEL: {sql}', ['sql' => $xml['tartikel']['SQLDEL']]);
            foreach (\explode("\n", $xml['tartikel']['SQLDEL']) as $sql) {
                if (\strlen($sql) <= 10) {
                    continue;
                }
                $this->db->query($sql);
            }
        }
        if (!isset($xml['tartikel']['SQL']) || \strlen($xml['tartikel']['SQL']) <= 10) {
            return;
        }
        $this->logger->debug('SQL: {sql}', ['sql' => $xml['tartikel']['SQL']]);
        foreach (\explode("\n", $xml['tartikel']['SQL']) as $sql) {
            if (\strlen($sql) <= 10) {
                continue;
            }
            $this->db->query($sql);
        }
    }

    /**
     * @param stdClass $product
     */
    private function addStockData(stdClass $product): void
    {
        if ((int)$product->nIstVater === 1) {
            $productID = (int)$product->kArtikel;
            $this->db->queryPrepared(
                'UPDATE tartikel SET fLagerbestand = (SELECT * FROM
                    (SELECT SUM(fLagerbestand)
                        FROM tartikel
                        WHERE kVaterartikel = :pid
                     ) AS x
                 )
                WHERE kArtikel = :pid',
                ['pid' => $productID]
            );
            Artikel::beachteVarikombiMerkmalLagerbestand($productID, $this->productVisibilityFilter);
        } elseif (isset($product->kVaterArtikel) && $product->kVaterArtikel > 0) {
            $productID = (int)$product->kVaterArtikel;
            $this->db->queryPrepared(
                'UPDATE tartikel SET fLagerbestand =
                (SELECT * FROM
                    (SELECT SUM(fLagerbestand)
                        FROM tartikel
                        WHERE kVaterartikel = :pid
                    ) AS x
                )
                WHERE kArtikel = :pid',
                ['pid' => $productID]
            );
            // Aktualisiere Merkmale in tartikelmerkmal vom Vaterartikel
            Artikel::beachteVarikombiMerkmalLagerbestand($productID, $this->productVisibilityFilter);
        }
    }

    /**
     * @param array<mixed> $xml
     */
    private function addMinPurchaseData(array $xml, int $productID): void
    {
        $this->db->delete('tartikelabnahme', 'kArtikel', $productID);
        if (isset($xml['tartikel']['tartikelabnahme']) && \is_array($xml['tartikel']['tartikelabnahme'])) {
            $intervals = $this->mapper->mapArray($xml['tartikel'], 'tartikelabnahme', 'mArtikelAbnahme');
            $this->upsert('tartikelabnahme', $intervals, 'kArtikel', 'kKundengruppe');
        }
    }

    /**
     * @param array<mixed> $xml
     * @return int[] - list of product IDs to flush
     */
    private function handleInserts(array $xml): array
    {
        $res       = [];
        $productID = 0;
        $product   = $xml['tartikel'] ?? null;
        if (\is_array($xml['tartikel attr'])) {
            $productID = (int)$xml['tartikel attr']['kArtikel'];
        }
        if (!$productID) {
            $this->logger->error('kArtikel fehlt! XML: ' . XML::getLastParseError() . ' in:' . \print_r($xml, true));

            return $res;
        }
        if (!\is_array($product)) {
            return $res;
        }
        $products   = $this->mapper->mapArray($xml, 'tartikel', 'mArtikel');
        $oldSeo     = $this->db->getSingleObject(
            'SELECT cSeo 
                FROM tartikel 
                WHERE kArtikel = :pid',
            ['pid' => $productID]
        )->cSeo ?? null;
        $oldSeoData = $this->getSeoFromDB($productID, 'kArtikel', null, 'kSprache');
        $this->checkCategoryCache($xml, $productID);
        $downloadKeys = $this->getDownloadIDs($productID);
        $this->deleteProduct($productID);
        $products = $this->addProduct($products);
        $this->addSeo($oldSeo, $products[0]->cSeo, $productID);
        $this->addProductLocalizations($xml, $products, $productID, $oldSeoData);
        $this->addAttributes($xml);
        $this->addMediaFiles($xml);
        $this->addDownloads($xml, $downloadKeys, $productID);
        $this->addPartList($xml);
        $this->addUploads($xml);
        $this->addMinPurchaseData($xml, $productID);
        $this->addConfigGroups($xml);
        $this->upsertXML($product, 'tkategorieartikel', 'mKategorieArtikel', 'kKategorieArtikel');
        $this->upsertXML($product, 'tartikelattribut', 'mArtikelAttribut', 'kArtikelAttribut');
        $this->upsertXML($product, 'tartikelsichtbarkeit', 'mArtikelSichtbarkeit', 'kKundengruppe', 'kArtikel');
        $this->upsertXML($product, 'txsell', 'mXSell', 'kXSell');
        $this->upsertXML($product, 'tartikelmerkmal', 'mArtikelSichtbarkeit', 'kMerkmalWert', 'kArtikel');
        $this->addStockData($products[0]);
        $this->handleSQL($xml);
        $this->addWarehouseData($xml, $productID);
        $this->addCharacteristics($xml);
        $this->addCategoryDiscounts($productID);
        $this->addPrices($xml, $productID);
        $res[] = $productID;
        if (!empty($products[0]->kVaterartikel)) {
            $res[] = (int)$products[0]->kVaterartikel;
        }
        $this->sendAvailabilityMails(
            $products[0],
            $this->settings->int(ProductOption::AVAILABILITY_REQ_MIN_QTY)
        );
        \executeHook(\HOOK_SYNC_ADDED_PRODUCT, [
            'productID' => $productID,
            'product'   => $products[0],
            'xml'       => $xml,
        ]);

        return $res;
    }

    /**
     * @param array<mixed> $xml
     * @return int[] - list of product IDs
     */
    private function handleDeletes(array $xml): array
    {
        $res = [];
        if (!\is_array($xml['del_artikel'])) {
            return $res;
        }
        if (!\is_array($xml['del_artikel']['kArtikel'])) {
            $xml['del_artikel']['kArtikel'] = [$xml['del_artikel']['kArtikel']];
        }
        foreach ($xml['del_artikel']['kArtikel'] as $productID) {
            $productID = (int)$productID;
            if (
                (int)($this->db->selectSingleRow(
                    'tartikel',
                    'kArtikel',
                    $productID,
                    null,
                    null,
                    null,
                    null,
                    false,
                    'kArtikel'
                )->kArtikel ?? 0) === 0
            ) {
                continue;
            }
            $parent = Product::getParent($productID);
            $this->db->queryPrepared(
                'DELETE teigenschaftkombiwert
                    FROM teigenschaftkombiwert
                    JOIN tartikel
                        ON tartikel.kArtikel = :pid
                        AND tartikel.kEigenschaftKombi = teigenschaftkombiwert.kEigenschaftKombi',
                ['pid' => $productID]
            );
            $this->removeProductIdfromCoupons($productID);
            $res[] = $this->deleteProduct($productID, true);
            if ($parent > 0) {
                Artikel::beachteVarikombiMerkmalLagerbestand($parent);
                $res[] = $parent;
            }
            \executeHook(\HOOK_ARTIKEL_XML_BEARBEITEDELETES, [
                'kArtikel'      => $productID,
                'kVaterArtikel' => $parent,
            ]);
        }

        return $res;
    }

    private function deleteProduct(int $id, bool $force = false): int
    {
        if ($id <= 0) {
            return 0;
        }
        // get list of all categories the product was associated with
        $categories = $this->db->selectAll(
            'tkategorieartikel',
            'kArtikel',
            $id,
            'kKategorie'
        );
        if ($force === true && $this->categoryVisibilityFilter === \EINSTELLUNGEN_KATEGORIEANZEIGEFILTER_NICHTLEERE) {
            $stockFilter = Shop::getProductFilter()->getFilterSQL()->getStockFilterSQL();
            foreach ($categories as $category) {
                // check if the product was the only one in at least one of these categories
                $categoryCount = $this->db->getSingleInt(
                    'SELECT COUNT(tkategorieartikel.kArtikel) AS cnt
                        FROM tkategorieartikel
                        LEFT JOIN tartikel
                            ON tartikel.kArtikel = tkategorieartikel.kArtikel
                        WHERE tkategorieartikel.kKategorie = :cid ' . $stockFilter,
                    'cnt',
                    ['cid' => (int)$category->kKategorie]
                );
                if ($categoryCount <= 1) {
                    // the category only had this product in it - flush cache
                    $this->flushCategoryTreeCache();
                    break;
                }
            }
        }
        $this->db->delete('tseo', ['cKey', 'kKey'], ['kArtikel', $id]);
        $this->db->delete('tartikel', 'kArtikel', $id);
        $this->db->delete('tkategorieartikel', 'kArtikel', $id);
        $this->db->delete('tartikelsprache', 'kArtikel', $id);
        $this->db->delete('tartikelattribut', 'kArtikel', $id);
        $this->db->delete('tartikelwarenlager', 'kArtikel', $id);
        $this->db->delete('tartikelabnahme', 'kArtikel', $id);
        $this->deleteProductAttributes($id);
        $this->deleteProductAttributeValues($id);
        $this->deleteProperties($id);
        $this->deletePrices($id);
        $this->deleteSpecialPrices($id);
        $this->db->delete('txsell', 'kArtikel', $id);
        $this->db->delete('tartikelmerkmal', 'kArtikel', $id);
        $this->db->delete('tartikelsichtbarkeit', 'kArtikel', $id);
        $this->deleteProductMediaFiles($id);
        if ($force === true) {
            $this->deleteProductDownloads($id);
            $this->deleteProductUploads($id);
            $this->db->delete('tartikelkategorierabatt', 'kArtikel', $id);
            $this->db->delete('tartikelpicthistory', 'kArtikel', $id);
            $this->db->delete('tsuchcachetreffer', 'kArtikel', $id);
            $this->db->delete('timagemaparea', 'kArtikel', $id);
            $this->db->delete('tvergleichslistepos', 'kArtikel', $id);
            $this->db->delete('twunschlistepos', 'kArtikel', $id);
        } else {
            $this->deleteDownload($id);
        }
        $this->deleteConfigGroup($id);

        return $id;
    }

    private function deleteProperty(int $id): void
    {
        $this->db->delete('teigenschaft', 'kEigenschaft', $id);
        $this->db->delete('teigenschaftsprache', 'kEigenschaft', $id);
        $this->db->delete('teigenschaftsichtbarkeit', 'kEigenschaft', $id);
        $this->db->delete('teigenschaftwert', 'kEigenschaft', $id);
    }

    private function deleteProperties(int $productID): void
    {
        foreach ($this->db->selectAll('teigenschaft', 'kArtikel', $productID, 'kEigenschaft') as $attribute) {
            $this->deleteProperty((int)$attribute->kEigenschaft);
        }
    }

    private function deletePropertyValue(int $id): void
    {
        $this->db->delete('teigenschaftwert', 'kEigenschaftWert', $id);
        $this->db->delete('teigenschaftwertaufpreis', 'kEigenschaftWert', $id);
        $this->db->delete('teigenschaftwertsichtbarkeit', 'kEigenschaftWert', $id);
        $this->db->delete('teigenschaftwertsprache', 'kEigenschaftWert', $id);
        $this->db->delete('teigenschaftwertabhaengigkeit', 'kEigenschaftWert', $id);
    }

    private function deleteProductAttributeValues(int $productID): void
    {
        $propValues = $this->db->getObjects(
            'SELECT teigenschaftwert.kEigenschaftWert AS id
                FROM teigenschaftwert
                JOIN teigenschaft
                    ON teigenschaft.kEigenschaft = teigenschaftwert.kEigenschaft
                WHERE teigenschaft.kArtikel = :pid',
            ['pid' => $productID]
        );
        foreach ($propValues as $propValue) {
            $this->deletePropertyValue((int)$propValue->id);
        }
    }

    private function deleteAttribute(int $id): void
    {
        $this->db->delete('tattribut', 'kAttribut', $id);
        $this->db->delete('tattributsprache', 'kAttribut', $id);
    }

    private function deleteProductAttributes(int $productID): void
    {
        foreach ($this->db->selectAll('tattribut', 'kArtikel', $productID, 'kAttribut') as $attribute) {
            $this->deleteAttribute((int)$attribute->kAttribut);
        }
    }

    private function deleteMediaFile(int $id): void
    {
        $this->db->delete('tmediendatei', 'kMedienDatei', $id);
        $this->db->delete('tmediendateisprache', 'kMedienDatei', $id);
        $this->db->delete('tmediendateiattribut', 'kMedienDatei', $id);
    }

    private function deleteProductMediaFiles(int $productID): void
    {
        foreach ($this->db->selectAll('tmediendatei', 'kArtikel', $productID, 'kMedienDatei') as $mediaFile) {
            $this->deleteMediaFile((int)$mediaFile->kMedienDatei);
        }
    }

    private function deleteUpload(int $id): void
    {
        $this->db->delete('tuploadschema', 'kUploadSchema', $id);
        $this->db->delete('tuploadschemasprache', 'kArtikelUpload', $id);
    }

    private function deleteProductUploads(int $productID): void
    {
        foreach ($this->db->selectAll('tuploadschema', 'kCustomID', $productID, 'kUploadSchema') as $upload) {
            $this->deleteUpload((int)$upload->kUploadSchema);
        }
        $this->db->delete('tuploadqueue', 'kArtikel', $productID);
    }

    private function deleteDownload(int $productID, ?int $downloadID = null): void
    {
        if ($productID > 0) {
            if ($downloadID > 0) {
                $this->db->delete('tartikeldownload', ['kArtikel', 'kDownload'], [$productID, $downloadID]);
            } else {
                $this->db->delete('tartikeldownload', 'kArtikel', $productID);
            }
        }
        if ($downloadID !== null) {
            $this->db->delete('tdownload', 'kDownload', $downloadID);
            $this->db->delete('tdownloadsprache', 'kDownload', $downloadID);
        }
    }

    /**
     * @return int[]
     */
    private function getDownloadIDs(int $productID): array
    {
        return $this->db->getInts(
            'SELECT kDownload
                FROM tartikeldownload
                WHERE kArtikel = :pid',
            'kDownload',
            ['pid' => $productID]
        );
    }

    private function deleteProductDownloads(int $productID): void
    {
        foreach ($this->getDownloadIDs($productID) as $downloadID) {
            $this->deleteDownload($productID, $downloadID);
        }
    }

    private function deleteConfigGroup(int $productID): void
    {
        $this->db->delete('tartikelkonfiggruppe', 'kArtikel', $productID);
    }

    private function deletePartList(int $id): void
    {
        $this->db->delete('tstueckliste', 'kStueckliste', $id);
    }

    private function deletePrices(int $productID): int
    {
        return $this->db->getAffectedRows(
            'DELETE p, pd
                FROM tpreis p
                INNER JOIN tpreisdetail pd ON pd.kPreis = p.kPreis
                WHERE  p.kArtikel = :productID',
            ['productID' => $productID]
        );
    }

    private function deleteSpecialPrices(int $productID): int
    {
        return $this->db->getAffectedRows(
            'DELETE asp, sp
                FROM tartikelsonderpreis asp
                LEFT JOIN tsonderpreise sp
                    ON sp.kArtikelSonderpreis = asp.kArtikelSonderpreis
                WHERE asp.kArtikel = :productID',
            ['productID' => $productID]
        );
    }

    private function removeProductIdfromCoupons(int $productID): void
    {
        $data = $this->db->getSingleObject(
            'SELECT cArtNr FROM tartikel WHERE kArtikel = :pid',
            ['pid' => $productID]
        );
        if ($data !== null && !empty($data->cArtNr)) {
            $artNo = $data->cArtNr;
            $this->db->queryPrepared(
                "UPDATE tkupon SET cArtikel = REPLACE(cArtikel, :rep, ';') WHERE cArtikel LIKE :artno",
                [
                    'rep'   => ';' . $artNo . ';',
                    'artno' => '%;' . $artNo . ';%'
                ]
            );
            $this->db->query("UPDATE tkupon SET cArtikel = '' WHERE cArtikel = ';'");
        }
    }

    /**
     * @return int[]
     */
    private function addCategoryDiscounts(int $productID): array
    {
        $customerGroups     = $this->db->getInts('SELECT kKundengruppe FROM tkundengruppe', 'kKundengruppe');
        $affectedProductIDs = [];
        $this->db->delete('tartikelkategorierabatt', 'kArtikel', $productID);
        if (\count($customerGroups) === 0) {
            return $affectedProductIDs;
        }
        foreach ($customerGroups as $customerGroupID) {
            $maxDiscount = $this->db->getSingleObject(
                'SELECT tkategoriekundengruppe.fRabatt, tkategoriekundengruppe.kKategorie
                FROM tkategoriekundengruppe
                JOIN tkategorieartikel
                    ON tkategorieartikel.kKategorie = tkategoriekundengruppe.kKategorie
                    AND tkategorieartikel.kArtikel = :kArtikel
                LEFT JOIN tkategoriesichtbarkeit
                    ON tkategoriesichtbarkeit.kKategorie = tkategoriekundengruppe.kKategorie
                    AND tkategoriesichtbarkeit.kKundengruppe = :kKundengruppe
                WHERE tkategoriesichtbarkeit.kKategorie IS NULL
                    AND tkategoriekundengruppe.kKundengruppe = :kKundengruppe
                ORDER BY tkategoriekundengruppe.fRabatt DESC
                LIMIT 1',
                [
                    'kArtikel'      => $productID,
                    'kKundengruppe' => $customerGroupID,
                ]
            );

            if ($maxDiscount !== null && $maxDiscount->fRabatt > 0) {
                $this->db->queryPrepared(
                    'INSERT INTO tartikelkategorierabatt (kArtikel, kKundengruppe, kKategorie, fRabatt)
                        VALUES (:productID, :customerGroup, :categoryID, :discount) ON DUPLICATE KEY UPDATE
                            kKategorie = IF(fRabatt < :discount, :categoryID, kKategorie),
                            fRabatt    = IF(fRabatt < :discount, :discount, fRabatt)',
                    [
                        'productID'     => $productID,
                        'customerGroup' => $customerGroupID,
                        'categoryID'    => $maxDiscount->kKategorie,
                        'discount'      => $maxDiscount->fRabatt,
                    ]
                );
                $affectedProductIDs[] = $productID;
            }
        }

        return $affectedProductIDs;
    }

    /**
     * @return int[]
     */
    private function getConfigParents(int $productID): array
    {
        $configGroupIDs = $this->db->getInts(
            'SELECT kKonfiggruppe
                FROM tkonfigitem 
                WHERE kArtikel = :pid',
            'kKonfiggruppe',
            ['pid' => $productID]
        );
        if (\count($configGroupIDs) === 0) {
            return [];
        }

        return $this->db->getInts(
            'SELECT kArtikel AS id
                FROM tartikelkonfiggruppe
                WHERE kKonfiggruppe IN (' . \implode(',', $configGroupIDs) . ')',
            'id'
        );
    }

    private function flushCategoryTreeCache(): int
    {
        return $this->cache->flushTags(['jtl_category_tree']);
    }

    /**
     * clear all caches associated with a product ID
     * including manufacturers, categories, parent products
     *
     * @param array<int|array{kArtikel: int, kHersteller: int, categories: stdClass[]}> $products
     */
    private function clearProductCaches(array $products): void
    {
        $start     = \microtime(true);
        $cacheTags = new Collection();
        $deps      = new Collection();
        foreach ($products as $product) {
            if (isset($product['kArtikel'])) {
                // generated by bearbeiteDeletes()
                $cacheTags->push(\CACHING_GROUP_ARTICLE . '_' . (int)$product['kArtikel']);
                if ($product['kHersteller'] > 0) {
                    $cacheTags->push(\CACHING_GROUP_MANUFACTURER . '_' . (int)$product['kHersteller']);
                }
                $cacheTags = $cacheTags->concat(
                    map(
                        $product['categories'],
                        fn($item): string => \CACHING_GROUP_CATEGORY . '_' . (int)$item->kKategorie
                    )
                );
            } elseif (\is_numeric($product)) {
                // generated by bearbeiteInsert()
                $cacheTags = $cacheTags->concat(
                    map(
                        $this->getConfigParents($product),
                        fn($item): string => \CACHING_GROUP_ARTICLE . '_' . (int)$item
                    )
                )->push(\CACHING_GROUP_ARTICLE . '_' . (int)$product);
                $deps->push((int)$product);
            }
        }
        // additionally get dependencies for products that were inserted
        if ($deps->count() > 0) {
            $whereIn = $deps->implode(',');
            // flush cache tags associated with the product's manufacturer ID
            $cacheTags = $cacheTags->concat(
                $this->db->getCollection(
                    'SELECT DISTINCT kHersteller AS id
                        FROM tartikel
                        WHERE kArtikel IN (' . $whereIn . ')
                            AND kHersteller > 0'
                )->map(static fn(stdClass $item): string => \CACHING_GROUP_MANUFACTURER . '_' . (int)$item->id)
            )->concat(
                $this->db->getCollection(
                    'SELECT DISTINCT kKategorie AS id
                        FROM tkategorieartikel
                        WHERE kArtikel IN (' . $whereIn . ')'
                )->map(static fn(stdClass $item): string => \CACHING_GROUP_CATEGORY . '_' . (int)$item->id)
            )->concat(
                $this->db->getCollection(
                    'SELECT DISTINCT kVaterArtikel AS id
                        FROM tartikel
                        WHERE kArtikel IN (' . $whereIn . ')
                            AND kVaterArtikel > 0'
                )->map(static fn(stdClass $item): string => \CACHING_GROUP_ARTICLE . '_' . (int)$item->id)
            )->concat(
                $this->db->getCollection(
                    'SELECT DISTINCT kArtikel AS id
                        FROM tartikel
                        WHERE kVaterArtikel IN (' . $whereIn . ')
                            AND kVaterArtikel > 0'
                )->map(static fn(stdClass $item): string => \CACHING_GROUP_ARTICLE . '_' . (int)$item->id)
            );
        }

        $cacheTags->push('jtl_mmf');
        if ($this->affectsSearchSpecials === true) {
            $cacheTags->push('jtl_ssp');
        }
        $cacheTags = $cacheTags->unique();
        // flush product cache, category cache and cache for gibMerkmalFilterOptionen() and mega menu/category boxes
        $totalCount = $this->cache->flushTags($cacheTags->toArray());
        $end        = \microtime(true);
        $this->logger->debug(
            'Flushed a total of {cnt} keys for {tgs} tags in {tme}s',
            [
                'cnt' => $totalCount,
                'tgs' => $cacheTags->count(),
                'tme' => ($end - $start)
            ]
        );
    }
}
