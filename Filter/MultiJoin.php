<?php

declare(strict_types=1);

namespace JTL\Filter;

/**
 * Class MultiJoin
 * @package JTL\Filter
 */
class MultiJoin extends Join
{
    /**
     * @var JoinInterface[]
     */
    private array $joinChain = [];

    /**
     * @param JoinInterface $join
     * @return static
     */
    public function addJoin(JoinInterface $join): JoinInterface
    {
        $this->joinChain[$join->getTable()] = $join;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getSQL(): string
    {
        if (\count($this->joinChain) === 0) {
            return parent::getSQL();
        }
        $table = $this->getTable();
        if ($table === '') {
            return '';
        }

        $on = $this->getOn();
        if ($on !== '') {
            $on = ' ON ' . $on;
        }
        $subJoins = '';
        foreach ($this->joinChain as $join) {
            $subJoins .= ' ' . $join->getSQL();
        }

        return $this->getType() . ' ' . '(' . $table . $subJoins . ')' . $on;
    }
}
