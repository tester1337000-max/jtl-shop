<?php

declare(strict_types=1);

namespace JTL\Backend;

use JTL\DB\DbInterface;
use JTL\Helpers\GeneralObject;
use JTL\Shop;
use stdClass;

/**
 * Class CustomerFields
 * @package JTL\Backend
 * @phpstan-consistent-constructor
 */
class CustomerFields
{
    /**
     * @var static[]
     */
    private static array $instances = [];

    /**
     * @var stdClass[]
     */
    protected array $customerFields = [];

    private DbInterface $db;

    public function __construct(protected int $langID, ?DbInterface $db = null)
    {
        $this->db = $db ?? Shop::Container()->getDB();
        $this->loadFields($langID);
    }

    public static function getInstance(?int $langID = null, ?DbInterface $db = null): self
    {
        if ($langID === null || $langID === 0) {
            $langID = (int)$_SESSION['kSprache'];
        }

        if (!isset(self::$instances[$langID])) {
            self::$instances[$langID] = new static($langID, $db);
        }

        return self::$instances[$langID];
    }

    protected function loadFields(int $langID): void
    {
        $this->customerFields = $this->db->getCollection(
            'SELECT * FROM tkundenfeld
                WHERE kSprache = :lid
                ORDER BY nSort ASC',
            ['lid' => $langID]
        )->map($this->prepare(...))->keyBy('kKundenfeld')->toArray();
    }

    public function prepare(stdClass $customerField): stdClass
    {
        $customerField->kKundenfeld = (int)$customerField->kKundenfeld;
        $customerField->kSprache    = (int)$customerField->kSprache;
        $customerField->nSort       = (int)$customerField->nSort;
        $customerField->nPflicht    = (int)$customerField->nPflicht > 0 ? 1 : 0;
        $customerField->nEditierbar = (int)$customerField->nEditierbar > 0 ? 1 : 0;

        return $customerField;
    }

    /**
     * @return stdClass[]
     */
    public function getCustomerFields(): array
    {
        return GeneralObject::deepCopy($this->customerFields);
    }

    public function getCustomerField(int $kCustomerField): ?stdClass
    {
        return $this->customerFields[$kCustomerField] ?? null;
    }

    /**
     * @param stdClass $customerField
     * @return null|stdClass[]
     */
    public function getCustomerFieldValues(stdClass $customerField): ?array
    {
        $this->prepare($customerField);

        if ($customerField->cTyp === 'auswahl') {
            return $this->db->selectAll(
                'tkundenfeldwert',
                'kKundenfeld',
                $customerField->kKundenfeld,
                '*',
                'nSort, kKundenfeldWert ASC'
            );
        }

        return null;
    }

    public function delete(int $fieldID): bool
    {
        if ($fieldID === 0) {
            return false;
        }
        $ret = $this->db->delete('tkundenattribut', 'kKundenfeld', $fieldID) >= 0
            && $this->db->delete('tkundenfeldwert', 'kKundenfeld', $fieldID) >= 0
            && $this->db->delete('tkundenfeld', 'kKundenfeld', $fieldID) >= 0;

        if ($ret) {
            unset($this->customerFields[$fieldID]);
        } else {
            $this->loadFields($this->langID);
        }

        return $ret;
    }

    /**
     * @param array<string, mixed> $customerFieldValues
     */
    protected function updateCustomerFieldValues(int $customerFieldID, array $customerFieldValues): void
    {
        $this->db->delete('tkundenfeldwert', 'kKundenfeld', $customerFieldID);

        foreach ($customerFieldValues as $customerFieldValue) {
            $entity              = new stdClass();
            $entity->kKundenfeld = $customerFieldID;
            $entity->cWert       = $customerFieldValue['cWert'];
            $entity->nSort       = (int)$customerFieldValue['nSort'];

            $this->db->insert('tkundenfeldwert', $entity);
        }

        // Delete all customer values that are not in value list
        $this->db->queryPrepared(
            "DELETE tkundenattribut
                FROM tkundenattribut
                INNER JOIN tkundenfeld ON tkundenfeld.kKundenfeld = tkundenattribut.kKundenfeld
                WHERE tkundenfeld.cTyp = 'auswahl'
                    AND tkundenfeld.kKundenfeld = :kKundenfeld
                    AND NOT EXISTS (
                        SELECT 1
                        FROM tkundenfeldwert
                        WHERE tkundenfeldwert.kKundenfeld = tkundenattribut.kKundenfeld
                            AND tkundenfeldwert.cWert = tkundenattribut.cWert
                        )",
            ['kKundenfeld' => $customerFieldID]
        );
    }

    /**
     * @param null|array<mixed> $customerFieldValues
     */
    public function save(stdClass $customerField, ?array $customerFieldValues = null): bool
    {
        $this->prepare($customerField);
        $key = $customerField->kKundenfeld ?? null;
        $ret = false;

        if ($key !== null && isset($this->customerFields[$key])) {
            // update...
            $oldType                    = $this->customerFields[$key]->cTyp;
            $this->customerFields[$key] = clone $customerField;
            // this entities are not changeable
            unset($customerField->kKundenfeld, $customerField->kSprache, $customerField->cWawi);

            $ret = $this->db->update('tkundenfeld', 'kKundenfeld', $key, $customerField) >= 0;

            if ($oldType !== $customerField->cTyp) {
                // cTyp has been changed
                if ($oldType === 'auswahl') {
                    // cTyp changed from "auswahl" to something else - delete values for the customer field
                    $this->db->delete('tkundenfeldwert', 'kKundenfeld', $key);
                }
                switch ($customerField->cTyp) {
                    case 'zahl':
                        // all customer values will be changed to numbers if possible
                        $this->db->queryPrepared(
                            'UPDATE tkundenattribut SET
                                cWert =	CAST(CAST(cWert AS DOUBLE) AS CHAR)
                                WHERE tkundenattribut.kKundenfeld = :kKundenfeld',
                            ['kKundenfeld' => $key]
                        );
                        break;
                    case 'datum':
                        // all customer values will be changed to date if possible
                        $this->db->queryPrepared(
                            "UPDATE tkundenattribut SET
                                cWert =	DATE_FORMAT(STR_TO_DATE(cWert, '%d.%m.%Y'), '%d.%m.%Y')
                                WHERE tkundenattribut.kKundenfeld = :kKundenfeld",
                            ['kKundenfeld' => $key]
                        );
                        break;
                    case 'text':
                    default:
                        // changed to text - nothing to do...
                        break;
                }
            }
        } else {
            $key = $this->db->insert('tkundenfeld', $customerField);

            if ($key > 0) {
                $customerField->kKundenfeld = $key;
                $this->customerFields[$key] = $customerField;

                $ret = true;
            }
        }

        if ($ret) {
            if ($customerField->cTyp === 'auswahl' && \is_array($customerFieldValues)) {
                $this->updateCustomerFieldValues($key, $customerFieldValues);
            }
        } else {
            $this->loadFields($this->langID);
        }

        return $ret;
    }

    public function getLangID(): int
    {
        return $this->langID;
    }

    public function setLangID(int $langID): void
    {
        $this->langID = $langID;
    }

    public function getDB(): ?DbInterface
    {
        return $this->db;
    }

    public function setDB(DbInterface $db): void
    {
        $this->db = $db;
    }
}
