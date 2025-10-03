<?php

declare(strict_types=1);

namespace JTL\Checkbox;

use JTL\Abstracts\AbstractDBRepository;
use JTL\CheckBox;
use JTL\DataObjects\DomainObjectInterface;
use stdClass;

/**
 * Class CheckboxRepository
 * @package JTL\Checkbox
 */
class CheckboxRepository extends AbstractDBRepository
{
    public function get(int $id): ?stdClass
    {
        return $this->getDB()->getSingleObject(
            "SELECT *, DATE_FORMAT(dErstellt, '%d.%m.%Y %H:%i:%s') AS dErstellt_DE"
            . ' FROM ' . $this->getTableName()
            . ' WHERE ' . $this->getKeyName() . ' = :cbID',
            ['cbID' => $id]
        );
    }

    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tcheckbox';
    }

    /**
     * @inheritdoc
     */
    public function getKeyName(): string
    {
        return 'kCheckBox';
    }

    /**
     * @param int[] $checkboxIDs
     * @return bool
     */
    public function activate(array $checkboxIDs): bool
    {
        if (\count($checkboxIDs) === 0) {
            return false;
        }
        $this->getDB()->query(
            'UPDATE ' . $this->getTableName()
            . ' SET nAktiv = 1'
            . ' WHERE ' . $this->getKeyName() . ' IN ('
            . \implode(',', $this->ensureIntValuesInArray($checkboxIDs)) . ')'
        );

        return true;
    }

    /**
     * @param int[] $checkboxIDs
     * @return bool
     */
    public function deactivate(array $checkboxIDs): bool
    {
        if (\count($checkboxIDs) === 0) {
            return false;
        }
        $this->getDB()->query(
            'UPDATE ' . $this->getTableName()
            . ' SET nAktiv = 0'
            . ' WHERE ' . $this->getKeyName() . ' IN (' .
            \implode(',', $this->ensureIntValuesInArray($checkboxIDs)) . ')'
        );

        return true;
    }

    public function update(DomainObjectInterface $domainObject): bool
    {
        if (!\method_exists($domainObject, 'getCheckBoxID') || !\method_exists($domainObject, 'toObjectMapped')) {
            return false;
        }

        if (\property_exists($domainObject, 'modifiedKeys') && \count($domainObject->modifiedKeys) > 0) {
            throw new \InvalidArgumentException(
                'DomainObject has been modified. The modified keys are '
                . \print_r($domainObject->modifiedKeys, true) . '. The DomainObject looks like this: '
                . \print_r($domainObject->toArray(true), true)
            );
        }
        $res = $this->db->updateRow(
            $this->getTableName(),
            $this->getKeyName(),
            $domainObject->getCheckBoxID(),
            $domainObject->toObjectMapped()
        );

        return $res !== self::UPDATE_OR_UPSERT_FAILED;
    }

    public function updateFunctionID(int $checkboxID, int $functionId): bool
    {
        $res = $this->db->updateRow(
            $this->getTableName(),
            $this->getKeyName(),
            $checkboxID,
            (object)['kCheckBoxFunktion' => $functionId]
        );

        return $res !== self::UPDATE_OR_UPSERT_FAILED;
    }

    /**
     * @param int[]|numeric-string[] $values
     * @return bool
     */
    public function deleteByIDs(array $values): bool
    {
        if (\count($values) === 0) {
            return false;
        }
        $this->db->query(
            'DELETE tcheckbox, tcheckboxsprache
                FROM tcheckbox
                LEFT JOIN tcheckboxsprache
                    ON tcheckboxsprache.kCheckBox = tcheckbox.kCheckBox
                WHERE tcheckbox.kCheckBox IN (' . \implode(',', \array_map('\intval', $values)) . ')' .
            ' AND nInternal = 0'
        );

        return true;
    }

    /**
     * Since Hook expects an array of CheckBox-objects....
     * @param CheckboxValidationDomainObject $data
     * @return CheckBox[]
     */
    public function getCheckBoxValidationData(CheckboxValidationDomainObject $data): array
    {
        $sql = '';
        if ($data->getActive() === true) {
            $sql .= ' AND nAktiv = 1';
        }
        if ($data->getSpecial() === true) {
            $sql .= ' AND kCheckBoxFunktion > 0';
        }
        if ($data->getLogging() === true) {
            $sql .= ' AND nLogging = 1';
        }

        return $this->db->getCollection(
            "SELECT kCheckBox AS id
                FROM tcheckbox
                WHERE FIND_IN_SET('" . $data->getLocation() . "', REPLACE(cAnzeigeOrt, ';', ',')) > 0
                    AND FIND_IN_SET('" . $data->getCustomerGroupId() . "', REPLACE(cKundengruppe, ';', ',')) > 0
                    " . $sql . '
                ORDER BY nSort'
        )->map(fn(stdClass $e): CheckBox => new CheckBox((int)$e->id, $this->db))->all();
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
        foreach (\get_object_vars($obj) as $key => $value) {
            if ($value === null) {
                $obj->{$key} = '_DBNULL_';
            }
        }

        return $this->db->insertRow($this->getTableName(), $obj);
    }
}
