<?php

declare(strict_types=1);

namespace JTL\dbeS\Sync;

use JTL\dbeS\LastJob;
use JTL\dbeS\Starter;
use JTL\Helpers\Seo;
use JTL\Language\LanguageHelper;
use stdClass;

use function Functional\map;

/**
 * Class Categories
 * @package JTL\dbeS\Sync
 */
final class Categories extends AbstractSync
{
    public function handle(Starter $starter): void
    {
        $categoryIDs = [];
        $this->db->query('START TRANSACTION');
        foreach ($starter->getXML() as $item) {
            /**
             * @var string               $file
             * @var array<string, mixed> $xml
             */
            [$file, $xml] = [\key($item), \reset($item)];
            if (isset($xml['tkategorie attr']['nGesamt']) || isset($xml['tkategorie attr']['nAktuell'])) {
                unset($xml['tkategorie attr']['nGesamt'], $xml['tkategorie attr']['nAktuell']);
            }
            if (\str_contains($file, 'katdel.xml')) {
                $this->handleDeletes($xml);
            } elseif (\str_contains($file, 'customer_discount.xml')) {
                $this->handleCustomerDiscount($xml);
            } else {
                $categoryIDs[] = $this->handleInserts($xml);
            }
        }
        $this->cache->flushTags(map($categoryIDs, fn(int $id): string => \CACHING_GROUP_CATEGORY . '_' . $id));
        $lastJob = new LastJob($this->db, $this->logger);
        $lastJob->run(\LASTJOBS_KATEGORIEUPDATE, 'CategoryUpdate');
        $this->db->query('COMMIT');
    }

    /**
     * @param array<mixed> $xml
     */
    private function handleDeletes(array $xml): void
    {
        $source = $xml['del_kategorien']['kKategorie'] ?? null;
        if ($source === null) {
            return;
        }
        if (\is_numeric($source)) {
            $source = [$source];
        }
        if (!\is_array($source)) {
            return;
        }
        foreach (\array_filter(\array_map('\intval', $source)) as $categoryID) {
            $this->deleteCategory($categoryID);
            \executeHook(\HOOK_KATEGORIE_XML_BEARBEITEDELETES, ['kKategorie' => $categoryID]);
        }
    }

    /**
     * @param array<mixed> $xml
     */
    private function handleInserts(array $xml): int
    {
        $category                 = new stdClass();
        $category->kKategorie     = 0;
        $category->kOberKategorie = 0;
        if (\is_array($xml['tkategorie attr'])) {
            $category->kKategorie     = (int)$xml['tkategorie attr']['kKategorie'];
            $category->kOberKategorie = (int)$xml['tkategorie attr']['kOberKategorie'];
        }
        if (!$category->kKategorie) {
            $this->logger->error('kKategorie fehlt! XML: ' . \print_r($xml, true));

            return 0;
        }
        if (!\is_array($xml['tkategorie'])) {
            return 0;
        }
        // Altes SEO merken => falls sich es bei der aktualisierten Kategorie ändert => Eintrag in tredirect
        $oldData    = $this->db->getSingleObject(
            'SELECT cSeo, lft, rght, nLevel
                FROM tkategorie
                WHERE kKategorie = :categoryID',
            ['categoryID' => $category->kKategorie]
        );
        $oldSeoData = $this->getSeoFromDB($category->kKategorie, 'kKategorie', null, 'kSprache');
        $this->db->delete('tseo', ['kKey', 'cKey'], [$category->kKategorie, 'kKategorie']);
        $categories = $this->mapper->mapArray($xml, 'tkategorie', 'mKategorie');
        if ($categories[0]->kKategorie > 0) {
            if (!$categories[0]->cSeo) {
                $categories[0]->cSeo = Seo::checkSeo(Seo::getSeo(Seo::getFlatSeoPath($categories[0]->cName)));
            } else {
                $categories[0]->cSeo = Seo::checkSeo(Seo::getSeo($categories[0]->cSeo, true));
            }
            $categories[0]->dLetzteAktualisierung = 'NOW()';
            $categories[0]->lft                   = $oldData->lft ?? 0;
            $categories[0]->rght                  = $oldData->rght ?? 0;
            $categories[0]->nLevel                = $oldData->nLevel ?? 0;
            $this->insertOnExistUpdate('tkategorie', $categories, ['kKategorie']);
            if ($oldData !== null) {
                $this->checkDbeSXmlRedirect($oldData->cSeo, $categories[0]->cSeo);
            }
            $this->db->queryPrepared(
                "INSERT IGNORE INTO tseo
                    SELECT tkategorie.cSeo, 'kKategorie', tkategorie.kKategorie, tsprache.kSprache
                        FROM tkategorie, tsprache
                        WHERE tkategorie.kKategorie = :categoryID
                            AND tsprache.cStandard = 'Y'
                            AND tkategorie.cSeo != ''
                    ON DUPLICATE KEY UPDATE cSeo = (SELECT tkategorie.cSeo
                        FROM tkategorie, tsprache
                        WHERE tkategorie.kKategorie = :categoryID
                            AND tsprache.cStandard = 'Y'
                            AND tkategorie.cSeo != '')",
                ['categoryID' => (int)$categories[0]->kKategorie]
            );
            \executeHook(\HOOK_KATEGORIE_XML_BEARBEITEINSERT, ['oKategorie' => $categories[0]]);
        }
        $this->setLanguages($xml, $category->kKategorie, $categories[0], $oldSeoData);
        $this->setCustomerGroups($xml, $category->kKategorie);
        $this->setCategoryDiscount($category->kKategorie);
        foreach ($this->getLinkedDiscountCategories($category->kKategorie) as $linkedCategory) {
            $this->setCategoryDiscount((int)$linkedCategory->kKategorie);
        }
        $this->setVisibility($xml, $category->kKategorie);
        $this->setAttributes($xml, $category->kKategorie);

        return $category->kKategorie;
    }

    /**
     * @param array<mixed> $xml
     */
    private function handleCustomerDiscount(array $xml): void
    {
        $catID = (int)($xml['tkategorie attr']['kKategorie'] ?? 0);
        if ($catID <= 0) {
            $this->logger->error('kKategorie fehlt! XML: ' . \print_r($xml, true));

            return;
        }

        $customerDiscounts = $this->mapper->mapArray(
            $xml['tkategorie'],
            'category_customerdiscount',
            'mCustomerDiscount'
        );
        foreach ($customerDiscounts as $customerDiscount) {
            if (isset($customerDiscount->delete) || (float)$customerDiscount->discount === 0.0) {
                $this->deleteCustomerDiscount($catID, (int)$customerDiscount->kKunde);
            } else {
                $this->saveCustomerDiscount($catID, (int)$customerDiscount->kKunde, (float)$customerDiscount->discount);
            }
        }
    }

    /**
     * @param array<mixed>             $xml
     * @param int                      $categoryID
     * @param stdClass                 $category
     * @param stdClass[]|stdClass|null $oldSeoData
     */
    private function setLanguages(
        array $xml,
        int $categoryID,
        stdClass $category,
        array|stdClass|null $oldSeoData = null
    ): void {
        $seoData      = $oldSeoData ?? $this->getSeoFromDB($categoryID, 'kKategorie', null, 'kSprache');
        $catLanguages = $this->mapper->mapArray($xml['tkategorie'], 'tkategoriesprache', 'mKategorieSprache');
        $langIDs      = [];
        $allLanguages = LanguageHelper::getAllLanguages(1);
        foreach ($catLanguages as $language) {
            $langID = (int)$language->kSprache;
            // Sprachen die nicht im Shop vorhanden sind überspringen
            if (!LanguageHelper::isShopLanguage($langID, $allLanguages)) {
                continue;
            }
            if ($language->cSeo) {
                $language->cSeo = Seo::checkSeo(Seo::getSeo($language->cSeo, true));
            } else {
                $language->cSeo = $language->cName;
                if (!$language->cSeo) {
                    $language->cSeo = $category->cSeo;
                }
                if (!$language->cSeo) {
                    $language->cSeo = $category->cName;
                }
                $language->cSeo = Seo::checkSeo(Seo::getSeo($language->cSeo));
            }
            $this->insertOnExistUpdate('tkategoriesprache', [$language], ['kKategorie', 'kSprache']);

            $ins           = new stdClass();
            $ins->cSeo     = $language->cSeo;
            $ins->cKey     = 'kKategorie';
            $ins->kKey     = $language->kKategorie;
            $ins->kSprache = $langID;
            $this->db->upsert('tseo', $ins);
            if (\is_array($seoData) && isset($seoData[$langID])) {
                $this->checkDbeSXmlRedirect($seoData[$langID]->cSeo, $language->cSeo);
            }
            $langIDs[] = $langID;
        }
        $this->deleteByKey('tkategoriesprache', ['kKategorie' => $categoryID], 'kSprache', $langIDs);
    }

    /**
     * @param array<mixed> $xml
     */
    private function setCustomerGroups(array $xml, int $categoryID): void
    {
        $pkValues = $this->insertOnExistsUpdateXMLinDB(
            $xml['tkategorie'],
            'tkategoriekundengruppe',
            'mKategorieKundengruppe',
            ['kKategorie', 'kKundengruppe']
        );
        $this->deleteByKey(
            'tkategoriekundengruppe',
            ['kKategorie' => $categoryID],
            'kKundengruppe',
            $pkValues['kKundengruppe']
        );
    }

    /**
     * @param array<mixed> $xml
     */
    private function setAttributes(array $xml, int $categoryID): void
    {
        // Wawi sends category attributes in tkategorieattribut (function attributes)
        // and tattribut (localized attributes) nodes
        $pkValues   = $this->insertOnExistsUpdateXMLinDB(
            $xml['tkategorie'],
            'tkategorieattribut',
            'mKategorieAttribut',
            ['kKategorieAttribut']
        );
        $attributes = $this->mapper->mapArray($xml['tkategorie'], 'tattribut', 'mNormalKategorieAttribut');
        $attribPKs  = $pkValues['kKategorieAttribut'];
        if (\count($attributes) > 0) {
            $single = isset($xml['tkategorie']['tattribut attr']) && \is_array($xml['tkategorie']['tattribut attr']);
            $i      = 0;
            foreach ($attributes as $attribute) {
                $attribPKs[] = $this->saveAttribute(
                    $single ? $xml['tkategorie']['tattribut'] : $xml['tkategorie']['tattribut'][$i++],
                    $attribute
                );
            }
        }
        $this->db->queryPrepared(
            'DELETE tkategorieattribut, tkategorieattributsprache
                FROM tkategorieattribut
                LEFT JOIN tkategorieattributsprache
                    ON tkategorieattributsprache.kAttribut = tkategorieattribut.kKategorieAttribut
                WHERE tkategorieattribut.kKategorie = :categoryID' . (\count($attribPKs) > 0 ? '
                    AND tkategorieattribut.kKategorieAttribut NOT IN (' . \implode(', ', $attribPKs) . ')' : ''),
            ['categoryID' => $categoryID]
        );
    }

    /**
     * @param array<mixed> $xml
     */
    private function setVisibility(array $xml, int $categoryID): void
    {
        $pkValues = $this->insertOnExistsUpdateXMLinDB(
            $xml['tkategorie'],
            'tkategoriesichtbarkeit',
            'mKategorieSichtbarkeit',
            ['kKundengruppe', 'kKategorie']
        );
        $this->deleteByKey(
            'tkategoriesichtbarkeit',
            ['kKategorie' => $categoryID],
            'kKundengruppe',
            $pkValues['kKundengruppe']
        );
    }

    private function deleteCategory(int $id): void
    {
        $this->db->queryPrepared(
            'DELETE tkategorieattribut, tkategorieattributsprache
                FROM tkategorieattribut
                LEFT JOIN tkategorieattributsprache
                    ON tkategorieattributsprache.kAttribut = tkategorieattribut.kKategorieAttribut
                WHERE tkategorieattribut.kKategorie = :categoryID',
            ['categoryID' => $id]
        );
        $this->db->delete('tseo', ['kKey', 'cKey'], [$id, 'kKategorie']);
        $this->db->delete('tkategorie', 'kKategorie', $id);
        $this->db->delete('tkategoriekundengruppe', 'kKategorie', $id);
        $this->db->delete('tkategoriesichtbarkeit', 'kKategorie', $id);
        $this->db->delete('tkategorieartikel', 'kKategorie', $id);
        $this->db->delete('tkategoriesprache', 'kKategorie', $id);
        $this->db->delete('tartikelkategorierabatt', 'kKategorie', $id);
    }

    /**
     * @param array<mixed> $xmlParent
     */
    private function saveAttribute(array $xmlParent, stdClass $attribute): int
    {
        // Fix: die Wawi überträgt für die normalen Attribute die ID in kAttribut statt in kKategorieAttribut
        if (!isset($attribute->kKategorieAttribut) && isset($attribute->kAttribut)) {
            $attribute->kKategorieAttribut = $attribute->kAttribut;
            unset($attribute->kAttribut);
        }
        $attribute->kKategorieAttribut = (int)$attribute->kKategorieAttribut;
        $this->insertOnExistUpdate('tkategorieattribut', [$attribute], ['kKategorieAttribut', 'kKategorie']);
        $localized = $this->mapper->mapArray($xmlParent, 'tattributsprache', 'mKategorieAttributSprache');
        // Die Standardsprache wird nicht separat übertragen und wird deshalb aus den Attributwerten gesetzt
        \array_unshift(
            $localized,
            (object)[
                'kAttribut' => $attribute->kKategorieAttribut,
                'kSprache'  => $this->db->select('tsprache', 'cShopStandard', 'Y')->kSprache ?? 0,
                'cName'     => $attribute->cName,
                'cWert'     => $attribute->cWert,
            ]
        );
        $this->upsert('tkategorieattributsprache', $localized, 'kAttribut', 'kSprache');
        $pkValues = $this->insertOnExistUpdate('tkategorieattributsprache', $localized, ['kAttribut', 'kSprache']);
        $this->deleteByKey(
            'tkategorieattributsprache',
            ['kAttribut' => $attribute->kKategorieAttribut],
            'kSprache',
            $pkValues['kSprache']
        );

        return $attribute->kKategorieAttribut;
    }

    private function setCategoryDiscount(int $categoryID): void
    {
        $this->db->delete('tartikelkategorierabatt', 'kKategorie', $categoryID);
        $this->db->queryPrepared(
            'INSERT INTO tartikelkategorierabatt SELECT * FROM (
                SELECT tkategorieartikel.kArtikel, tkategoriekundengruppe.kKundengruppe, tkategorieartikel.kKategorie,
                       MAX(tkategoriekundengruppe.fRabatt) fRabatt
                FROM tkategoriekundengruppe
                INNER JOIN tkategorieartikel
                    ON tkategorieartikel.kKategorie = tkategoriekundengruppe.kKategorie
                LEFT JOIN tkategoriesichtbarkeit
                    ON tkategoriesichtbarkeit.kKategorie = tkategoriekundengruppe.kKategorie
                    AND tkategoriesichtbarkeit.kKundengruppe = tkategoriekundengruppe.kKundengruppe
                WHERE tkategoriekundengruppe.kKategorie = :categoryID
                    AND tkategoriesichtbarkeit.kKategorie IS NULL
                GROUP BY tkategorieartikel.kArtikel, tkategoriekundengruppe.kKundengruppe, tkategorieartikel.kKategorie
                HAVING MAX(tkategoriekundengruppe.fRabatt) > 0) AS tNew ON DUPLICATE KEY UPDATE
                    kKategorie = IF(tartikelkategorierabatt.fRabatt < tNew.fRabatt,
                        tNew.kKategorie,
                        tartikelkategorierabatt.kKategorie),
                    fRabatt    = IF(tartikelkategorierabatt.fRabatt < tNew.fRabatt,
                        tNew.fRabatt,
                        tartikelkategorierabatt.fRabatt)',
            ['categoryID' => $categoryID]
        );
    }

    /**
     * @return stdClass[]
     */
    private function getLinkedDiscountCategories(int $categoryID): array
    {
        return $this->db->getObjects(
            'SELECT DISTINCT tkgrp_b.kKategorie
                FROM tkategorieartikel tart_a
                INNER JOIN tkategorieartikel tart_b ON tart_a.kArtikel = tart_b.kArtikel
                    AND tart_a.kKategorie != tart_b.kKategorie
                INNER JOIN tkategoriekundengruppe tkgrp_b ON tart_b.kKategorie = tkgrp_b.kKategorie
                LEFT JOIN tkategoriekundengruppe tkgrp_a ON tkgrp_a.kKategorie = tart_a.kKategorie
                LEFT JOIN tkategoriesichtbarkeit tsicht ON tsicht.kKategorie = tkgrp_b.kKategorie
                    AND tsicht.kKundengruppe = tkgrp_b.kKundengruppe
                WHERE tart_a.kKategorie = :categoryID
                    AND tkgrp_b.fRabatt > COALESCE(tkgrp_a.fRabatt, 0)
                    AND tsicht.kKategorie IS NULL',
            ['categoryID' => $categoryID]
        );
    }

    private function deleteCustomerDiscount(int $categoryId, int $customerId): void
    {
        $this->db->delete('category_customerdiscount', ['categoryId', 'customerId'], [$categoryId, $customerId]);
    }

    private function saveCustomerDiscount(int $categoryId, int $customerId, float $discount): void
    {
        $this->db->upsert(
            'category_customerdiscount',
            (object)[
                'categoryId' => $categoryId,
                'customerId' => $customerId,
                'discount'   => $discount,
            ],
            [
                'categoryId',
                'customerId'
            ]
        );
    }
}
