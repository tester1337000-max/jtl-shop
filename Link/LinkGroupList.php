<?php

declare(strict_types=1);

namespace JTL\Link;

use Illuminate\Support\Collection;
use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Session\Frontend;
use stdClass;

use function Functional\group;

/**
 * Class LinkGroupList
 * @package JTL\Link
 */
final class LinkGroupList implements LinkGroupListInterface
{
    private LinkGroupCollection $linkGroups;

    private LinkGroupCollection $visibleLinkGroups;

    public function __construct(private readonly DbInterface $db, private readonly JTLCacheInterface $cache)
    {
        $this->linkGroups        = new LinkGroupCollection();
        $this->visibleLinkGroups = new LinkGroupCollection();
    }

    /**
     * @param string $name
     * @return LinkGroupInterface|null
     */
    public function __get(string $name)
    {
        \trigger_error(__CLASS__ . ': getter should be used to get ' . $name, \E_USER_DEPRECATED);

        return $this->getLinkgroupByTemplate($name);
    }

    public function __set(string $name, mixed $value): void
    {
        \trigger_error(__CLASS__ . ': setting data like this not supported anymore. ', \E_USER_DEPRECATED);
    }

    public function __isset(string $name): bool
    {
        return $this->__get($name) !== null;
    }

    /**
     * @inheritdoc
     */
    public function loadAll(): LinkGroupListInterface
    {
        if ($this->linkGroups->count() > 0) {
            return $this;
        }
        $cached = true;
        /** @var LinkGroupCollection|false $data */
        $data = $this->cache->get('linkgroups');
        if ($data === false) {
            $cached           = false;
            $this->linkGroups = new LinkGroupCollection();
            foreach ($this->loadDefaultGroups() as $group) {
                $this->linkGroups->push($group);
            }
            $this->linkGroups->push($this->loadSpecialPages());
            $this->linkGroups->push($this->loadStaticRoutes());
            $this->linkGroups->push($this->loadUnassignedGroups());

            \executeHook(\HOOK_LINKGROUPS_LOADED_PRE_CACHE, ['list' => $this]);
            $this->cache->set('linkgroups', $this->linkGroups, [\CACHING_GROUP_CORE]);
        } else {
            $this->linkGroups = $data;
        }
        $this->applyVisibilityFilter(Frontend::getCustomerGroup()->getID(), Frontend::getCustomer()->getID());
        \executeHook(\HOOK_LINKGROUPS_LOADED, ['list' => $this, 'cached' => $cached]);

        return $this;
    }

    /**
     * @return LinkGroupInterface
     */
    private function loadUnassignedGroups(): LinkGroupInterface
    {
        $unassigned = $this->db->getObjects(
            "SELECT tlink.*, tlinksprache.cISOSprache, 
                tlink.cName AS displayName, tlinksprache.cName AS localizedName, 
                tlinksprache.cTitle AS localizedTitle, tsprache.kSprache, 
                tlinksprache.cSeo AS linkURL, tlinksprache.cContent AS content,
                tlinksprache.cMetaDescription AS metaDescription, tlinksprache.cMetaKeywords AS metaKeywords,
                tlinksprache.cMetaTitle AS metaTitle, tseo.kSprache AS languageID,
                tseo.cSeo AS localizedUrl, '' AS cDateiname, '' AS linkGroups, 2 AS pluginState
                    FROM tlinksprache
                    JOIN tlink
                        ON tlink.kLink = tlinksprache.kLink
                    JOIN tsprache
                        ON tsprache.cISO = tlinksprache.cISOSprache
                    LEFT JOIN tseo
                        ON tseo.cKey = 'kLink'
                        AND tseo.kKey = tlink.kLink
                        AND tseo.kSprache = tsprache.kSprache
                    WHERE tlink.kLink NOT IN (SELECT linkID FROM tlinkgroupassociations)
                    GROUP BY tlink.kLink, tsprache.kSprache"
        );
        $grouped    = group($unassigned, fn(stdClass $e): int => (int)$e->kLink);
        $lg         = new LinkGroup($this->db);
        $lg->setID(-1);
        $lg->setNames(['unassigned', 'unassigned']);
        $lg->setTemplate('unassigned');
        $lg->setGroupName('unassigned');
        $links = new Collection();
        foreach ($grouped as $linkData) {
            $link = new Link($this->db);
            $link->map($linkData);
            if ($link->getLinkType() === \LINKTYP_DATENSCHUTZ) {
                $this->linkGroups->Link_Datenschutz = [];
                foreach ($link->getURLs() as $langID => $url) {
                    $this->linkGroups->Link_Datenschutz[$link->getLanguageCode($langID)] = $url;
                }
            } elseif ($link->getLinkType() === \LINKTYP_AGB) {
                $this->linkGroups->Link_AGB = [];
                foreach ($link->getURLs() as $langID => $url) {
                    $this->linkGroups->Link_AGB[$link->getLanguageCode($langID)] = $url;
                }
            } elseif ($link->getLinkType() === \LINKTYP_VERSAND) {
                $this->linkGroups->Link_Versandseite = [];
                foreach ($link->getURLs() as $langID => $url) {
                    $this->linkGroups->Link_Versandseite[$link->getLanguageCode($langID)] = $url;
                }
            }
            $links->push($link);
        }
        $lg->setLinks($links);

        return $lg;
    }

    /**
     * @return LinkGroupInterface[]
     */
    private function loadDefaultGroups(): array
    {
        $groups         = [];
        $groupLanguages = $this->db->getObjects(
            'SELECT g.*, l.cName AS localizedName, l.cISOSprache, g.cTemplatename AS template,
                g.cName AS groupName, IFNULL(tsprache.kSprache, 0) AS kSprache 
                FROM tlinkgruppe AS g
                LEFT JOIN tlinkgruppesprache AS l
                    ON g.kLinkgruppe = l.kLinkgruppe
                LEFT JOIN tsprache 
                    ON tsprache.cISO = l.cISOSprache
                WHERE g.kLinkgruppe > 0 AND l.kLinkgruppe > 0'
        );
        $grouped        = group($groupLanguages, fn(stdClass $e): int => (int)$e->kLinkgruppe);
        foreach ($grouped as $linkGroupID => $localizedLinkgroup) {
            $lg = new LinkGroup($this->db);
            $lg->setID($linkGroupID);
            $lg->setIsSpecial(false);
            $groups[] = $lg->map($localizedLinkgroup);
        }

        return $groups;
    }

    /**
     * @return LinkGroupInterface
     */
    private function loadSpecialPages(): LinkGroupInterface
    {
        $specialPages = $this->db->getObjects(
            "SELECT tlink.*, tlinksprache.cISOSprache, 
                tlink.cName AS displayName, tlinksprache.cName AS localizedName, tlinksprache.cTitle AS localizedTitle, 
                tsprache.kSprache, tlinksprache.cSeo AS linkURL, tlinksprache.cContent AS content,
                tlinksprache.cMetaDescription AS metaDescription, tlinksprache.cMetaKeywords AS metaKeywords,
                tlinksprache.cMetaTitle AS metaTitle, tseo.kSprache AS languageID,
                tseo.cSeo AS localizedUrl, tspezialseite.cDateiname,
                GROUP_CONCAT(tlinkgroupassociations.linkGroupID) AS linkGroups, 2 AS pluginState
                    FROM tlinksprache
                    JOIN tlink
                        ON tlink.kLink = tlinksprache.kLink
                    JOIN tsprache
                        ON tsprache.cISO = tlinksprache.cISOSprache
                    JOIN tlinkgroupassociations
                        ON tlinkgroupassociations.linkID = tlinksprache.kLink
                    LEFT JOIN tseo
                        ON tseo.cKey = 'kLink'
                        AND tseo.kKey = tlink.kLink
                        AND tseo.kSprache = tsprache.kSprache
                    LEFT JOIN tspezialseite
                        ON tspezialseite.nLinkart = tlink.nLinkart
                    WHERE tlink.kLink = tlinksprache.kLink
                        AND tlink.nLinkart >= 5
                    GROUP BY tlink.kLink, tseo.kSprache"
        );
        $grouped      = group($specialPages, fn(stdClass $e): int => (int)$e->kLink);
        $lg           = new LinkGroup($this->db);
        $lg->setID(998);
        $lg->setNames(['specialpages', 'specialpages']);
        $lg->setTemplate('specialpages');
        $lg->setGroupName('specialpages');
        $links = new Collection();
        foreach ($grouped as $linkData) {
            $link = new Link($this->db);
            $link->map($linkData);
            if ($link->getLinkType() === \LINKTYP_DATENSCHUTZ) {
                $this->linkGroups->Link_Datenschutz = [];
                foreach ($link->getURLs() as $langID => $url) {
                    $this->linkGroups->Link_Datenschutz[$link->getLanguageCode($langID)] = $url;
                }
            } elseif ($link->getLinkType() === \LINKTYP_AGB) {
                $this->linkGroups->Link_AGB = [];
                foreach ($link->getURLs() as $langID => $url) {
                    $this->linkGroups->Link_AGB[$link->getLanguageCode($langID)] = $url;
                }
            } elseif ($link->getLinkType() === \LINKTYP_VERSAND) {
                $this->linkGroups->Link_Versandseite = [];
                foreach ($link->getURLs() as $langID => $url) {
                    $this->linkGroups->Link_Versandseite[$link->getLanguageCode($langID)] = $url;
                }
            }
            $links->push($link);
        }
        $lg->setLinks($links);

        return $lg;
    }

    /**
     * @return LinkGroupInterface
     */
    private function loadStaticRoutes(): LinkGroupInterface
    {
        $staticRoutes = $this->db->getObjects(
            "SELECT tspezialseite.kSpezialseite, tspezialseite.cName AS baseName, tspezialseite.cDateiname, 
                tspezialseite.nLinkart, tlink.kLink, tlink.cName AS displayName, tlink.reference,
                tlinksprache.cName AS localizedName, tlinksprache.cTitle AS localizedTitle, 
                tlinksprache.cSeo AS linkURL, tlinksprache.cContent AS content, 
                tlinksprache.cMetaDescription AS metaDescription, tlinksprache.cMetaKeywords AS metaKeywords, 
                tlinksprache.cMetaTitle AS metaTitle, tlink.cKundengruppen,  tseo.cSeo AS localizedUrl,  
                tsprache.cISO AS cISOSprache, tsprache.kSprache AS languageID, tlink.kVaterLink, 
                tspezialseite.kPlugin, tlink.cName, tlink.cNoFollow, tlink.cSichtbarNachLogin, 
                tlink.cDruckButton, tlink.nSort, tlink.bIsActive, tlink.bIsFluid, tlink.bSSL,
                GROUP_CONCAT(tlinkgroupassociations.linkGroupID) AS linkGroups, 2 AS pluginState
            FROM tspezialseite
                LEFT JOIN tlink 
                    ON tlink.nLinkart = tspezialseite.nLinkart
                LEFT JOIN tlinksprache 
                    ON tlink.kLink = tlinksprache.kLink
                JOIN tsprache 
                    ON tsprache.cISO = tlinksprache.cISOSprache
                JOIN tlinkgroupassociations
                    ON tlinkgroupassociations.linkID = tlinksprache.kLink
                LEFT JOIN tseo 
                    ON tseo.cKey = 'kLink' 
                    AND tseo.kKey = tlink.kLink 
                    AND tseo.kSprache = tsprache.kSprache
                WHERE cDateiname IS NOT NULL 
                    AND cDateiname != ''
                GROUP BY tlink.kLink, tsprache.kSprache"
        );
        $grouped      = group($staticRoutes, fn(stdClass $e): int => (int)$e->kLink);
        $lg           = new LinkGroup($this->db);
        $lg->setID(999);
        $lg->setNames(['staticroutes', 'staticroutes']);
        $lg->setTemplate('staticroutes');
        $lg->setGroupName('staticroutes');
        $links = new Collection();
        foreach ($grouped as $linkData) {
            $link = new Link($this->db);
            $link->map($linkData);
            $links->push($link);
        }
        $lg->setLinks($links);

        return $lg;
    }

    /**
     * @inheritdoc
     */
    public function getLinkGroups(): LinkGroupCollection
    {
        return $this->linkGroups;
    }

    /**
     * @inheritdoc
     */
    public function setLinkGroups(LinkGroupCollection $linkGroups): void
    {
        $this->linkGroups = $linkGroups;
    }

    /**
     * @inheritdoc
     */
    public function getVisibleLinkGroups(): LinkGroupCollection
    {
        return $this->visibleLinkGroups;
    }

    /**
     * @inheritdoc
     */
    public function setVisibleLinkGroups(LinkGroupCollection $linkGroups): void
    {
        $this->visibleLinkGroups = $linkGroups;
    }

    /**
     * @inheritdoc
     */
    public function applyVisibilityFilter(int $customerGroupID, int $customerID): LinkGroupListInterface
    {
        /** @var LinkGroupInterface $linkGroup */
        foreach ($this->linkGroups as $linkGroup) {
            $this->runVisibilityCheck($linkGroup, $customerGroupID, $customerID);
            $filtered = clone $linkGroup;
            $filtered->filterLinks(static fn(LinkInterface $l): bool => $l->isVisible() && $l->getIsEnabled());
            $filtered->filterChildLinks();
            $this->visibleLinkGroups->push($filtered);
        }

        return $this;
    }

    private function runVisibilityCheck(LinkGroupInterface $linkGroup, int $customerGroupID, int $customerID): void
    {
        foreach ($linkGroup->getLinks() as $link) {
            $this->checkLinkVisiblity($link, $customerGroupID, $customerID);
        }
    }

    private function checkLinkVisiblity(LinkInterface $link, int $customerGroupID, int $customerID): void
    {
        $childLinks = $link->getChildLinks();
        $link->checkVisibility($customerGroupID, $customerID);
        foreach ($childLinks as $childLink) {
            $childLink->checkVisibility($customerGroupID, $customerID);
            $this->checkLinkVisiblity($childLink, $customerGroupID, $customerID);
        }
    }

    /**
     * @inheritdoc
     */
    public function getLinkgroupByTemplate(string $name, bool $filtered = true): ?LinkGroupInterface
    {
        return ($filtered ? $this->visibleLinkGroups : $this->linkGroups)->getLinkgroupByTemplate($name);
    }

    /**
     * @inheritdoc
     */
    public function getLinkgroupByID(int $id, bool $filtered = true): ?LinkGroupInterface
    {
        return ($filtered ? $this->visibleLinkGroups : $this->linkGroups)->getLinkgroupByID($id);
    }
}
