<?php

declare(strict_types=1);

namespace JTL\Backend\LocalizationCheck;

use Illuminate\Support\Collection;

/**
 * Class ShippingMethods
 * @package JTL\Backend\LocalizationCheck
 */
class ShippingMethods extends AbstractLocalizationCheck
{
    /**
     * @inheritdoc
     */
    public function getExcessLocalizations(): Collection
    {
        return $this->db->getCollection(
            'SELECT kVersandart AS id, 0 AS langID, cName AS name, cISOSprache AS additional
                FROM tversandartsprache
                WHERE cISOSprache NOT IN (
                    SELECT cISO FROM tsprache WHERE kSprache IN (' . $this->getActiveLanguageIDs()->implode(',') . ')
                )'
        )->mapInto(Item::class);
    }

    /**
     * @inheritdoc
     */
    public function deleteExcessLocalizations(): int
    {
        if ($this->getActiveLanguageCodes()->count() === 0) {
            return 0;
        }
        $codes = $this->getActiveLanguageCodes()->map(fn(string $e): string => "'" . $e . "'")->implode(',');

        return $this->db->getAffectedRows(
            'DELETE
                FROM tversandartsprache
                WHERE cISOSprache NOT IN (' . $codes . ')'
        );
    }

    /**
     * @inheritdoc
     */
    public function getItemsWithoutLocalization(): Collection
    {
        $res = new Collection();
        foreach ($this->getActiveLanguages() as $language) {
            $res = $res->concat(
                $this->db->getCollection(
                    'SELECT A.kVersandart AS id, A.cName AS name, :lid AS langID
                        FROM tversandart A
                        LEFT JOIN tversandartsprache B
                            ON A.kVersandart = B.kVersandart
                            AND B.cISOSprache = :lcd
                        LEFT JOIN tsprache C
                            ON B.cISOSprache = C.cISO
                            AND C.kSprache = :lid
                        WHERE B.cISOSprache IS NULL',
                    ['lid' => $language->getId(), 'lcd' => $language->getCode()]
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
        return \__('locationShippingMethods');
    }
}
