<?php

declare(strict_types=1);

namespace JTL\Filter;

use function Functional\reduce_left;

/**
 * Class StateSQL
 * @package JTL\Filter
 */
class StateSQL implements StateSQLInterface
{
    /**
     * @var string[]
     */
    protected array $having = [];

    /**
     * @var string[]
     */
    protected array $conditions = [];

    /**
     * @var JoinInterface[]
     */
    protected array $joins = [];

    /**
     * @var string[]
     */
    protected array $select = ['tartikel.kArtikel'];

    private ?string $orderBy = '';

    private string $limit = '';

    /**
     * @var string[]
     */
    private array $groupBy = ['tartikel.kArtikel'];

    public function __construct()
    {
    }

    /**
     * @inheritdoc
     */
    public function from(StateSQLInterface $source): StateSQLInterface
    {
        $this->setJoins($source->getJoins());
        $this->setSelect($source->getSelect());
        $this->setConditions($source->getConditions());
        $this->setHaving($source->getHaving());

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getHaving(): array
    {
        return $this->having;
    }

    /**
     * @inheritdoc
     */
    public function setHaving(array $having): void
    {
        $this->having = $having;
    }

    /**
     * @inheritdoc
     */
    public function addHaving(string $having): array
    {
        $this->having[] = $having;

        return $this->having;
    }

    /**
     * @inheritdoc
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    /**
     * @inheritdoc
     */
    public function setConditions(array $conditions): void
    {
        $this->conditions = $conditions;
    }

    /**
     * @inheritdoc
     */
    public function addCondition(string $condition): array
    {
        $this->conditions[] = $condition;

        return $this->conditions;
    }

    /**
     * @inheritdoc
     */
    public function getJoins(): array
    {
        return $this->joins;
    }

    /**
     * @inheritdoc
     */
    public function getDeduplicatedJoins(): array
    {
        $checked = [];

        return reduce_left($this->joins, static function (JoinInterface $value, $d, $c, $reduction) use (&$checked) {
            $key = $value->getTable();
            if (!\in_array($key, $checked, true)) {
                $checked[]   = $key;
                $reduction[] = $value;
            }

            return $reduction;
        }, []);
    }

    /**
     * @inheritdoc
     */
    public function setJoins(array $joins): void
    {
        $this->joins = $joins;
    }

    /**
     * @inheritdoc
     */
    public function addJoin(JoinInterface $join): array
    {
        $this->joins[] = $join;

        return $this->joins;
    }

    /**
     * @inheritdoc
     */
    public function getSelect(): array
    {
        return $this->select;
    }

    /**
     * @inheritdoc
     */
    public function setSelect(array $select): void
    {
        $this->select = $select;
    }

    /**
     * @inheritdoc
     */
    public function addSelect(string $select): array
    {
        $this->select[] = $select;

        return $this->select;
    }

    /**
     * @inheritdoc
     */
    public function getOrderBy(): ?string
    {
        return $this->orderBy;
    }

    /**
     * @inheritdoc
     */
    public function setOrderBy(?string $orderBy): void
    {
        $this->orderBy = $orderBy;
    }

    /**
     * @inheritdoc
     */
    public function getLimit(): string
    {
        return $this->limit;
    }

    /**
     * @inheritdoc
     */
    public function setLimit(string $limit): void
    {
        $this->limit = $limit;
    }

    /**
     * @inheritdoc
     */
    public function getGroupBy(): array
    {
        return $this->groupBy;
    }

    /**
     * @inheritdoc
     */
    public function addGroupBy(string $groupBy): array
    {
        $this->groupBy[] = $groupBy;

        return $this->groupBy;
    }

    /**
     * @inheritdoc
     */
    public function setGroupBy(array $groupBy): void
    {
        $this->groupBy = $groupBy;
    }
}
