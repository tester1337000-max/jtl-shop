<?php

declare(strict_types=1);

namespace JTL\Backend\LocalizationCheck;

use Illuminate\Support\Collection;

/**
 * Class Warehouses
 * @package JTL\Backend\LocalizationCheck
 */
class Warehouses extends AbstractLocalizationCheck
{
    /**
     * @inheritdoc
     */
    public function getExcessLocalizations(): Collection
    {
        return $this->db->getCollection(
            'SELECT kWarenlager AS id, kSprache AS langID, cName AS name
                FROM twarenlagersprache
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
                FROM twarenlagersprache
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
                    'SELECT A.kWarenlager AS id, A.cName AS name, :lid AS langID
                        FROM twarenlager A
                        LEFT JOIN twarenlagersprache B
                            ON A.kWarenlager = B.kWarenlager
                            AND B.kSprache = :lid
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
        return \__('locationWarehouses');
    }
}
