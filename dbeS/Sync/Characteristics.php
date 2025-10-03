<?php

declare(strict_types=1);

namespace JTL\dbeS\Sync;

use JTL\dbeS\Starter;
use JTL\Helpers\Seo;
use JTL\Language\LanguageHelper;
use stdClass;

/**
 * Class Characteristics
 * @package JTL\dbeS\Sync
 */
final class Characteristics extends AbstractSync
{
    /**
     * @inheritdoc
     */
    public function handle(Starter $starter): void
    {
        foreach ($starter->getXML() as $item) {
            /**
             * @var string       $file
             * @var array<mixed> $xml
             */
            [$file, $xml] = [\key($item), \reset($item)];
            if (\str_contains($file, 'del_merkmal.xml')) {
                $this->handleDeletes($xml);
            } elseif (\str_contains($file, 'merkmal.xml')) {
                $this->handleInserts($xml);
            }
        }
        $this->cache->flushTags([\CACHING_GROUP_ATTRIBUTE, \CACHING_GROUP_FILTER_CHARACTERISTIC]);
    }

    /**
     * @param array<mixed> $xml
     */
    private function handleDeletes(array $xml): void
    {
        // Merkmal
        $characteristics      = $xml['del_merkmale']['kMerkmal'] ?? [];
        $characteristicValues = $xml['del_merkmalwerte']['kMerkmalWert'] ?? [];
        if (!\is_array($characteristics)) {
            $characteristics = [$characteristics];
        }
        foreach (\array_filter($characteristics, '\is_numeric') as $id) {
            $this->delete((int)$id);
        }
        // MerkmalWert - WIRD ZURZEIT NOCH NICHT GENUTZT WEGEN MOEGLICHER INKONSISTENZ
        if (!\is_array($characteristicValues)) {
            $characteristicValues = [$characteristicValues];
        }
        foreach (\array_filter($characteristicValues, '\is_numeric') as $id) {
            $this->deleteCharacteristicValue((int)$id);
        }
    }

    /**
     * @param array<mixed> $xml
     */
    private function handleInserts(array $xml): void
    {
        $defaultLangID = LanguageHelper::getDefaultLanguage()->getId();
        $charValues    = $this->insertCharacteristics($xml, $defaultLangID);
        $this->insertCharacteristicValues($xml, $charValues, $defaultLangID);
    }

    /**
     * @param array<mixed> $xml
     * @param int          $defaultLangID
     * @return stdClass[]
     */
    private function insertCharacteristics(array $xml, int $defaultLangID): array
    {
        $charValues = []; // Merkt sich alle MerkmalWerte die von der Wawi geschickt werden
        if (!isset($xml['merkmale']['tmerkmal']) || !\is_array($xml['merkmale']['tmerkmal'])) {
            return $charValues;
        }
        $characteristics = $this->mapper->mapArray($xml['merkmale'], 'tmerkmal', 'mMerkmal');
        $charCount       = \count($characteristics);
        for ($i = 0; $i < $charCount; $i++) {
            $charValues[$i] = new stdClass();
            if (isset($characteristics[$i]->nMehrfachauswahl)) {
                if ($characteristics[$i]->nMehrfachauswahl > 1) {
                    $characteristics[$i]->nMehrfachauswahl = 1;
                }
            } else {
                $characteristics[$i]->nMehrfachauswahl = 0;
            }
            $characteristic                 = $this->saveImagePath((int)$characteristics[$i]->kMerkmal);
            $characteristics[$i]->cBildpfad = $characteristic->cBildpfad ?? '';
            $charValues[$i]->oMMW_arr       = [];

            if ($charCount < 2) {
                $charData      = $xml['merkmale']['tmerkmal'];
                $charAttribute = $xml['merkmale']['tmerkmal attr'];
            } else {
                $charData      = $xml['merkmale']['tmerkmal'][$i];
                $charAttribute = $xml['merkmale']['tmerkmal'][$i . ' attr'];
            }

            $values = $this->mapper->mapArray(
                $charData,
                'tmerkmalwert',
                'mMerkmalWert'
            );

            if (\count($values) > 0) {
                $this->delete((int)$charAttribute['kMerkmal'], false);
            } else {
                $this->deleteCharacteristicOnly((int)$charAttribute['kMerkmal']);
            }
            $this->upsertXML(
                $charData,
                'tmerkmalsprache',
                'mMerkmalSprache',
                'kMerkmal',
                'kSprache'
            );
            $valueCount = \count($values);
            if ($valueCount === 0) {
                continue;
            }
            for ($o = 0; $o < $valueCount; $o++) {
                $item               = $charValues[$i]->oMMW_arr[$o];
                $item->kMerkmalWert = $values[$o]->kMerkmalWert;
                $item->kSprache_arr = [];

                $source    = \count($values) < 2
                    ? $charData['tmerkmalwert']
                    : $charData['tmerkmalwert'][$o];
                $localized = $this->mapper->mapArray($source, 'tmerkmalwertsprache', 'mMerkmalWertSprache');
                foreach ($localized as $loc) {
                    $loc->kSprache     = (int)$loc->kSprache;
                    $loc->kMerkmalWert = (int)$loc->kMerkmalWert;
                    $this->db->delete(
                        'tseo',
                        ['kKey', 'cKey', 'kSprache'],
                        [
                            $loc->kMerkmalWert,
                            'kMerkmalWert',
                            $loc->kSprache
                        ]
                    );
                    $loc->cSeo = \trim($loc->cSeo)
                        ? Seo::checkSeo(Seo::getSeo($loc->cSeo, true))
                        : Seo::checkSeo(Seo::getSeo(Seo::getFlatSeoPath($loc->cWert)));
                    $this->upsert(
                        'tmerkmalwertsprache',
                        [$loc],
                        'kMerkmalWert',
                        'kSprache'
                    );
                    $ins           = new stdClass();
                    $ins->cSeo     = $loc->cSeo;
                    $ins->cKey     = 'kMerkmalWert';
                    $ins->kKey     = $loc->kMerkmalWert;
                    $ins->kSprache = $loc->kSprache;
                    $this->db->insert('tseo', $ins);

                    if (!\in_array($loc->kSprache, $item->kSprache_arr, true)) {
                        $item->kSprache_arr[] = $loc->kSprache;
                    }

                    if ($loc->kSprache === $defaultLangID) {
                        $item->cNameSTD            = $loc->cWert;
                        $item->cSeoSTD             = $loc->cSeo;
                        $item->cMetaTitleSTD       = $loc->cMetaTitle;
                        $item->cMetaKeywordsSTD    = $loc->cMetaKeywords;
                        $item->cMetaDescriptionSTD = $loc->cMetaDescription;
                        $item->cBeschreibungSTD    = $loc->cBeschreibung;
                    }
                }
                $values[$o]->cBildpfad = $characteristic->oMerkmalWert_arr[$values[$o]->kMerkmalWert];
                $this->upsert('tmerkmalwert', [$values[$o]], 'kMerkmalWert');
                $charValues[$i]->oMMW_arr[$o] = $item;
            }
        }
        $this->upsert('tmerkmal', $characteristics, 'kMerkmal');
        $this->addMissingCharacteristicValueSeo($charValues);
        $this->cache->flushTags([\CACHING_GROUP_ATTRIBUTE]);

        return $charValues;
    }

    /**
     * @param array<mixed> $xml
     * @param array<mixed> $charValues
     * @param int          $defaultLangID
     * @return array<mixed>
     */
    private function insertCharacteristicValues(array $xml, array $charValues, int $defaultLangID): array
    {
        // Kommen nur MerkmalWerte?
        if (!isset($xml['merkmale']['tmerkmalwert']) || !\is_array($xml['merkmale']['tmerkmalwert'])) {
            return [];
        }
        $mapped = $this->mapper->mapArray($xml['merkmale'], 'tmerkmalwert', 'mMerkmalWert');
        $i      = 0;

        if (!isset($charValues[$i])) {
            $charValues[$i] = new stdClass();
        }
        $charValues[$i]->oMMW_arr = [];
        $valueCount               = \count($mapped);
        $allowedLanguageIDs       = \array_keys(LanguageHelper::getAllLanguages(1));
        for ($o = 0; $o < $valueCount; $o++) {
            $id         = (int)$mapped[$o]->kMerkmalWert;
            $oldSeoData = $this->getSeoFromDB($id, 'kMerkmalWert', null, 'kSprache');
            $this->deleteCharacteristicValue($id, true);
            $item               = new stdClass();
            $item->kMerkmalWert = $id;
            $item->kSprache_arr = [];

            $source    = (\count($mapped) < 2)
                ? $xml['merkmale']['tmerkmalwert']
                : $xml['merkmale']['tmerkmalwert'][$o];
            $localized = $this->mapper->mapArray($source, 'tmerkmalwertsprache', 'mMerkmalWertSprache');
            foreach ($localized as $loc) {
                $loc->kSprache     = (int)$loc->kSprache;
                $loc->kMerkmalWert = (int)$loc->kMerkmalWert;
                if (!\in_array($loc->kSprache, $allowedLanguageIDs, true)) {
                    $this->logger->warning(
                        'Language id {id} is not available for characteristic value {cv}',
                        ['id' => $loc->kSprache, 'cv' => $loc->kMerkmalWert]
                    );
                    continue;
                }
                $oldSlug   = $oldSeoData[$loc->kSprache] ?? null;
                $loc->cSeo = \trim($loc->cSeo)
                    ? Seo::checkSeo(Seo::getSeo($loc->cSeo, true))
                    : Seo::checkSeo(Seo::getSeo(Seo::getFlatSeoPath($loc->cWert)));

                if ($loc->cSeo === '') {
                    $this->logger->warning(
                        'Empty SEO string for characteristic value {cv} in language {id}',
                        ['cv' => $loc->kMerkmalWert, 'id' => $loc->kSprache]
                    );
                    continue;
                }
                $this->upsert('tmerkmalwertsprache', [$loc], 'kMerkmalWert', 'kSprache');
                $ins           = new stdClass();
                $ins->cSeo     = $loc->cSeo;
                $ins->cKey     = 'kMerkmalWert';
                $ins->kKey     = $loc->kMerkmalWert;
                $ins->kSprache = $loc->kSprache;
                $this->db->insert('tseo', $ins);
                if ($oldSlug !== null && $oldSlug->cSeo !== $loc->cSeo) {
                    $this->checkDbeSXmlRedirect($oldSlug->cSeo, $loc->cSeo);
                }
                if (!\in_array($loc->kSprache, $item->kSprache_arr, true)) {
                    $item->kSprache_arr[] = $loc->kSprache;
                }

                if ($loc->kSprache === $defaultLangID) {
                    $item->cNameSTD            = $loc->cWert;
                    $item->cSeoSTD             = $loc->cSeo;
                    $item->cMetaTitleSTD       = $loc->cMetaTitle;
                    $item->cMetaKeywordsSTD    = $loc->cMetaKeywords;
                    $item->cMetaDescriptionSTD = $loc->cMetaDescription;
                    $item->cBeschreibungSTD    = $loc->cBeschreibung;
                }
            }
            $image = $this->db->select('tmerkmalwertbild', 'kMerkmalWert', $id);

            $mapped[$o]->cBildpfad = $image->cBildpfad ?? '';
            $this->upsert('tmerkmalwert', [$mapped[$o]], 'kMerkmalWert');
            $charValues[$i]->oMMW_arr[$o] = $item;
        }
        $this->addMissingCharacteristicValueSeo($charValues);

        return $charValues;
    }

    /**
     * Geht $oMMW_arr durch welches vorher mit den mitgeschickten Merkmalwerten gefüllt wurde
     * und füllt die Seo Tabelle in den Sprachen, die nicht von der Wawi mitgeschickt wurden
     *
     * @param stdClass[] $characteristics
     */
    private function addMissingCharacteristicValueSeo(array $characteristics): void
    {
        $languages = $this->db->getInts('SELECT kSprache FROM tsprache ORDER BY kSprache', 'kSprache');
        foreach ($characteristics as $characteristic) {
            foreach ($characteristic->oMMW_arr as $characteristicValue) {
                $characteristicValue->kMerkmalWert = (int)$characteristicValue->kMerkmalWert;
                foreach ($languages as $languageID) {
                    foreach ($characteristicValue->kSprache_arr as $charLanguageID) {
                        // Laufe alle gefüllten Sprachen durch
                        if ($charLanguageID === $languageID) {
                            continue 2;
                        }
                    }
                    // Sprache vom Shop wurde nicht von der Wawi mitgeschickt und muss somit in tseo nachgefüllt werden
                    $slug = Seo::checkSeo(Seo::getSeo($characteristicValue->cNameSTD ?? ''));
                    $this->db->queryPrepared(
                        "DELETE tmerkmalwertsprache, tseo FROM tmerkmalwertsprache
                            LEFT JOIN tseo
                            ON tseo.cKey = 'kMerkmalWert'
                                AND tseo.kKey = :av
                                AND tseo.kSprache = :lid
                            WHERE tmerkmalwertsprache.kMerkmalWert = :av
                                AND tmerkmalwertsprache.kSprache = :lid",
                        ['lid' => $languageID, 'av' => $characteristicValue->kMerkmalWert]
                    );
                    if ($slug !== '') {
                        $seo           = new stdClass();
                        $seo->cSeo     = $slug;
                        $seo->cKey     = 'kMerkmalWert';
                        $seo->kKey     = $characteristicValue->kMerkmalWert;
                        $seo->kSprache = $languageID;
                        $this->db->insert('tseo', $seo);
                        $localized                   = new stdClass();
                        $localized->kMerkmalWert     = $characteristicValue->kMerkmalWert;
                        $localized->kSprache         = $languageID;
                        $localized->cWert            = $characteristicValue->cNameSTD ?? '';
                        $localized->cSeo             = $seo->cSeo ?? '';
                        $localized->cMetaTitle       = $characteristicValue->cMetaTitleSTD ?? '';
                        $localized->cMetaKeywords    = $characteristicValue->cMetaKeywordsSTD ?? '';
                        $localized->cMetaDescription = $characteristicValue->cMetaDescriptionSTD ?? '';
                        $localized->cBeschreibung    = $characteristicValue->cBeschreibungSTD ?? '';
                        $this->db->insert('tmerkmalwertsprache', $localized);
                    }
                }
            }
        }
    }

    private function delete(int $id, bool $update = true): void
    {
        if ($id < 1) {
            return;
        }
        $this->db->queryPrepared(
            "DELETE tseo
                FROM tseo
                INNER JOIN tmerkmalwert
                    ON tmerkmalwert.kMerkmalWert = tseo.kKey
                INNER JOIN tmerkmal
                    ON tmerkmal.kMerkmal = tmerkmalwert.kMerkmal
                WHERE tseo.cKey = 'kMerkmalWert'
                    AND tmerkmal.kMerkmal = :aid",
            ['aid' => $id]
        );
        if ($update === true) {
            $this->db->delete('tartikelmerkmal', 'kMerkmal', $id);
        }
        $this->db->delete('tmerkmal', 'kMerkmal', $id);
        $this->db->delete('tmerkmalsprache', 'kMerkmal', $id);
        foreach ($this->db->selectAll('tmerkmalwert', 'kMerkmal', $id, 'kMerkmalWert') as $value) {
            $this->db->delete('tmerkmalwertsprache', 'kMerkmalWert', (int)$value->kMerkmalWert);
            $this->db->delete('tmerkmalwertbild', 'kMerkmalWert', (int)$value->kMerkmalWert);
        }
        $this->db->delete('tmerkmalwert', 'kMerkmal', $id);
    }

    private function deleteCharacteristicOnly(int $id): void
    {
        if ($id < 1) {
            return;
        }
        $this->db->delete('tmerkmal', 'kMerkmal', $id);
        $this->db->delete('tmerkmalsprache', 'kMerkmal', $id);
    }

    private function deleteCharacteristicValue(int $id, bool $isInsert = false): void
    {
        if ($id < 1) {
            return;
        }
        $this->db->delete('tseo', ['cKey', 'kKey'], ['kMerkmalWert', $id]);
        // Hat das Merkmal vor dem Loeschen noch mehr als einen Wert?
        // Wenn nein => nach dem Loeschen auch das Merkmal loeschen
        $count = $this->db->getSingleObject(
            'SELECT COUNT(*) AS nAnzahl, kMerkmal
                FROM tmerkmalwert
                WHERE kMerkmal = (
                    SELECT kMerkmal
                        FROM tmerkmalwert
                        WHERE kMerkmalWert = :av)',
            ['av' => $id]
        );

        $this->db->queryPrepared(
            'DELETE tmerkmalwert, tmerkmalwertsprache
                FROM tmerkmalwert
                JOIN tmerkmalwertsprache
                    ON tmerkmalwertsprache.kMerkmalWert = tmerkmalwert.kMerkmalWert
                WHERE tmerkmalwert.kMerkmalWert = :av',
            ['av' => $id]
        );
        // Das Merkmal hat keine MerkmalWerte mehr => auch loeschen
        if (!$isInsert && $count !== null && (int)$count->nAnzahl === 1) {
            $this->delete((int)$count->kMerkmal);
        }
    }

    private function saveImagePath(int $characteristicValueID): stdClass
    {
        $characteristic                   = new stdClass();
        $characteristic->oMerkmalWert_arr = [];
        if ($characteristicValueID > 0) {
            $tmp = $this->db->select('tmerkmal', 'kMerkmal', $characteristicValueID);
            if ($tmp !== null && $tmp->kMerkmal > 0) {
                $characteristic->kMerkmal  = $tmp->kMerkmal;
                $characteristic->cBildpfad = $tmp->cBildpfad;
            }
            $characteristicValues = $this->db->selectAll(
                'tmerkmalwert',
                'kMerkmal',
                $characteristicValueID,
                'kMerkmalWert, cBildpfad'
            );
            foreach ($characteristicValues as $value) {
                $characteristic->oMerkmalWert_arr[$value->kMerkmalWert] = $value->cBildpfad;
            }
        }

        return $characteristic;
    }
}
