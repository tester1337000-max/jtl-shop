<?php

declare(strict_types=1);

namespace JTL\Customer;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Shop;
use stdClass;
use Traversable;

use function Functional\map;
use function Functional\select;

/**
 * Class CustomerFields
 * @package JTL\Customer
 * @phpstan-implements IteratorAggregate<int, CustomerField>
 * @phpstan-implements ArrayAccess<int, CustomerField>
 */
class CustomerFields implements ArrayAccess, IteratorAggregate, Countable
{
    /**
     * @var array<int, CustomerField[]>
     */
    private static array $fields = [];

    private int $langID = 0;

    private DbInterface $db;

    private JTLCacheInterface $cache;

    public function __construct(
        int $langID = 0,
        ?DbInterface $db = null,
        ?JTLCacheInterface $cache = null
    ) {
        $this->db    = $db ?? Shop::Container()->getDB();
        $this->cache = $cache ?? Shop::Container()->getCache();
        $langID      = $langID ?: Shop::getLanguageID();
        if ($langID > 0) {
            $this->load($langID);
        }
    }

    public function load(int $langID): self
    {
        $this->langID = $langID;
        if (isset(self::$fields[$langID])) {
            return $this;
        }
        $cacheID = 'cstmrflds_' . $langID;
        /** @var CustomerField[]|false $data */
        $data = $this->cache->get($cacheID);
        if ($data !== false) {
            self::$fields[$langID] = $data;

            return $this;
        }
        /** @var CustomerField[] $data */
        $data = $this->db->getCollection(
            'SELECT kKundenfeld, kSprache, cName, cWawi, cTyp, nSort, nPflicht, nEditierbar
                FROM tkundenfeld
                WHERE kSprache = :langID
                ORDER BY nSort',
            ['langID' => $langID]
        )->map(static function (stdClass $e): CustomerField {
            $e->kSprache    = (int)$e->kSprache;
            $e->kKundenfeld = (int)$e->kKundenfeld;
            $e->nSort       = (int)$e->nPflicht;
            $e->nEditierbar = (int)$e->nEditierbar;
            $e->nPflicht    = (int)$e->nPflicht;

            return new CustomerField($e);
        })->keyBy(fn(CustomerField $field): int => $field->getID())->toArray();
        $this->cache->set($cacheID, $data, [\CACHING_GROUP_OBJECT]);
        self::$fields[$langID] = $data;

        return $this;
    }

    /**
     * @return CustomerField[]
     */
    public function getFields(): array
    {
        return self::$fields[$this->langID] ?? [];
    }

    /**
     * @return int[]
     */
    public function getNonEditableFields(): array
    {
        return map(
            select($this->getFields(), fn(CustomerField $e): bool => !$e->isEditable()),
            fn(CustomerField $e): int => $e->getID()
        );
    }

    /**
     * @inheritdoc
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->getFields());
    }

    /**
     * @inheritdoc
     */
    public function offsetExists($offset): bool
    {
        return \array_key_exists($offset, $this->getFields());
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        $fields = $this->getFields();
        if (!isset($fields[$offset])) {
            return null;
        }

        if (!\is_a($fields[$offset], CustomerField::class)) {
            $fields[$offset] = new CustomerField($fields[$offset]);
        }

        return $fields[$offset];
    }

    /**
     * @inheritdoc
     */
    public function offsetSet($offset, $value): void
    {
        if (\is_a($value, CustomerField::class)) {
            self::$fields[$this->langID][$offset] = $value;
        } elseif (\is_object($value)) {
            self::$fields[$this->langID][$offset] = new CustomerField($value);
        } else {
            throw new \InvalidArgumentException(
                self::class . '::' . __METHOD__ . ' - value must be an object, ' . \gettype($value) . ' given.'
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function offsetUnset($offset): void
    {
        unset(self::$fields[$this->langID][$offset]);
    }

    /**
     * @inheritdoc
     */
    public function count(): int
    {
        return \count($this->getFields());
    }

    /**
     * @return array{langID: int, fields: CustomerField[]}
     */
    public function __debugInfo(): array
    {
        return [
            'langID' => $this->langID,
            'fields' => $this->getFields(),
        ];
    }
}
