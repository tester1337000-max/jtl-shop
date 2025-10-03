<?php

declare(strict_types=1);

namespace JTL\Backend\LocalizationCheck;

use Illuminate\Support\Collection;

/**
 * Class Products
 * @package JTL\Backend\LocalizationCheck
 */
class Products extends AbstractLocalizationCheck
{
    /**
     * @inheritdoc
     */
    public function getExcessLocalizations(): Collection
    {
        return $this->db->getCollection(
            'SELECT kArtikel AS id, cName AS name, kSprache AS langID
                FROM tartikelsprache
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
                FROM tartikelsprache
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
                    'SELECT A.kArtikel AS id, A.cName AS name, :lid AS langID
                        FROM tartikel A
                        LEFT JOIN tartikelsprache B
                            ON A.kArtikel = B.kArtikel
                        LEFT JOIN tsprache C
                            ON B.kSprache = C.kSprache
                            AND C.kSprache = :lid
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
        return \__('locationProducts');
    }
}
