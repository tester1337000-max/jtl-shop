<?php

declare(strict_types=1);

namespace JTL\Customer;

use JTL\MagicCompatibilityTrait;
use JTL\Shop;
use stdClass;

/**
 * Class CustomerAttribute
 * @package JTL\Customer
 */
class CustomerAttribute
{
    use MagicCompatibilityTrait;

    private int $id = 0;

    private int $customerID = 0;

    private int $customerFieldID = 0;

    private string $label = '';

    private string $name = '';

    private ?string $value = '';

    private int $order = 0;

    private bool $editable = true;

    /**
     * @var array<string, string>
     */
    public static array $mapping = [
        'kKundenAttribut' => 'ID',
        'kKunde'          => 'CustomerID',
        'kKundenfeld'     => 'CustomerFieldID',
        'cName'           => 'Label',
        'cWawi'           => 'Name',
        'cWert'           => 'Value',
        'nSort'           => 'Order',
        'nEditierbar'     => 'Editable',
    ];

    public function __construct(?stdClass $record = null)
    {
        $this->setRecord($record);
    }

    public static function load(int $id): self
    {
        $instance = new self();
        $instance->setRecord(
            Shop::Container()->getDB()->getSingleObject(
                'SELECT tkundenattribut.kKundenAttribut, tkundenattribut.kKunde, tkundenattribut.kKundenfeld,
                       tkundenfeld.cName, tkundenfeld.cWawi, tkundenattribut.cWert, tkundenfeld.nSort,
                       IF(COALESCE(tkundenattribut.cWert, \'\') = \'\', 1, tkundenfeld.nEditierbar) nEditierbar
                    FROM tkundenattribut
                    INNER JOIN tkundenfeld ON tkundenfeld.kKundenfeld = tkundenattribut.kKundenfeld
                    WHERE tkundenattribut.kKundenAttribut = :id',
                ['id' => $id]
            )
        );

        return $instance;
    }

    public function save(): self
    {
        $record        = $this->getRecord();
        $record->cName = $record->cWawi;
        unset(
            $record->cWawi,
            $record->nSort,
            $record->nEditierbar
        );

        if ($record->kKundenAttribut === 0) {
            unset($record->kKundenAttribut);
        }
        $res = Shop::Container()->getDB()->upsert(
            'tkundenattribut',
            $record,
            ['kKundenAttribut', 'kKunde', 'kKundenfeld']
        );

        if ($res > 0) {
            $this->setID($res);
        }

        return $this;
    }

    public function getID(): int
    {
        return $this->id;
    }

    public function setID(int|string|null $id): void
    {
        $this->id = (int)($id ?? 0);
    }

    public function getCustomerID(): int
    {
        return $this->customerID;
    }

    public function setCustomerID(int|string|null $customerID): void
    {
        $this->customerID = (int)($customerID ?? 0);
    }

    public function getCustomerFieldID(): int
    {
        return $this->customerFieldID;
    }

    public function setCustomerFieldID(int|string $customerFieldID): void
    {
        $this->customerFieldID = (int)$customerFieldID;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getValue(): string
    {
        return $this->value ?? '';
    }

    public function setValue(?string $value): void
    {
        $this->value = $value;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function setOrder(int|string $order): void
    {
        $this->order = (int)$order;
    }

    public function isEditable(): bool
    {
        return $this->editable;
    }

    public function getEditable(): int
    {
        return $this->editable ? 1 : 0;
    }

    public function setEditable(bool|int|string $editable): void
    {
        $this->editable = (bool)$editable;
    }

    /**
     * @param array<mixed>|stdClass|null $record
     */
    public function setRecord(array|stdClass|null $record): self
    {
        if (!\is_object($record) && !\is_array($record)) {
            $this->setID(0);
            $this->setCustomerFieldID(0);
            $this->setCustomerID(0);
            $this->setLabel('');
            $this->setName('');
            $this->setValue('');
            $this->setOrder(0);
            $this->setEditable(true);

            return $this;
        }
        foreach ((array)$record as $item => $value) {
            $mapped = self::getMapping($item);
            if (\is_string($mapped)) {
                $method = 'set' . $mapped;

                $this->$method($value);
            }
        }

        return $this;
    }

    public function getRecord(): stdClass
    {
        $result = new stdClass();

        foreach (self::$mapping as $item => $mapped) {
            $method        = 'get' . $mapped;
            $result->$item = $this->$method();
        }

        return $result;
    }
}
