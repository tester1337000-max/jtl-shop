<?php

declare(strict_types=1);

namespace JTL\Filter;

/**
 * Interface StateSQLInterface
 * @package JTL\Filter
 */
interface StateSQLInterface
{
    /**
     * @param StateSQLInterface $source
     * @return StateSQLInterface
     */
    public function from(StateSQLInterface $source): StateSQLInterface;

    /**
     * @return string[]
     */
    public function getHaving(): array;

    /**
     * @param string[] $having
     */
    public function setHaving(array $having): void;

    /**
     * @param string $having
     * @return string[]
     */
    public function addHaving(string $having): array;

    /**
     * @return string[]
     */
    public function getConditions(): array;

    /**
     * @param string[] $conditions
     */
    public function setConditions(array $conditions): void;

    /**
     * @param string $condition
     * @return string[]
     */
    public function addCondition(string $condition): array;

    /**
     * @return JoinInterface[]
     */
    public function getJoins(): array;

    /**
     * @return JoinInterface[]
     */
    public function getDeduplicatedJoins(): array;

    /**
     * @param JoinInterface[] $joins
     */
    public function setJoins(array $joins): void;

    /**
     * @param JoinInterface $join
     * @return JoinInterface[]
     */
    public function addJoin(JoinInterface $join): array;

    /**
     * @return string[]
     */
    public function getSelect(): array;

    /**
     * @param string[] $select
     */
    public function setSelect(array $select): void;

    /**
     * @param string $select
     * @return string[]
     */
    public function addSelect(string $select): array;

    /**
     * @return string|null
     */
    public function getOrderBy(): ?string;

    /**
     * @param string|null $orderBy
     */
    public function setOrderBy(?string $orderBy): void;

    /**
     * @return string
     */
    public function getLimit(): string;

    /**
     * @param string $limit
     */
    public function setLimit(string $limit): void;

    /**
     * @return string[]
     */
    public function getGroupBy(): array;

    /**
     * @param string $groupBy
     * @return string[]
     */
    public function addGroupBy(string $groupBy): array;

    /**
     * @param string[] $groupBy
     */
    public function setGroupBy(array $groupBy): void;
}
