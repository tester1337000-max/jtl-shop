<?php

declare(strict_types=1);

namespace JTL\Backend\LocalizationCheck;

use Illuminate\Support\Collection;

/**
 * Class Varcombi
 * @package JTL\Backend\LocalizationCheck
 */
class Varcombi extends AbstractLocalizationCheck
{
    /**
     * @inheritdoc
     */
    public function getExcessLocalizations(): Collection
    {
        return $this->db->getCollection(
            'SELECT kEigenschaft AS id, kSprache AS langID, cName AS name
                FROM teigenschaftsprache
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
                FROM teigenschaftsprache
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
                    'SELECT A.kEigenschaft AS id, A.cName AS name, D.cName AS productName, :lid AS langID
                        FROM teigenschaft A
                        LEFT JOIN teigenschaftsprache B
                            ON A.kEigenschaft = B.kEigenschaft
                            AND B.kSprache = :lid
                        LEFT JOIN tsprache C
                            ON B.kSprache = C.kSprache
                            AND C.kSprache = :lid
                        LEFT JOIN tartikel D
                            ON A.kArtikel = D.kArtikel
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
        return \__('locationVarcombi');
    }
}
