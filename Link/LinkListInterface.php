<?php

declare(strict_types=1);

namespace JTL\Link;

use Illuminate\Support\Collection;

/**
 * Interface LinkListInterface
 * @package JTL\Link
 */
interface LinkListInterface
{
    /**
     * @param int[] $linkIDs
     * @return Collection<int, LinkInterface>
     */
    public function createLinks(array $linkIDs): Collection;

    /**
     * @return Collection<int, LinkInterface>
     */
    public function getLinks(): Collection;

    /**
     * @param Collection<int, LinkInterface> $links
     */
    public function setLinks(Collection $links): void;

    /**
     * @param LinkInterface $link
     */
    public function addLink(LinkInterface $link): void;
}
