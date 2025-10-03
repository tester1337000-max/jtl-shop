<?php

declare(strict_types=1);

namespace JTL\Link;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use JTL\Contracts\RoutableInterface;
use JTL\DB\DbInterface;
use JTL\Plugin\State;
use JTL\Router\RoutableTrait;
use JTL\Router\Router;
use JTL\Shop;
use JTL\Shopsetting;
use stdClass;

/**
 * Class Link
 * @package JTL\Link
 */
final class Link extends AbstractLink implements RoutableInterface
{
    use RoutableTrait;

    protected int $id = 0;

    protected int $level = 0;

    protected int $parent = 0;

    protected int $linkGroupID = -1;

    protected int $pluginID = -1;

    protected int $linkType = -1;

    /**
     * @var int[]
     */
    protected array $linkGroups = [];

    /**
     * @var array<int, string>
     */
    protected array $names = [];

    /**
     * @var array<int, string>
     */
    protected array $titles = [];

    /**
     * @var string[]
     */
    protected array $contents = [];

    /**
     * @var string[]
     */
    protected array $metaTitles = [];

    /**
     * @var array<int, string>
     */
    protected array $metaKeywords = [];

    /**
     * @var array<int, string>
     */
    protected array $metaDescriptions = [];

    /**
     * @var int[]
     */
    protected array $customerGroups = [];

    /**
     * @var int[]
     */
    protected array $languageIDs = [];

    protected int $reference = 0;

    protected int $sort = 0;

    protected bool $ssl = false;

    protected bool $noFollow = false;

    protected bool $printButton = false;

    protected bool $isActive = false;

    protected bool $isEnabled = true;

    protected bool $isFluid = false;

    protected bool $isVisible = true;

    protected bool $isSystem = false;

    protected bool $visibleLoggedInOnly = false;

    /**
     * @var array<int, string>
     */
    protected array $languageCodes = [];

    protected int $redirectCode = 0;

    protected string $identifier = '';

    protected bool $pluginEnabled = true;

    protected string $fileName = '';

    protected string $handler = '';

    protected string $template = '';

    protected string $displayName = '';

    /**
     * @var Collection<int, LinkInterface>
     */
    protected Collection $childLinks;

    protected ?DbInterface $db = null;

    private string $target = '_self';

    public function __construct(DbInterface $db)
    {
        $this->db         = $db;
        $this->childLinks = new Collection();
        $this->setRouteType(Router::TYPE_PAGE);
        $this->initLanguageID();
    }

    public function __wakeup(): void
    {
        $this->initLanguageID();
    }

    private function getDB(): DbInterface
    {
        return $this->db ?? throw new \RuntimeException('Database connection not set');
    }

    /**
     * @inheritdoc
     */
    public function load(int $id): LinkInterface
    {
        $this->id = $id;
        $realID   = $this->getRealID($id);
        $links    = $this->getDB()->getObjects(
            "SELECT tlink.*, loc.cISOSprache, tlink.cName AS displayName,
                loc.cName AS localizedName,  loc.cTitle AS localizedTitle, loc.cSeo AS linkURL,
                loc.cContent AS content, loc.cMetaDescription AS metaDescription,
                loc.cMetaKeywords AS metaKeywords, loc.cMetaTitle AS metaTitle,
                tseo.kSprache AS languageID, tseo.cSeo AS localizedUrl,
                pld.cDatei AS pluginFileName, tplugin.nStatus AS pluginState,
                pld.cDatei AS handler, pld.cTemplate AS template, pld.cFullscreenTemplate AS fullscreenTemplate,
                GROUP_CONCAT(assoc.linkGroupID) AS linkGroups
            FROM tlink
                JOIN tlinksprache loc
                    ON tlink.kLink = loc.kLink
                JOIN tsprache
                    ON tsprache.cISO = loc.cISOSprache
                LEFT JOIN tseo
                    ON tseo.cKey = 'kLink'
                    AND tseo.kKey = loc.kLink
                    AND tseo.kSprache = tsprache.kSprache
                LEFT JOIN tlinkgroupassociations assoc
                    ON assoc.linkID = loc.kLink
                LEFT JOIN tplugin
                    ON tplugin.kPlugin = tlink.kPlugin
                LEFT JOIN tpluginlinkdatei pld
                    ON tplugin.kPlugin = pld.kPlugin
                    AND tlink.kLink = pld.kLink
                WHERE tlink.kLink = :lid
                GROUP BY tseo.kSprache",
            ['lid' => $realID]
        );
        if (\count($links) === 0) {
            throw new InvalidArgumentException('Provided link id ' . $this->id . ' not found.');
        }
        if ($id !== $realID) {
            foreach ($links as $link) {
                $link->reference = $realID;
            }
        }

        return $this->map($links);
    }

    private function getRealID(int $id): int
    {
        $reference = $this->getDB()->getSingleObject(
            'SELECT `reference` FROM `tlink` WHERE kLink = :lid',
            ['lid' => $id]
        );

        return $reference !== null && (int)$reference->reference > 0
            ? (int)$reference->reference
            : $id;
    }

    public function deref(): void
    {
        $id     = $this->getID();
        $realID = $this->getRealID($id);
        if ($id !== $realID) {
            $this->setID($realID);
        }
    }

    /**
     * @return stdClass[]
     */
    public function getData(): array
    {
        $res = [];
        foreach ($this->getLanguageIDs() as $languageID) {
            $languageCode          = $this->getLanguageCode($languageID);
            $data                  = new stdClass();
            $data->content         = $this->getContent($languageID);
            $data->cContent        = $data->content;
            $data->url             = $this->getURL($languageID);
            $data->languageID      = $languageID;
            $data->languageCode    = $languageCode;
            $data->seo             = $this->getSEO($languageID);
            $data->id              = $this->getID();
            $data->title           = $this->getTitle($languageID);
            $data->metaDescription = $this->getMetaDescription($languageID);
            $data->metaTitle       = $this->getMetaTitle($languageID);
            $data->metaKeywords    = $this->getMetaKeyword($languageID);
            $res[$languageCode]    = $data;
        }

        return $res;
    }

    /**
     * @inheritdoc
     */
    public function map(array $localizedLinks): LinkInterface
    {
        \executeHook(\HOOK_LINK_PRE_MAP, ['data' => $localizedLinks]);
        foreach ($localizedLinks as $link) {
            $link = $this->sanitizeLinkData($link);
            $this->setIdentifier($link->cIdentifier ?? '');
            $this->setParent($link->kVaterLink);
            $this->setPluginID($link->kPlugin);
            $this->setPluginEnabled($link->enabled);
            $this->setLinkGroups(\array_unique(\array_map('\intval', \explode(',', $link->linkGroups))));
            $this->setLinkGroupID($this->linkGroups[0]);
            $this->setLinkType($link->nLinkart);
            $this->setNoFollow($link->cNoFollow === 'Y');
            $this->setCustomerGroups(self::parseSSKAdvanced($link->cKundengruppen));
            $this->setVisibleLoggedInOnly($link->cSichtbarNachLogin === 'Y');
            $this->setPrintButton($link->cDruckButton === 'Y');
            $this->setSort($link->nSort);
            $this->setReference($link->reference);
            $this->setSSL((bool)$link->bSSL);
            $this->setIsFluid((bool)$link->bIsFluid);
            $this->setIsEnabled($this->checkActivationSetting((bool)$link->bIsActive));
            $this->setIsSystem((int)($link->bIsSystem ?? 0) === 1);
            $this->setFileName($link->cDateiname ?? '');
            $this->setLanguageCode($link->cISOSprache, $link->languageID);
            $this->setContent($link->content ?? '', $link->languageID);
            $this->setMetaDescription($link->metaDescription ?? '', $link->languageID);
            $this->setMetaTitle($link->metaTitle ?? '', $link->languageID);
            $this->setMetaKeyword($link->metaKeywords ?? '', $link->languageID);
            $this->setDisplayName($link->displayName ?? '');
            $this->setName($link->localizedName ?? $link->cName, $link->languageID);
            $this->setTitle($link->localizedTitle ?? $link->cName, $link->languageID);
            $this->setLanguageID($link->languageID, $link->languageID);
            $this->setTarget($link->target ?? '_self');
            if ($this->getLinkType() === \LINKTYP_EXTERNE_URL) {
                $this->setSEO($link->linkURL, $link->languageID);
                $this->setURL($link->linkURL, $link->languageID);
            } else {
                $this->setSEO($link->localizedUrl ?? '', $link->languageID);
                $this->setSlug($link->localizedUrl ?? '', $link->languageID);
                if (\EXPERIMENTAL_MULTILANG_SHOP === true && $this->getLinkType() === \LINKTYP_STARTSEITE) {
                    $this->setURL(Shop::getURL(true, $link->languageID) . '/', $link->languageID);
                }
            }
            $this->setHandler($link->handler ?? '');
            $this->setTemplate($link->template ?? $link->fullscreenTemplate ?? '');
            if ($this->id === 0 && isset($link->kLink)) {
                $this->setID((int)$link->kLink);
            }
        }
        if ($this->getLinkType() !== \LINKTYP_EXTERNE_URL) {
            $this->createBySlug();
        }
        $this->setChildLinks($this->buildChildLinks());
        \executeHook(\HOOK_LINK_MAPPED, ['link' => $this]);

        return $this;
    }

    private function sanitizeLinkData(stdClass $link): stdClass
    {
        $link->kLink       = (int)$link->kLink;
        $link->languageID  = (int)$link->languageID;
        $link->kVaterLink  = (int)$link->kVaterLink;
        $link->kPlugin     = (int)$link->kPlugin;
        $link->bSSL        = (int)$link->bSSL;
        $link->nLinkart    = (int)$link->nLinkart;
        $link->nSort       = (int)$link->nSort;
        $link->reference   = (int)$link->reference;
        $link->enabled     = $link->pluginState === null || (int)$link->pluginState === State::ACTIVATED;
        $link->cISOSprache = $link->cISOSprache ?? Shop::getLanguageCode();
        $link->linkGroups  = $link->linkGroups ?? '';
        if ($link->languageID === 0) {
            $link->languageID = $this->currentLanguageID;
        }
        if ($link->bSSL === 2) {
            $link->bSSL = 1;
        }

        return $link;
    }

    /**
     * @inheritdoc
     */
    public function checkVisibility(int $customerGroupID, int $customerID = 0): bool
    {
        $cVis   = $this->visibleLoggedInOnly === false || $customerID > 0;
        $cgVisi = \count($this->customerGroups) === 0 || \in_array($customerGroupID, $this->customerGroups, true);

        $this->isVisible = $cVis && $cgVisi && $this->isEnabled === true;

        return $this->isVisible;
    }

    /**
     * @inheritdoc
     */
    public function getID(): int
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     */
    public function setID(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @inheritdoc
     */
    public function getParent(): int
    {
        return $this->parent;
    }

    /**
     * @inheritdoc
     */
    public function setParent(int $parent): void
    {
        $this->parent = $parent;
    }

    /**
     * @return int[]
     */
    public function getLinkGroups(): array
    {
        return $this->linkGroups;
    }

    /**
     * @inheritdoc
     */
    public function setLinkGroups(array $linkGroups): void
    {
        $this->linkGroups = $linkGroups;
    }

    /**
     * @inheritdoc
     */
    public function getLinkGroupID(): int
    {
        return $this->linkGroupID;
    }

    /**
     * @inheritdoc
     */
    public function setLinkGroupID(int $linkGroupID): void
    {
        $this->linkGroupID = $linkGroupID;
    }

    /**
     * @inheritdoc
     */
    public function getPluginID(): int
    {
        return $this->pluginID;
    }

    /**
     * @inheritdoc
     */
    public function setPluginID(int $pluginID): void
    {
        $this->pluginID = $pluginID;
    }

    /**
     * @inheritdoc
     */
    public function getLinkType(): int
    {
        return $this->linkType;
    }

    /**
     * @inheritdoc
     */
    public function setLinkType(int $linkType): void
    {
        $this->linkType = $linkType;
    }

    /**
     * @inheritdoc
     */
    public function getName(?int $idx = null): string
    {
        return $this->names[$idx ?? $this->currentLanguageID]
            ?? $this->names[$this->fallbackLanguageID]
            ?? '';
    }

    public function getTarget(): string
    {
        return $this->target;
    }

    public function setTarget(string $target): void
    {
        $this->target = $target;
    }

    /**
     * @inheritdoc
     */
    public function getNames(): array
    {
        return $this->names;
    }

    /**
     * @inheritdoc
     */
    public function setName(string $name, ?int $idx = null): void
    {
        $this->names[$idx ?? $this->currentLanguageID] = $name;
    }

    /**
     * @inheritdoc
     */
    public function setNames(array $names): void
    {
        $this->names = $names;
    }

    /**
     * @inheritdoc
     */
    public function getSEOs(): array
    {
        return $this->slugs;
    }

    /**
     * @inheritdoc
     */
    public function getSEO(?int $idx = null): string
    {
        return $this->slugs[$idx ?? $this->currentLanguageID]
            ?? $this->slugs[$this->fallbackLanguageID]
            ?? '';
    }

    /**
     * @inheritdoc
     */
    public function setSEOs(array $seo): void
    {
        $this->slugs = $seo;
    }

    /**
     * @inheritdoc
     */
    public function setSEO(string $url, ?int $idx = null): void
    {
        $this->slugs[$idx ?? $this->currentLanguageID] = $url;
    }

    /**
     * @inheritdoc
     */
    public function getURL(?int $idx = null): string
    {
        return $this->urls[$idx ?? $this->currentLanguageID]
            ?? '/?s=' . $this->getID()
            . '&lang=' . Shop::Lang()->getIsoFromLangID($idx ?? $this->currentLanguageID)->cISO;
    }

    /**
     * @inheritdoc
     */
    public function getTitle(?int $idx = null): string
    {
        return $this->titles[$idx ?? $this->currentLanguageID]
            ?? $this->titles[$this->fallbackLanguageID]
            ?? '';
    }

    /**
     * @inheritdoc
     */
    public function getTitles(): array
    {
        return $this->titles;
    }

    /**
     * @inheritdoc
     */
    public function setTitle(string $title, ?int $idx = null): void
    {
        $this->titles[$idx ?? $this->currentLanguageID] = $title;
    }

    /**
     * @inheritdoc
     */
    public function setTitles(array $title): void
    {
        $this->titles = $title;
    }

    /**
     * @inheritdoc
     */
    public function getCustomerGroups(): array
    {
        return $this->customerGroups;
    }

    /**
     * @inheritdoc
     */
    public function setCustomerGroups(array $customerGroups): void
    {
        $this->customerGroups = $customerGroups;
    }

    /**
     * @inheritdoc
     */
    public function getLanguageCode(?int $idx = null): string
    {
        return $this->languageCodes[$idx ?? $this->currentLanguageID] ?? '';
    }

    /**
     * @inheritdoc
     */
    public function getLanguageCodes(): array
    {
        return $this->languageCodes;
    }

    /**
     * @inheritdoc
     */
    public function setLanguageCode(string $languageCode, ?int $idx = null): void
    {
        $this->languageCodes[$idx ?? $this->currentLanguageID] = $languageCode;
    }

    /**
     * @inheritdoc
     */
    public function setLanguageCodes(array $languageCodes): void
    {
        $this->languageCodes = $languageCodes;
    }

    /**
     * @inheritdoc
     */
    public function getReference(): int
    {
        return $this->reference;
    }

    /**
     * @inheritdoc
     */
    public function setReference(int $reference): void
    {
        $this->reference = $reference;
    }

    /**
     * @inheritdoc
     */
    public function getSort(): int
    {
        return $this->sort;
    }

    /**
     * @inheritdoc
     */
    public function setSort(int $sort): void
    {
        $this->sort = $sort;
    }

    /**
     * @inheritdoc
     */
    public function getSSL(): bool
    {
        return $this->ssl;
    }

    /**
     * @inheritdoc
     */
    public function setSSL(bool $ssl): void
    {
        $this->ssl = $ssl;
    }

    /**
     * @inheritdoc
     */
    public function getNoFollow(): bool
    {
        return $this->noFollow;
    }

    /**
     * @inheritdoc
     */
    public function setNoFollow(bool $noFollow): void
    {
        $this->noFollow = $noFollow;
    }

    /**
     * @inheritdoc
     */
    public function hasPrintButton(): bool
    {
        return $this->printButton;
    }

    public function getPrintButton(): bool
    {
        return $this->hasPrintButton();
    }

    /**
     * @inheritdoc
     */
    public function setPrintButton(bool $printButton): void
    {
        $this->printButton = $printButton;
    }

    /**
     * @inheritdoc
     */
    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    /**
     * @inheritdoc
     */
    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    /**
     * @inheritdoc
     */
    public function getIsEnabled(): bool
    {
        return $this->isEnabled;
    }

    /**
     * @inheritdoc
     */
    public function setIsEnabled(bool $enabled): void
    {
        $this->isEnabled = $enabled;
    }

    /**
     * @inheritdoc
     */
    public function getIsFluid(): bool
    {
        return $this->isFluid;
    }

    /**
     * @inheritdoc
     */
    public function setIsFluid(bool $isFluid): void
    {
        $this->isFluid = $isFluid;
    }

    /**
     * @inheritdoc
     */
    public function getLanguageID(?int $idx = null): int
    {
        return $this->languageIDs[$idx ?? $this->currentLanguageID] ?? 0;
    }

    /**
     * @inheritdoc
     */
    public function setLanguageID(int $languageID, ?int $idx = null): void
    {
        $this->languageIDs[$idx ?? $this->currentLanguageID] = $languageID;
    }

    /**
     * @return int[]
     */
    public function getLanguageIDs(): array
    {
        return $this->languageIDs;
    }

    /**
     * @param int[] $ids
     */
    public function setLanguageIDs(array $ids): void
    {
        $this->languageIDs = \array_map('\intval', $ids);
    }

    /**
     * @inheritdoc
     */
    public function getRedirectCode(): int
    {
        return $this->redirectCode;
    }

    /**
     * @inheritdoc
     */
    public function setRedirectCode(int $redirectCode): void
    {
        $this->redirectCode = $redirectCode;
    }

    /**
     * @inheritdoc
     */
    public function getVisibleLoggedInOnly(): bool
    {
        return $this->visibleLoggedInOnly;
    }

    /**
     * @inheritdoc
     */
    public function setVisibleLoggedInOnly(bool $visibleLoggedInOnly): void
    {
        $this->visibleLoggedInOnly = $visibleLoggedInOnly;
    }

    /**
     * @inheritdoc
     */
    public function getIdentifier(): string
    {
        return $this->identifier ?? '';
    }

    /**
     * @inheritdoc
     */
    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    /**
     * @inheritdoc
     */
    public function getPluginEnabled(): bool
    {
        return $this->pluginEnabled;
    }

    /**
     * @inheritdoc
     */
    public function setPluginEnabled(bool $pluginEnabled): void
    {
        $this->pluginEnabled = $pluginEnabled;
    }

    /**
     * @inheritdoc
     */
    public function getChildLinks(): Collection
    {
        return $this->childLinks;
    }

    /**
     * @inheritdoc
     */
    public function setChildLinks(array|Collection $links): void
    {
        $this->childLinks = \is_array($links) ? \collect($links) : $links;
    }

    /**
     * @inheritdoc
     */
    public function addChildLink(LinkInterface $link): void
    {
        $this->childLinks->push($link);
    }

    /**
     * @inheritdoc
     */
    public function getFileName(): string
    {
        return $this->fileName;
    }

    /**
     * @inheritdoc
     */
    public function setFileName(string $fileName): void
    {
        $this->fileName = $fileName;
    }

    /**
     * @inheritdoc
     */
    public function getContents(): array
    {
        return $this->contents;
    }

    /**
     * @inheritdoc
     */
    public function getContent(?int $idx = null): string
    {
        return $this->contents[$idx ?? $this->currentLanguageID] ?? $this->contents[$this->fallbackLanguageID] ?? '';
    }

    /**
     * @inheritdoc
     */
    public function setContent(string $content, ?int $idx = null): void
    {
        $this->contents[$idx ?? $this->currentLanguageID] = $content;
    }

    /**
     * @inheritdoc
     */
    public function setContents(array $contents): void
    {
        $this->contents = $contents;
    }

    /**
     * @inheritdoc
     */
    public function getMetaTitles(): array
    {
        return $this->metaTitles;
    }

    /**
     * @inheritdoc
     */
    public function getMetaTitle(?int $idx = null): string
    {
        return $this->metaTitles[$idx ?? $this->currentLanguageID]
            ?? $this->metaTitles[$this->fallbackLanguageID]
            ?? '';
    }

    /**
     * @inheritdoc
     */
    public function setMetaTitle(string $metaTitle, ?int $idx = null): void
    {
        $this->metaTitles[$idx ?? $this->currentLanguageID] = $metaTitle;
    }

    /**
     * @inheritdoc
     */
    public function setMetaTitles(array $metaTitles): void
    {
        $this->metaTitles = $metaTitles;
    }

    /**
     * @inheritdoc
     */
    public function getMetaKeyword(?int $idx = null): string
    {
        return $this->metaKeywords[$idx ?? $this->currentLanguageID]
            ?? $this->metaKeywords[$this->fallbackLanguageID]
            ?? '';
    }

    /**
     * @inheritdoc
     */
    public function getMetaKeywords(): array
    {
        return $this->metaKeywords;
    }

    /**
     * @inheritdoc
     */
    public function setMetaKeyword(string $metaKeyword, ?int $idx = null): void
    {
        $this->metaKeywords[$idx ?? $this->currentLanguageID] = $metaKeyword;
    }

    /**
     * @inheritdoc
     */
    public function setMetaKeywords(array $metaKeywords): void
    {
        $this->metaKeywords = $metaKeywords;
    }

    /**
     * @inheritdoc
     */
    public function getMetaDescription(?int $idx = null): string
    {
        return $this->metaDescriptions[$idx ?? $this->currentLanguageID]
            ?? $this->metaDescriptions[$this->fallbackLanguageID]
            ?? '';
    }

    /**
     * @inheritdoc
     */
    public function getMetaDescriptions(): array
    {
        return $this->metaDescriptions;
    }

    /**
     * @inheritdoc
     */
    public function setMetaDescription(string $metaDescription, ?int $idx = null): void
    {
        $this->metaDescriptions[$idx ?? $this->currentLanguageID] = $metaDescription;
    }

    /**
     * @inheritdoc
     */
    public function setMetaDescriptions(array $metaDescriptions): void
    {
        $this->metaDescriptions = $metaDescriptions;
    }

    /**
     * @inheritdoc
     */
    public function isVisible(): bool
    {
        return $this->isVisible;
    }

    /**
     * @inheritdoc
     */
    public function setVisibility(bool $isVisible): void
    {
        $this->isVisible = $isVisible;
    }

    public function isSystem(): bool
    {
        return $this->isSystem;
    }

    public function setIsSystem(bool $isSystem): void
    {
        $this->isSystem = $isSystem;
    }

    /**
     * @inheritdoc
     */
    public function getLevel(): int
    {
        return $this->level;
    }

    /**
     * @inheritdoc
     */
    public function setLevel(int $level): void
    {
        $this->level = $level;
    }

    /**
     * @inheritdoc
     */
    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    /**
     * @inheritdoc
     */
    public function setDisplayName(string $displayName): void
    {
        $this->displayName = $displayName;
    }

    /**
     * @inheritdoc
     */
    public function getHandler(): string
    {
        return $this->handler;
    }

    /**
     * @inheritdoc
     */
    public function setHandler(string $handler): void
    {
        $this->handler = $handler;
    }

    /**
     * @inheritdoc
     */
    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * @inheritdoc
     */
    public function setTemplate(string $template): void
    {
        $this->template = $template;
    }

    /**
     * @inheritdoc
     */
    public function buildChildLinks(): array
    {
        $links  = [];
        $realID = $this->getRealID($this->getID());
        foreach ($this->getDB()->selectAll('tlink', 'kVaterLink', $realID, 'kLink', 'nSort') as $id) {
            $links[] = (new self($this->getDB()))->load((int)$id->kLink);
        }

        return $links;
    }

    /**
     * @inheritdoc
     */
    public function hasDuplicateSpecialLink(): bool
    {
        $group = Shop::Container()->getLinkService()->getAllLinkGroups()->getLinkgroupByTemplate('specialpages');
        if ($group === null) {
            return false;
        }
        $duplicateLinks = $group->getLinks()->filter(function (LinkInterface $link): bool {
            return ($link->getPluginID() === 0
                && $link->getLinkType() === $this->getLinkType()
                && $this->getReference() === 0
                && $link->getID() !== $this->getID()
                && (empty($this->getCustomerGroups())
                    || \in_array(-1, $this->getCustomerGroups(), true)
                    || empty($link->getCustomerGroups())
                    || \array_intersect($link->getCustomerGroups(), $this->getCustomerGroups())
                )
            );
        });

        return $duplicateLinks->isNotEmpty();
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $res       = \get_object_vars($this);
        $res['db'] = '*truncated*';

        return $res;
    }

    private function checkActivationSetting(bool $isActive): bool
    {
        if (!$isActive) {
            return false;
        }
        $conf = Shopsetting::getInstance($this->db)->getAll();

        return match ($this->getLinkType()) {
            \LINKTYP_NEWSLETTER, \LINKTYP_NEWSLETTERARCHIV => $conf['newsletter']['newsletter_active'] === 'Y',
            default                                        => true,
        };
    }
}
