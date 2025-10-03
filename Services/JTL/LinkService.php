<?php

declare(strict_types=1);

namespace JTL\Services\JTL;

use Illuminate\Support\Collection;
use JTL\Cache\JTLCacheInterface;
use JTL\Customer\CustomerGroup;
use JTL\DB\DbInterface;
use JTL\Language\LanguageHelper;
use JTL\Link\Link;
use JTL\Link\LinkGroupCollection;
use JTL\Link\LinkGroupInterface;
use JTL\Link\LinkGroupList;
use JTL\Link\LinkGroupListInterface;
use JTL\Link\LinkInterface;
use JTL\Link\SpecialPageNotFoundException;
use JTL\Shop;
use stdClass;

use function Functional\first;
use function Functional\first_index_of;

/**
 * Class LinkService
 * @package JTL\Services\JTL
 */
final class LinkService implements LinkServiceInterface
{
    private static ?LinkService $instance = null;

    private LinkGroupListInterface $linkGroupList;

    public function __construct(private readonly DbInterface $db, private readonly JTLCacheInterface $cache)
    {
        self::$instance      = $this;
        $this->linkGroupList = new LinkGroupList($db, $cache);
        $this->initLinkGroups();
    }

    public static function getInstance(): LinkServiceInterface
    {
        return self::$instance ?? new self(Shop::Container()->getDB(), Shop::Container()->getCache());
    }

    public function reset(): void
    {
        $this->linkGroupList = new LinkGroupList($this->db, $this->cache);
        $this->initLinkGroups();
    }

    /**
     * @inheritdoc
     */
    public function updateDefaultLanguageData(int $languageID, string $locale): void
    {
        /** @var LinkGroupInterface $linkGroup */
        foreach ($this->linkGroupList->getLinkGroups() as $linkGroup) {
            /** @var Link $link */
            foreach ($linkGroup->getLinks() as $link) {
                $link->initLanguageID($languageID, $locale);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function getLinkGroups(): LinkGroupCollection
    {
        return $this->linkGroupList->getLinkGroups();
    }

    /**
     * @inheritdoc
     */
    public function getVisibleLinkGroups(): LinkGroupCollection
    {
        return $this->linkGroupList->getVisibleLinkGroups();
    }

    /**
     * @inheritdoc
     */
    public function getAllLinkGroups(): LinkGroupCollection
    {
        return $this->linkGroupList->getLinkGroups();
    }

    /**
     * @inheritdoc
     */
    public function initLinkGroups(): void
    {
        $this->linkGroupList->loadAll();
    }

    /**
     * @inheritdoc
     */
    public function getLinkByID(int $id): ?LinkInterface
    {
        if ($id === 0) {
            return null;
        }
        /** @var LinkGroupInterface $linkGroup */
        foreach ($this->linkGroupList->getLinkGroups() as $linkGroup) {
            /** @var LinkInterface|null $first */
            $first = first($linkGroup->getLinks(), fn(LinkInterface $link): bool => $link->getID() === $id);
            if ($first !== null) {
                return $first;
            }
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function getParentForID(int $id): ?LinkInterface
    {
        if ($id === 0) {
            return null;
        }
        /** @var LinkGroupInterface $linkGroup */
        foreach ($this->linkGroupList->getLinkGroups() as $linkGroup) {
            /** @var LinkInterface|null $first */
            $first = first($linkGroup->getLinks(), fn(LinkInterface $link): bool => $link->getID() === $id);
            if ($first !== null) {
                return $this->getLinkByID($first->getParent());
            }
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function getParentIDs(int $id): array
    {
        $result = [];
        $link   = $this->getParentForID($id);
        while ($link !== null && $link->getID() > 0) {
            \array_unshift($result, $link->getID());
            $link = $this->getLinkByID($link->getParent());
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getParentLinks(int $id): Collection
    {
        $result = new Collection();
        $link   = $this->getParentForID($id);
        while ($link !== null && $link->getID() > 0) {
            $result->push($link);
            $link = $this->getLinkByID($link->getParent());
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getRootID(int $id): ?int
    {
        $res = null;
        while (($parent = $this->getParentForID($id)) !== null && $parent->getID() !== $id) {
            $id  = $parent->getID();
            $res = $parent;
        }
        if ($res === null) {
            $res = $this->getLinkByID($id);
        }

        return $res?->getID();
    }

    /**
     * @inheritdoc
     */
    public function isDirectChild(int $parentLinkID, int $linkID): bool
    {
        if ($parentLinkID <= 0) {
            return false;
        }
        /** @var LinkGroupInterface $linkGroup */
        foreach ($this->linkGroupList->getLinkGroups() as $linkGroup) {
            /** @var LinkInterface $link */
            foreach ($linkGroup->getLinks() as $link) {
                if ($link->getID() === $linkID && $link->getParent() === $parentLinkID) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function getLinkObjectByID(int $id): LinkInterface
    {
        return (new Link($this->db))->load($id);
    }

    /**
     * @former gibLinkKeySpecialSeite()
     * @throws SpecialPageNotFoundException
     */
    public function getSpecialPage(int $linkType): LinkInterface
    {
        $lg = $this->getLinkGroupByName('specialpages');
        if ($lg === null) {
            throw new SpecialPageNotFoundException($linkType);
        }
        /** @var LinkInterface|null $lt */
        $lt = $lg->getLinks()->first(fn(LinkInterface $link): bool => $link->getLinkType() === $linkType);
        if ($lt === null) {
            throw new SpecialPageNotFoundException($linkType);
        }

        return $lt;
    }

    /**
     * @inheritdoc
     */
    public function getSpecialPageID(int $linkType, bool $fallback = true): bool|int
    {
        try {
            $link = $this->getSpecialPage($linkType);
        } catch (SpecialPageNotFoundException $e) {
            Shop::Container()->getLogService()->warning($e->getMessage());
            $link = null;
        }
        if ($link !== null) {
            return $link->getID();
        }
        $link = $fallback ? $this->getSpecialPage(\LINKTYP_404) : null;

        return $link === null ? false : $link->getID();
    }

    /**
     * @inheritdoc
     */
    public function getSpecialPageLinkKey(int $linkType): bool|int
    {
        return $this->getSpecialPageID($linkType);
    }

    /**
     * @inheritdoc
     */
    public function getLinkGroupByName(string $name, bool $filtered = true): ?LinkGroupInterface
    {
        return $this->linkGroupList->getLinkgroupByTemplate($name, $filtered);
    }

    /**
     * @inheritdoc
     */
    public function getLinkGroupByID(int $id): ?LinkGroupInterface
    {
        return $this->linkGroupList->getLinkgroupByID($id);
    }

    /**
     * @inheritdoc
     */
    public function getStaticRoute(
        string $id = 'kontakt.php',
        bool $full = true,
        bool $secure = false,
        ?string $langISO = null
    ): string {
        $idx = null;
        $lg  = $this->getLinkGroupByName('staticroutes');
        if ($lg !== null) {
            /** @var LinkInterface|null $filterd */
            $filterd = $lg->getLinks()->first(fn(LinkInterface $link): bool => $link->getFileName() === $id);
            if ($filterd !== null) {
                if ($langISO !== null) {
                    $codes = $filterd->getLanguageCodes();
                    $idx   = first_index_of($codes, $langISO);
                }

                if ($idx !== false) {
                    return $full ? $filterd->getURL($idx) : $filterd->getSlug($idx);
                }
            }
        }

        return $full && !\str_starts_with($id, 'http')
            ? Shop::getURL($secure) . '/' . $id
            : $id;
    }

    /**
     * @inheritdoc
     */
    public function getSpecialPages(): Collection
    {
        $lg = $this->getLinkGroupByName('specialpages');
        if ($lg === null) {
            return new Collection();
        }

        return $lg->getLinks()->groupBy(fn(LinkInterface $link): int => $link->getLinkType())
            ->map(fn(Collection $group) => $group->first());
    }

    /**
     * @inheritdoc
     */
    public function getPageLinkLanguage(int $id): ?LinkInterface
    {
        return $this->getLinkByID($id);
    }

    /**
     * @inheritdoc
     */
    public function getPageLink(int $id): ?LinkInterface
    {
        return $this->getLinkByID($id);
    }

    /**
     * @inheritdoc
     */
    public function getLinkObject(int $id): ?LinkInterface
    {
        return $this->getLinkByID($id);
    }

    /**
     * @inheritdoc
     */
    public function findCMSLinkInSession(int $id, int $pluginID = 0): ?LinkInterface
    {
        $link = $this->getLinkByID($id);

        return $pluginID === 0 || ($link !== null && $link->getPluginID() === $pluginID)
            ? $link
            : null;
    }

    /**
     * @inheritdoc
     */
    public function isChildActive(int $parentLinkID, int $linkID): bool
    {
        return $this->isDirectChild($parentLinkID, $linkID);
    }

    /**
     * @inheritdoc
     */
    public function getRootLink(int $id): ?int
    {
        return $this->getRootID($id);
    }

    /**
     * @inheritdoc
     */
    public function getParentsArray(int $id): array
    {
        return $this->getParentIDs($id);
    }

    /**
     * @inheritdoc
     */
    public function getParent(int $id): ?LinkInterface
    {
        return $this->getLinkByID($id);
    }

    /**
     * @inheritdoc
     */
    public function buildSpecialPageMeta(int $type): stdClass
    {
        $meta            = new stdClass();
        $meta->cTitle    = '';
        $meta->cDesc     = '';
        $meta->cKeywords = '';
        /** @var LinkGroupInterface $linkGroup */
        foreach ($this->linkGroupList->getLinkGroups() as $linkGroup) {
            /** @var ?LinkInterface $first */
            $first = $linkGroup->getLinks()->first(fn(LinkInterface $link): bool => $link->getLinkType() === $type);
            if ($first !== null) {
                $meta->cTitle    = $first->getMetaTitle();
                $meta->cDesc     = $first->getMetaDescription();
                $meta->cKeywords = $first->getMetaKeyword();

                return $meta;
            }
        }

        return $meta;
    }

    /**
     * @inheritdoc
     */
    public function checkNoIndex(): bool
    {
        return Shop::getProductFilter()->getMetaData()->checkNoIndex();
    }

    /**
     * @inheritdoc
     */
    public function activate(int $pageType): LinkGroupCollection
    {
        $linkGroups = $this->linkGroupList->getLinkGroups();
        /** @var LinkGroupInterface $linkGroup */
        foreach ($linkGroups as $linkGroup) {
            /** @var LinkInterface $link */
            foreach ($linkGroup->getLinks() as $link) {
                $link->setIsActive(false);
                $linkType = $link->getLinkType();
                $linkID   = $link->getID();
                switch ($pageType) {
                    case \PAGE_STARTSEITE:
                        if ($linkType === \LINKTYP_STARTSEITE) {
                            $link->setIsActive(true);
                        }
                        break;
                    case \PAGE_EIGENE:
                        $parent = $link->getParent();
                        if ($parent === 0 && $this->isChildActive($linkID, Shop::$kLink)) {
                            $link->setIsActive(true);
                        }
                        if ($linkID === Shop::$kLink) {
                            $link->setIsActive(true);
                            $parent = $this->getRootLink($linkID);
                            $linkGroup->getLinks()->filter(fn(LinkInterface $l): bool => $l->getID() === $parent)
                                ->map(static function (LinkInterface $l): LinkInterface {
                                    $l->setIsActive(true);

                                    return $l;
                                });
                        }
                        break;
                    case \PAGE_WARENKORB:
                        if ($linkType === \LINKTYP_WARENKORB) {
                            $link->setIsActive(true);
                        }
                        break;
                    case \PAGE_LOGIN:
                    case \PAGE_MEINKONTO:
                        if ($linkType === \LINKTYP_LOGIN) {
                            $link->setIsActive(true);
                        }
                        break;
                    case \PAGE_REGISTRIERUNG:
                        if ($linkType === \LINKTYP_REGISTRIEREN) {
                            $link->setIsActive(true);
                        }
                        break;
                    case \PAGE_PASSWORTVERGESSEN:
                        if ($linkType === \LINKTYP_PASSWORD_VERGESSEN) {
                            $link->setIsActive(true);
                        }
                        break;
                    case \PAGE_KONTAKT:
                        if ($linkType === \LINKTYP_KONTAKT) {
                            $link->setIsActive(true);
                        }
                        break;
                    case \PAGE_NEWSLETTER:
                        if ($linkType === \LINKTYP_NEWSLETTER) {
                            $link->setIsActive(true);
                        }
                        break;
                    case \PAGE_NEWS:
                        if ($linkType === \LINKTYP_NEWS) {
                            $link->setIsActive(true);
                        }
                        break;
                    case \PAGE_ARTIKEL:
                    case \PAGE_ARTIKELLISTE:
                    case \PAGE_BESTELLVORGANG:
                    default:
                        break;
                }
            }
        }

        return $linkGroups;
    }

    /**
     * @inheritdoc
     */
    public function getAGBWRB(int $langID, int $customerGroupID): bool|stdClass
    {
        $cacheID = 'agbwrb_' . $langID . '_' . $customerGroupID;
        /** @var stdClass|false $data */
        $data = $this->cache->get($cacheID);
        if ($data !== false) {
            return $data;
        }
        $linkAGB     = null;
        $linkWRB     = null;
        $linkWRBForm = null;
        // kLink für AGB und WRB suchen
        foreach ($this->getSpecialPages() as $sp) {
            /** @var LinkInterface $sp */
            if ($sp->getLinkType() === \LINKTYP_AGB) {
                $linkAGB = $sp;
            } elseif ($sp->getLinkType() === \LINKTYP_WRB) {
                $linkWRB = $sp;
            } elseif ($sp->getLinkType() === \LINKTYP_WRB_FORMULAR) {
                $linkWRBForm = $sp;
            }
        }
        $data = $this->db->select(
            'ttext',
            'kKundengruppe',
            $customerGroupID,
            'kSprache',
            $langID
        );
        if ($data === null || empty($data->kText)) {
            $data = $this->db->select(
                'ttext',
                'kKundengruppe',
                (new CustomerGroup(0, $this->db))->loadDefaultGroup()->getID(),
                'kSprache',
                $langID
            );
        }
        if (empty($data->kText)) {
            $data = $this->db->select(
                'ttext',
                'kKundengruppe',
                $customerGroupID,
                'kSprache',
                LanguageHelper::getDefaultLanguage()->getId()
            );
        }
        if (empty($data->kText)) {
            $data = $this->db->select(
                'ttext',
                'kKundengruppe',
                (new CustomerGroup(0, $this->db))->loadDefaultGroup()->getID(),
                'kSprache',
                LanguageHelper::getDefaultLanguage()->getId()
            );
        }
        if (empty($data->kText)) {
            return false;
        }
        $data->cURLAGB      = $linkAGB !== null ? $linkAGB->getURL() : '';
        $data->cURLWRB      = $linkWRB !== null ? $linkWRB->getURL() : '';
        $data->cURLWRBForm  = $linkWRBForm !== null ? $linkWRBForm->getURL() : '';
        $data->kLinkAGB     = $linkAGB !== null ? $linkAGB->getID() : 0;
        $data->kLinkWRB     = $linkWRB !== null ? $linkWRB->getID() : 0;
        $data->kLinkWRBForm = $linkWRBForm !== null ? $linkWRBForm->getID() : 0;

        $data->agbWrbNotice = ((int)Shop::getSettingValue(\CONF_KAUFABWICKLUNG, 'bestellvorgang_wrb_anzeigen')) === 1
            ? \sprintf(
                Shop::Lang()->get('termsCancelationNotice', 'checkout'),
                $data->cURLAGB,
                $data->kLinkAGB > 0
                    ? 'class="popup"'
                    : 'data-toggle="modal" data-target="#agb-modal" class="modal-popup"',
                $data->cURLWRB,
                $data->kLinkWRB > 0
                    ? 'class="popup"'
                    : 'data-toggle="modal" data-target="#wrb-modal" class="modal-popup"'
            )
            : \sprintf(
                Shop::Lang()->get('termsNotice', 'checkout'),
                $data->cURLAGB,
                $data->kLinkAGB > 0
                    ? 'class="popup"'
                    : 'data-toggle="modal" data-target="#agb-modal" class="modal-popup"'
            );
        $this->cache->set($cacheID, $data, [\CACHING_GROUP_CORE]);

        return $data;
    }
}
