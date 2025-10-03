<?php

declare(strict_types=1);

namespace JTL\Filter\SortingOptions;

use JTL\Filter\Join;

/**
 * Interface SortingOptionInterface
 * @package JTL\Filter\SortingOptions
 */
interface SortingOptionInterface
{
    /**
     * @return Join
     */
    public function getJoin(): Join;

    /**
     * @param Join $join
     */
    public function setJoin(Join $join): void;

    /**
     * @return string
     */
    public function getOrderBy(): string;

    /**
     * @param string $orderBy
     */
    public function setOrderBy(string $orderBy): void;

    /**
     * @return int
     */
    public function getPriority(): int;

    /**
     * @param int $priority
     */
    public function setPriority(int $priority): void;

    /**
     * @return int|string|array<mixed>
     */
    public function getValue();

    /**
     * @return string|null
     */
    public function getName(): ?string;
}
