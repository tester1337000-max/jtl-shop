<?php

declare(strict_types=1);

namespace JTL\dbeS\Sync;

use JTL\dbeS\Starter;
use JTL\Helpers\Seo;
use JTL\Language\LanguageHelper;
use JTL\Language\LanguageModel;
use stdClass;

/**
 * Class Manufacturers
 * @package JTL\dbeS\Sync
 */
final class Manufacturers extends AbstractSync
{
    public function handle(Starter $starter): void
    {
        $cacheTags = [];
        foreach ($starter->getXML() as $item) {
            /**
             * @var string               $file
             * @var array<string, mixed> $xml
             */
            [$file, $xml] = [\key($item), \reset($item)];
            if (\str_contains($file, 'del_hersteller.xml')) {
                $cacheTags[] = $this->handleDeletes($xml);
            } elseif (\str_contains($file, 'hersteller.xml')) {
                $cacheTags[] = $this->handleInserts($xml);
            }
        }
        $this->cache->flushTags($this->flattenTags($cacheTags));
    }

    /**
     * @param array<mixed> $xml
     * @return string[]
     */
    private function handleDeletes(array $xml): array
    {
        $cacheTags = [];
        $source    = $xml['del_hersteller']['kHersteller'] ?? [];
        if (\is_numeric($source)) {
            $source = [$source];
        }
        foreach (\array_filter(\array_map('\intval', $source)) as $manufacturerID) {
            $affectedProducts = $this->db->getInts(
                'SELECT kArtikel
                    FROM tartikel
                    WHERE kHersteller = :mid',
                'kArtikel',
                ['mid' => $manufacturerID]
            );
            $this->db->delete('tseo', ['kKey', 'cKey'], [$manufacturerID, 'kHersteller']);
            $this->db->delete('thersteller', 'kHersteller', $manufacturerID);
            $this->db->delete('therstellersprache', 'kHersteller', $manufacturerID);

            \executeHook(\HOOK_HERSTELLER_XML_BEARBEITEDELETES, ['kHersteller' => $manufacturerID]);
            $cacheTags[] = \CACHING_GROUP_MANUFACTURER . '_' . $manufacturerID;
            foreach ($affectedProducts as $productID) {
                $cacheTags[] = \CACHING_GROUP_ARTICLE . '_' . $productID;
            }
        }

        return $cacheTags;
    }

    /**
     * @param array<mixed> $xml
     * @return string[]
     */
    private function handleInserts(array $xml): array
    {
        $source = $xml['hersteller']['thersteller'] ?? null;
        if (!\is_array($source)) {
            return [];
        }
        $languages     = LanguageHelper::getAllLanguages();
        $manufacturers = $this->mapper->mapArray($xml['hersteller'], 'thersteller', 'mHersteller');
        $mfCount       = \count($manufacturers);
        $cacheTags     = [];
        for ($i = 0; $i < $mfCount; $i++) {
            $id               = (int)$manufacturers[$i]->kHersteller;
            $affectedProducts = $this->db->getInts(
                'SELECT kArtikel
                    FROM tartikel
                    WHERE kHersteller = :mid',
                'kArtikel',
                ['mid' => $id]
            );
            if (!\trim($manufacturers[$i]->cSeo)) {
                $manufacturers[$i]->cSeo = Seo::getSeo(Seo::getFlatSeoPath($manufacturers[$i]->cName));
            } else {
                $manufacturers[$i]->cSeo = Seo::getSeo($manufacturers[$i]->cSeo, true);
            }
            // alten Bildpfad merken
            $manufacturerImage            = $this->db->getSingleObject(
                'SELECT cBildPfad 
                    FROM thersteller 
                    WHERE kHersteller = :mid',
                ['mid' => $id]
            );
            $manufacturers[$i]->cBildPfad = $manufacturerImage->cBildPfad ?? '';
            $this->upsert('thersteller', [$manufacturers[$i]], 'kHersteller');

            $xmlLanguage = [];
            if (isset($source[$i])) {
                $xmlLanguage = $source[$i];
            } elseif (isset($source['therstellersprache'])) {
                $xmlLanguage = $source;
            }
            $newSeo = $this->updateSeo($id, $languages, $xmlLanguage, $manufacturers[$i]->cSeo);
            if ($newSeo !== $manufacturers[$i]->cSeo) {
                $this->db->update(
                    'thersteller',
                    'kHersteller',
                    $id,
                    (object)['cSeo' => $newSeo]
                );
            }
            $this->db->delete('therstellersprache', 'kHersteller', $id);

            $this->upsertXML(
                $xmlLanguage,
                'therstellersprache',
                'mHerstellerSprache',
                'kHersteller',
                'kSprache'
            );

            \executeHook(\HOOK_HERSTELLER_XML_BEARBEITEINSERT, ['oHersteller' => $manufacturers[$i]]);
            $cacheTags[] = \CACHING_GROUP_MANUFACTURER . '_' . $id;
            foreach ($affectedProducts as $productID) {
                $cacheTags[] = \CACHING_GROUP_ARTICLE . '_' . $productID;
            }
        }

        return $cacheTags;
    }

    /**
     * @param LanguageModel[] $languages
     * @param array<mixed>    $xmlLanguage
     */
    private function updateSeo(int $id, array $languages, array $xmlLanguage, string $slug): string
    {
        $oldSeoData = $this->getSeoFromDB($id, 'kHersteller', null, 'kSprache');
        $this->db->delete('tseo', ['kKey', 'cKey'], [$id, 'kHersteller']);
        $mfSeo  = $this->mapper->mapArray($xmlLanguage, 'therstellersprache', 'mHerstellerSpracheSeo');
        $result = $slug;
        foreach ($languages as $language) {
            $baseSeo = $slug;
            foreach ($mfSeo as $mf) {
                if (isset($mf->kSprache) && !empty($mf->cSeo) && (int)$mf->kSprache === $language->getId()) {
                    $baseSeo = Seo::getSeo($mf->cSeo, true);
                    break;
                }
            }
            $seo           = new stdClass();
            $seo->cSeo     = Seo::checkSeo($baseSeo);
            $seo->cKey     = 'kHersteller';
            $seo->kKey     = $id;
            $seo->kSprache = $language->getId();
            $this->db->insert('tseo', $seo);
            $oldSeo = $oldSeoData[$language->getId()] ?? null;
            if ($oldSeo !== null && $oldSeo->cSeo !== $seo->cSeo) {
                $this->checkDbeSXmlRedirect($oldSeo->cSeo, $seo->cSeo);
            }
            if ($language->default === 'Y') {
                $result = $seo->cSeo;
            }
        }

        return $result;
    }
}
