<?php

declare(strict_types=1);

namespace JTL\Backend\LocalizationCheck;

use Illuminate\Support\Collection;

/**
 * Class Uploads
 * @package JTL\Backend\LocalizationCheck
 */
class Uploads extends AbstractLocalizationCheck
{
    /**
     * @inheritdoc
     */
    public function getExcessLocalizations(): Collection
    {
        return $this->db->getCollection(
            'SELECT kArtikelUpload AS id, kSprache AS langID, cName AS name
                FROM tuploadschemasprache
                WHERE kSprache NOT IN (' . $this->getActiveLanguageIDs()->implode(',') . ')'
        )->mapInto(Item::class);
    }

    /**
     * @inheritdoc
     */
    public function deleteExcessLocalizations(): int
    {
        if ($this->getActiveLanguageIDs()->count() === 0) {
            return 0;
        }

        return $this->db->getAffectedRows(
            'DELETE
                FROM tuploadschemasprache
                WHERE kSprache NOT IN (' . $this->getActiveLanguageIDs()->implode(',') . ')'
        );
    }

    /**
     * @inheritdoc
     */
    public function getItemsWithoutLocalization(): Collection
    {
        $res = new Collection();
        foreach ($this->getNonDefaultLanguages() as $language) {
            $res = $res->concat(
                $this->db->getCollection(
                    'SELECT A.kUploadSchema AS id, A.cName AS name, :lid AS langID, D.cName AS productName
                        FROM tuploadschema A
                        LEFT JOIN tuploadschemasprache B
                            ON A.kUploadSchema = B.kArtikelUpload
                            AND B.kSprache = :lid
                        LEFT JOIN tsprache C
                            ON B.kSprache = C.kSprache
                            AND C.kSprache = :lid
                        LEFT JOIN tartikel D
                            ON A.kCustomID = D.kArtikel
                        WHERE B.kSprache IS NULL',
                    ['lid' => $language->getId()]
                )->mapInto(Item::class)
            );
        }

        return $res;
    }

    /**
     * @inheritdoc
     */
    public function getLocation(): string
    {
        return \__('locationUploads');
    }
}
