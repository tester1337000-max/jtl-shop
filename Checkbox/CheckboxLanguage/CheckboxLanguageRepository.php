<?php

declare(strict_types=1);

namespace JTL\Checkbox\CheckboxLanguage;

use JTL\Abstracts\AbstractDBRepository;
use JTL\DataObjects\DomainObjectInterface;

/**
 * Class CheckboxLanguageRepository
 * @package JTL\Checkbox\CheckboxLanguage
 */
class CheckboxLanguageRepository extends AbstractDBRepository
{
    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tcheckboxsprache';
    }

    /**
     * @inheritdoc
     */
    public function getKeyName(): string
    {
        return 'kCheckBoxSprache';
    }

    /**
     * @inheritdoc
     */
    public function update(DomainObjectInterface $domainObject, int $ID = 0): bool
    {
        if ($ID === 0) {
            return false;
        }
        $obj = $domainObject->toObjectMapped();
        unset($obj->modifiedKeys);
        $res = $this->getDB()->updateRow(
            $this->getTableName(),
            $this->getKeyName(),
            $ID,
            $obj
        );

        return $res !== self::UPDATE_OR_UPSERT_FAILED;
    }

    /**
     * @return array{cBeschreibung: string, cText: string,
     *      ISO: string, kCheckBox: int, kCheckBoxSprache: int, kSprache: int}[]
     */
    public function getLanguagesByCheckboxID(int $ID): array
    {
        $stmt = 'SELECT 
                cbl.kCheckBoxSprache,
                cbl.kCheckBox,
                cbl.kSprache,
                cbl.cText,
                cbl.cBeschreibung,
                l.cISO ISO
            FROM tcheckboxsprache cbl
            JOIN tsprache l
                ON cbl.kSprache = l.kSprache
            WHERE kCheckbox = :checkboxID';

        return $this->db->getArrays($stmt, ['checkboxID' => $ID]);
    }

    /**
     * @inheritdoc
     */
    public function insert(DomainObjectInterface $domainObject): int
    {
        if (!empty($domainObject->modifiedKeys)) {
            throw new \InvalidArgumentException(
                'DomainObject has been modified. The last modified keys are '
                . \print_r($domainObject->modifiedKeys, true) . '. The DomainObject looks like this: '
                . \print_r($domainObject->toArray(true), true)
            );
        }

        $obj = $domainObject->toObjectMapped();
        foreach ($obj as &$value) {
            if ($value === null) {
                $value = '_DBNULL_';
            }
        }

        return $this->db->insertRow($this->getTableName(), $obj);
    }
}
