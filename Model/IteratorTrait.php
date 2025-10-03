<?php

declare(strict_types=1);

namespace JTL\Model;

/**
 * Trait IteratorTrait
 * @package JTL\Model
 */
trait IteratorTrait
{
    /**
     * Stores keynames for iterator interface
     * @var string[]
     */
    protected array $iteratorKeys;

    /**
     * Add a key to the internal iterator array - this will be used to iterate over all public propertys of this model.
     * Basically the DataModel will only iterate over database attributes. If a persistent class property is defined in
     * descendents, this property must be added if it should be used for iteration. A good place to use this function
     * is {@link onRegisterHandlers}.
     *
     * @param string $keyName - the property/key to add to the list of iteratorkeys
     */
    protected function addIteratorKey(string $keyName): void
    {
        if (!\in_array($keyName, $this->iteratorKeys, true)) {
            $this->iteratorKeys[] = $keyName;
            \reset($this->iteratorKeys);
        }
    }

    /**
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function current(): mixed
    {
        $key = \current($this->iteratorKeys);

        return $this->$key;
    }

    /**
     * @inheritdoc
     */
    public function next(): void
    {
        \next($this->iteratorKeys);
    }

    /**
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function key(): mixed
    {
        return \current($this->iteratorKeys);
    }

    /**
     * @return bool
     */
    public function valid(): bool
    {
        return \key($this->iteratorKeys) !== null;
    }

    /**
     * @inheritdoc
     */
    public function rewind(): void
    {
        \reset($this->iteratorKeys);
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $result = [];
        foreach ($this->iteratorKeys as $key) {
            $result[$key] = $this->$key;
        }

        return $result;
    }
}
