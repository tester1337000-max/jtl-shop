<?php

declare(strict_types=1);

namespace JTL\Link;

/**
 * Interface LinkGroupListInterface
 * @package JTL\Link
 */
interface LinkGroupListInterface
{
    /**
     * @return $this
     */
    public function loadAll(): LinkGroupListInterface;

    /**
     * @return LinkGroupCollection
     */
    public function getLinkGroups(): LinkGroupCollection;

    /**
     * @param LinkGroupCollection $linkGroups
     */
    public function setLinkGroups(LinkGroupCollection $linkGroups): void;

    /**
     * @return LinkGroupCollection
     */
    public function getVisibleLinkGroups(): LinkGroupCollection;

    /**
     * @param LinkGroupCollection $linkGroups
     */
    public function setVisibleLinkGroups(LinkGroupCollection $linkGroups): void;

    /**
     * @param int $customerGroupID
     * @param int $customerID
     * @return $this
     */
    public function applyVisibilityFilter(int $customerGroupID, int $customerID): LinkGroupListInterface;

    /**
     * @param string $name
     * @param bool   $filtered
     * @return LinkGroupInterface|null
     */
    public function getLinkgroupByTemplate(string $name, bool $filtered = true): ?LinkGroupInterface;

    /**
     * @param int  $id
     * @param bool $filtered
     * @return LinkGroupInterface|null
     */
    public function getLinkgroupByID(int $id, bool $filtered = true): ?LinkGroupInterface;
}
