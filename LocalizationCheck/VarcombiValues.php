<?php

declare(strict_types=1);

namespace JTL\Backend\LocalizationCheck;

use Illuminate\Support\Collection;

/**
 * Class VarcombiValues
 * @package JTL\Backend\LocalizationCheck
 */
class VarcombiValues extends AbstractLocalizationCheck
{
    /**
     * @inheritdoc
     */
    public function getExcessLocalizations(): Collection
    {
        return $this->db->getCollection(
            'SELECT kEigenschaftWert AS id, kSprache AS langID, cName AS name
                FROM teigenschaftwertsprache
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
                FROM teigenschaftwertsprache
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
                    'SELECT A.kEigenschaftWert AS id, A.cName AS name, :lid AS langID, E.cName AS productName
                        FROM teigenschaftwert A
                        LEFT JOIN teigenschaftwertsprache B
                            ON A.kEigenschaftWert = B.kEigenschaftWert
                            AND B.kSprache = :lid
                        LEFT JOIN tsprache C
                            ON B.kSprache = C.kSprache
                            AND C.kSprache = :lid
                        LEFT JOIN teigenschaft D
                            ON D.kEigenschaft = A.kEigenschaft
                        LEFT JOIN tartikel E
                            ON E.kArtikel = D.kArtikel
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
        return \__('locationVarcombiValues');
    }
}
