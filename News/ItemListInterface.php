<?php

declare(strict_types=1);

namespace JTL\News;

use Illuminate\Support\Collection;

/**
 * Interface ItemListInterface
 * @package JTL\News
 * @template T
 */
interface ItemListInterface
{
    /**
     * @param int[] $itemIDs
     * @param bool  $activeOnly
     * @return Collection<int, T>
     */
    public function createItems(array $itemIDs, bool $activeOnly = true): Collection;

    /**
     * @return Collection<int, T>
     */
    public function getItems(): Collection;

    /**
     * @param Collection<int, T> $items
     */
    public function setItems(Collection $items): void;

    /**
     * @param mixed $item
     */
    public function addItem(mixed $item): void;
}
