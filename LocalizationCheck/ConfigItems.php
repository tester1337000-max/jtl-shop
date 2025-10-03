<?php

declare(strict_types=1);

namespace JTL\Backend\LocalizationCheck;

use Illuminate\Support\Collection;

/**
 * Class ConfigItems
 * @package JTL\Backend\LocalizationCheck
 */
class ConfigItems extends AbstractLocalizationCheck
{
    /**
     * @inheritdoc
     */
    public function getExcessLocalizations(): Collection
    {
        return $this->db->getCollection(
            'SELECT A.kKonfigitem AS id, A.kSprache AS langID, A.cName AS name
                FROM tkonfigitemsprache A
                JOIN tkonfigitem
                    ON tkonfigitem.kKonfigitem = A.kKonfigitem
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
                FROM tkonfigitemsprache
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
                    'SELECT A.kKonfigitem AS id, CONCAT(\'kKonfigitem \', A.kKonfigitem) AS name, :lid AS langID
                        FROM tkonfigitem A
                        LEFT JOIN tkonfigitemsprache B
                            ON A.kKonfigitem = B.kKonfigitem
                            AND B.kSprache = :lid
                        LEFT JOIN tsprache C
                            ON B.kSprache = C.kSprache
                            AND C.kSprache = :lid
                        WHERE A.bName = 0 AND B.kSprache IS NULL',
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
        return \__('locationConfigItems');
    }
}
