<?php

declare(strict_types=1);

namespace JTL\Services\JTL;

use Illuminate\Support\Collection;
use JTL\Link\LinkGroupCollection;
use JTL\Link\LinkGroupInterface;
use JTL\Link\LinkInterface;
use stdClass;

/**
 * Interface LinkServiceInterface
 * @package JTL\Services\JTL
 */
interface LinkServiceInterface
{
    /**
     * @return LinkGroupCollection
     */
    public function getLinkGroups(): LinkGroupCollection;

    /**
     * @return LinkGroupCollection
     */
    public function getVisibleLinkGroups(): LinkGroupCollection;

    /**
     * @return LinkGroupCollection
     */
    public function getAllLinkGroups(): LinkGroupCollection;

    /**
     *
     */
    public function initLinkGroups(): void;

    /**
     *
     */
    public function reset(): void;

    /**
     * @param int    $languageID
     * @param string $locale
     */
    public function updateDefaultLanguageData(int $languageID, string $locale): void;

    /**
     * @param int $id
     * @return LinkInterface|null
     */
    public function getLinkByID(int $id): ?LinkInterface;

    /**
     * @param int $id
     * @return LinkInterface|null
     */
    public function getParentForID(int $id): ?LinkInterface;

    /**
     * @param int $id
     * @return int[]
     */
    public function getParentIDs(int $id): array;

    /**
     * @param int $id
     * @return Collection<int, LinkInterface>
     */
    public function getParentLinks(int $id): Collection;

    /**
     * @param int $id
     * @return int|null
     */
    public function getRootID(int $id): ?int;

    /**
     * @param int $parentLinkID
     * @param int $linkID
     * @return bool
     */
    public function isDirectChild(int $parentLinkID, int $linkID): bool;

    /**
     * @param int $id
     * @return LinkInterface
     */
    public function getLinkObjectByID(int $id): LinkInterface;

    /**
     * @former gibLinkKeySpecialSeite()
     * @param int $linkType
     * @return LinkInterface
     */
    public function getSpecialPage(int $linkType): LinkInterface;

    /**
     * @former gibLinkKeySpecialSeite()
     * @param int  $linkType
     * @param bool $fallback
     * @return int|false
     */
    public function getSpecialPageID(int $linkType, bool $fallback = true): bool|int;

    /**
     * for compatibility only
     *
     * @former gibLinkKeySpecialSeite()
     * @param int $linkType
     * @return int|false
     */
    public function getSpecialPageLinkKey(int $linkType): bool|int;

    /**
     * @param string $name
     * @param bool   $filtered
     * @return LinkGroupInterface|null
     */
    public function getLinkGroupByName(string $name, bool $filtered = true): ?LinkGroupInterface;

    /**
     * @param int $id
     * @return LinkGroupInterface|null
     */
    public function getLinkGroupByID(int $id): ?LinkGroupInterface;

    /**
     * @param string      $id
     * @param bool        $full
     * @param bool        $secure
     * @param string|null $langISO
     * @return string
     */
    public function getStaticRoute(
        string $id = 'kontakt.php',
        bool $full = true,
        bool $secure = false,
        ?string $langISO = null
    ): string;

    /**
     * careful: this works compatible to gibSpezialSeiten() -
     * so only the first special page link per page type is returned!
     *
     * @former gibSpezialSeiten()
     * @return Collection<int, LinkInterface>
     */
    public function getSpecialPages(): Collection;

    /**
     * for compatibility only
     *
     * @param int $id
     * @return LinkInterface|null
     */
    public function getPageLinkLanguage(int $id): ?LinkInterface;

    /**
     * for compatibility only
     *
     * @param int $id
     * @return LinkInterface|null
     */
    public function getPageLink(int $id): ?LinkInterface;

    /**
     * for compatibility only
     *
     * @param int $id
     * @return LinkInterface|null
     */
    public function getLinkObject(int $id): ?LinkInterface;

    /**
     * for compatibility only
     *
     * @param int $id
     * @param int $pluginID
     * @return LinkInterface|null
     */
    public function findCMSLinkInSession(int $id, int $pluginID = 0): ?LinkInterface;

    /**
     * for compatibility only
     *
     * @param int $parentLinkID
     * @param int $linkID
     * @return bool
     */
    public function isChildActive(int $parentLinkID, int $linkID): bool;

    /**
     * for compatibility only
     *
     * @param int $id
     * @return int|null
     */
    public function getRootLink(int $id): ?int;

    /**
     * for compatibility only
     *
     * @param int $id
     * @return int[]
     */
    public function getParentsArray(int $id): array;

    /**
     * for compatibility only
     * careful: does not do what it says it does.
     *
     * @param int $id
     * @return LinkInterface|null
     */
    public function getParent(int $id): ?LinkInterface;

    /**
     * @param int $type
     * @return stdClass
     */
    public function buildSpecialPageMeta(int $type): stdClass;

    /**
     * @return bool
     */
    public function checkNoIndex(): bool;

    /**
     * @former aktiviereLinks()
     * @param int $pageType
     * @return LinkGroupCollection
     */
    public function activate(int $pageType): LinkGroupCollection;

    /**
     * @param int $langID
     * @param int $customerGroupID
     * @return stdClass|false
     */
    public function getAGBWRB(int $langID, int $customerGroupID): bool|stdClass;
}
