<?php

declare(strict_types=1);

namespace JTL\Services\JTL;

use InvalidArgumentException;
use JTL\Boxes\FactoryInterface;
use JTL\Boxes\Items\BoxInterface;
use JTL\Boxes\Items\Extension;
use JTL\Boxes\Items\Plugin;
use JTL\Boxes\Renderer\RendererInterface;
use JTL\Boxes\Type;
use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Filter\ProductFilter;
use JTL\Filter\Visibility;
use JTL\Plugin\LegacyPluginLoader;
use JTL\Plugin\PluginLoader;
use JTL\Plugin\State;
use JTL\Router\Controller\Backend\BoxController;
use JTL\Session\Frontend;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use stdClass;

use function Functional\first;
use function Functional\group;
use function Functional\map;
use function Functional\sort;
use function Functional\tail;

/**
 * Class BoxService
 * @package JTL\Services\JTL
 */
class BoxService implements BoxServiceInterface
{
    /**
     * @var array<string, BoxInterface[]>
     */
    public array $boxes = [];

    /**
     * unrendered box template file name + data
     *
     * @var array<string, array<int, array<'obj'|'tpl', BoxInterface>>>
     */
    public array $rawData = [];

    /**
     * @var array<int, array<string, bool>>|null
     */
    public ?array $visibilities = null;

    private static ?self $instance = null;

    /**
     * @inheritdoc
     */
    public static function getInstance(
        array $config,
        FactoryInterface $factory,
        DbInterface $db,
        JTLCacheInterface $cache,
        JTLSmarty $smarty,
        RendererInterface $renderer
    ): BoxServiceInterface {
        return self::$instance ?? new self($config, $factory, $db, $cache, $smarty, $renderer);
    }

    /**
     * @inheritdoc
     */
    public function __construct(
        private readonly array $config,
        private readonly FactoryInterface $factory,
        private readonly DbInterface $db,
        private readonly JTLCacheInterface $cache,
        private readonly JTLSmarty $smarty,
        private RendererInterface $renderer
    ) {
        self::$instance = $this;
    }

    /**
     * @inheritdoc
     */
    public function addRecentlyViewed(int $productID, ?int $limit = null): void
    {
        if ($productID <= 0) {
            return;
        }
        if ($limit === null) {
            $limit = (int)$this->config['boxen']['box_zuletztangesehen_anzahl'];
        }
        $lastVisited    = $_SESSION['ZuletztBesuchteArtikel'] ?? [];
        $alreadyPresent = false;
        foreach ($lastVisited as $product) {
            if ($product->kArtikel === $productID) {
                $alreadyPresent = true;
                break;
            }
        }
        if ($alreadyPresent === false) {
            if (\count($lastVisited) >= $limit) {
                $lastVisited = tail($lastVisited);
            }
            $lastVisited[] = (object)['kArtikel' => $productID];
        }
        $_SESSION['ZuletztBesuchteArtikel'] = $lastVisited;
        \executeHook(\HOOK_ARTIKEL_INC_ZULETZTANGESEHEN);
    }

    /**
     * @inheritdoc
     */
    public function getVisibility(int $pageType, bool $global = true): array|bool
    {
        if ($this->visibilities === null) {
            $this->visibilities = $this->getAllVisibilites();
        }

        return $this->visibilities[$pageType]
            ?? ($pageType !== \PAGE_UNBEKANNT && $global
                ? $this->getVisibility(\PAGE_UNBEKANNT)
                : false);
    }

    /**
     * all box visibilites grouped by page type
     *
     * @return array<int, array<string, bool>>
     */
    private function getAllVisibilites(): array
    {
        $cacheID = 'bx_visibilities_all';
        /** @var array<int, array<string, bool>>|false $grouped */
        $grouped = $this->cache->get($cacheID);
        if ($grouped !== false) {
            return $grouped;
        }
        $grouped = \collect($this->db->selectAll('tboxenanzeige', [], []))
            ->groupBy('nSeite')->transform(static function ($data) {
                return \collect($data)->mapWithKeys(static function (stdClass $item): array {
                    return [$item->ePosition => (bool)$item->bAnzeigen];
                });
            })->toArray();
        $this->cache->set($cacheID, $grouped, [\CACHING_GROUP_OBJECT, \CACHING_GROUP_BOX, 'boxes']);

        return $grouped;
    }

    /**
     * @inheritdoc
     */
    public function filterBoxVisibility(int $boxID, int $pageType, string|array $filter = ''): int
    {
        if (\is_array($filter)) {
            $filter = \implode(',', \array_unique($filter));
        }

        return $this->db->update(
            'tboxensichtbar',
            ['kBox', 'kSeite'],
            [$boxID, $pageType],
            (object)['cFilter' => $filter]
        );
    }

    /**
     * @inheritdoc
     */
    public function showBoxes(ProductFilter $pf): bool
    {
        $cf  = $pf->getCategoryFilter();
        $mf  = $pf->getManufacturerFilter();
        $prf = $pf->getPriceRangeFilter();
        $rf  = $pf->getRatingFilter();
        $afc = $pf->getCharacteristicFilterCollection();
        $ssf = $pf->getSearchSpecialFilter();
        $sf  = $pf->searchFilterCompat;

        $invis      = Visibility::SHOW_NEVER;
        $visContent = Visibility::SHOW_CONTENT;

        return (($cf->getVisibility() !== $invis && $cf->getVisibility() !== $visContent)
            || ($mf->getVisibility() !== $invis && $mf->getVisibility() !== $visContent)
            || ($prf->getVisibility() !== $invis && $prf->getVisibility() !== $visContent)
            || ($rf->getVisibility() !== $invis && $rf->getVisibility() !== $visContent)
            || ($afc->getVisibility() !== $invis && $afc->getVisibility() !== $visContent)
            || ($ssf->getVisibility() !== $invis && $ssf->getVisibility() !== $visContent)
            || ($sf->getVisibility() !== $invis && $sf->getVisibility() !== $visContent)
        );
    }

    /**
     * @inheritdoc
     */
    public function getRawData(): array
    {
        return $this->rawData;
    }

    /**
     * @inheritdoc
     */
    public function getBoxes(): array
    {
        return $this->boxes;
    }

    /**
     * @inheritdoc
     */
    public function compatGet(): array
    {
        $boxes = [];
        foreach ($this->rawData as $_type => $_boxes) {
            $boxes[$_type] = [];
            foreach ($_boxes as $_box) {
                $boxes[$_type][] = $_box['obj'];
            }
        }

        return $boxes;
    }

    private function getCurrentPageID(int $pageType): int
    {
        $pageID = 0;
        if ($pageType === \PAGE_ARTIKELLISTE) {
            $pageID = Shop::getState()->categoryID;
        } elseif ($pageType === \PAGE_ARTIKEL) {
            $pageID = Shop::getState()->productID;
        } elseif ($pageType === \PAGE_EIGENE) {
            $pageID = Shop::getState()->linkID;
        } elseif ($pageType === \PAGE_HERSTELLER) {
            $pageID = Shop::getState()->manufacturerID;
        }

        return $pageID;
    }

    /**
     * @inheritdoc
     */
    public function render(array $positionedBoxes, int $pageType): array
    {
        $pageID    = $this->getCurrentPageID($pageType);
        $product   = $this->smarty->getTemplateVars('Artikel');
        $htmlArray = [
            'top'    => null,
            'right'  => null,
            'bottom' => null,
            'left'   => null
        ];
        $this->smarty->assign('BoxenEinstellungen', $this->config)
            ->assign('bBoxenFilterNach', $this->showBoxes(Shop::getProductFilter()))
            ->assign('NettoPreise', Frontend::getCustomerGroup()->getIsMerchant());
        foreach ($positionedBoxes as $_position => $boxes) {
            if (!\is_array($boxes)) {
                $boxes = [];
            }
            $htmlArray[$_position]     = '';
            $this->rawData[$_position] = [];
            /** @var BoxInterface $box */
            foreach ($boxes as $box) {
                $renderClass = $box->getRenderer();
                if ($renderClass !== \get_class($this->renderer)) {
                    $this->renderer = new $renderClass($this->smarty);
                }
                $this->renderer->setBox($box);
                $html = \trim($this->renderer->render($pageType, $pageID));
                $box->setRenderedContent($html);
                $htmlArray[$_position]       .= $html;
                $this->rawData[$_position][] = [
                    'obj' => $box,
                    'tpl' => $box->getTemplateFile()
                ];
            }
        }
        $this->smarty->clearAssign('BoxenEinstellungen');
        // avoid modification of product object on render loop
        if ($product !== null) {
            $this->smarty->assign('Artikel', $product);
        }

        return $htmlArray;
    }

    /**
     * @inheritdoc
     */
    public function buildList(int $pageType = \PAGE_UNBEKANNT, bool $activeOnly = true): array
    {
        $model              = Shop::Container()->getTemplateService()->getActiveTemplate();
        $this->visibilities = $this->getAllVisibilites();
        $visiblePositions   = $this->getVisiblePositions($pageType, $model->getBoxLayout());
        if ($activeOnly === true && \count($visiblePositions) === 0) {
            return [];
        }
        $validPages = \implode(',', BoxController::getValidPageTypes());
        $cacheID    = 'bx_' . $pageType . '_' . (int)$activeOnly . '_' . Shop::getLanguageID();
        $activeSQL  = $activeOnly
            ? ' AND FIND_IN_SET(tboxensichtbar.kSeite, "' . $validPages . '") > 0  
                AND tboxen.ePosition IN (' . \implode(',', $visiblePositions) . ')'
            : '';
        $plgnSQL    = $activeOnly
            ? ' AND (tplugin.nStatus = ' . State::ACTIVATED
            . ' OR tboxvorlage.eTyp IS NULL '
            . "  OR (tboxvorlage.eTyp != '" . Type::PLUGIN . "' AND tboxvorlage.eTyp != '" . Type::EXTENSION . "'))"
            : '';
        /** @var stdClass[]|false $grouped */
        $grouped = $this->cache->get($cacheID);
        if ($grouped === false) {
            $boxData = $this->db->getObjects(
                'SELECT tboxen.kBox, tboxen.kBoxvorlage, tboxen.kCustomID, tboxen.kContainer, 
                       tboxen.cTitel, tboxen.ePosition, tboxensichtbar.kSeite, tboxensichtbar.nSort, 
                       tboxensichtbar.cFilter, tboxvorlage.eTyp, tboxvorlage.cVerfuegbar,
                       tboxvorlage.cName, tboxvorlage.cTemplate, tplugin.nStatus AS pluginStatus,
                       GROUP_CONCAT(tboxensichtbar.nSort) AS sortBypageIDs,
                       GROUP_CONCAT(tboxensichtbar.kSeite) AS pageIDs,
                       GROUP_CONCAT(tboxensichtbar.bAktiv) AS pageVisibilities,                       
                       tsprache.kSprache, tboxsprache.cInhalt, tboxsprache.cTitel
                    FROM tboxen
                    LEFT JOIN tboxensichtbar
                        ON tboxen.kBox = tboxensichtbar.kBox
                    LEFT JOIN tplugin
                        ON tboxen.kCustomID = tplugin.kPlugin
                    LEFT JOIN tboxvorlage
                        ON tboxen.kBoxvorlage = tboxvorlage.kBoxvorlage
                    LEFT JOIN tboxsprache
                        ON tboxsprache.kBox = tboxen.kBox
                    LEFT JOIN tsprache
                        ON tsprache.cISO = tboxsprache.cISO
                    WHERE tboxen.kContainer > -1 '
                . $activeSQL . $plgnSQL . ' 
                    GROUP BY tboxsprache.kBoxSprache, tboxen.kBox, tboxensichtbar.cFilter
                    ORDER BY tboxensichtbar.nSort, tboxen.kBox ASC'
            );
            if (isset($_SESSION['AdminAccount'])) {
                $boxData = map($boxData, static function (stdClass $box): stdClass {
                    $box->cName = \__($box->cName ?? '');

                    return $box;
                });
            }
            $boxData = map($boxData, static function (stdClass $box): stdClass {
                $box->kBox         = (int)$box->kBox;
                $box->kBoxvorlage  = (int)$box->kBoxvorlage;
                $box->kCustomID    = (int)$box->kCustomID;
                $box->kContainer   = (int)$box->kContainer;
                $box->kSeite       = (int)$box->kSeite;
                $box->nSort        = (int)$box->nSort;
                $box->kSprache     = $box->kSprache === null ? null : (int)$box->kSprache;
                $box->pluginStatus = $box->pluginStatus === null ? null : (int)$box->pluginStatus;

                return $box;
            });
            $grouped = group($boxData, fn(stdClass $e): int => (int)$e->kBox);
            $this->cache->set($cacheID, $grouped, [\CACHING_GROUP_OBJECT, \CACHING_GROUP_BOX, 'boxes']);
        }

        return $this->getItems($grouped, $pageType);
    }

    /**
     * @param string[] $templatePositions
     * @return string[]
     */
    private function getVisiblePositions(int $pageType, array $templatePositions): array
    {
        $visiblePositions = [];
        $visibilities     = $this->getVisibility($pageType);
        if (!\is_array($visibilities)) {
            return $visiblePositions;
        }
        foreach ($visibilities as $position => $isVisible) {
            if (isset($templatePositions[$position])) {
                $isVisible = $isVisible && $templatePositions[$position];
            }
            if ($isVisible) {
                $visiblePositions[] = "'" . $position . "'";
            }
        }

        return $visiblePositions;
    }

    /**
     * @param array<int, array<int, stdClass>> $grouped
     * @return array<string, BoxInterface[]>
     */
    private function getItems(array $grouped, int $pageType): array
    {
        $children = [];
        $result   = [];
        foreach ($grouped as $i => $boxes) {
            /** @var stdClass $first */
            $first = first($boxes);
            if ($first->kContainer <= 0) {
                continue;
            }
            $box = $this->factory->getBoxByBaseType($first->kBoxvorlage, $first->eTyp);
            $box->map($boxes);
            $this->loadPluginBox($box);
            if (!isset($children[$first->kContainer])) {
                $children[$first->kContainer] = [];
            }
            $children[$first->kContainer][] = $box;
            unset($grouped[$i]);
        }
        foreach ($grouped as $boxes) {
            /** @var stdClass $first */
            $first = first($boxes);
            $box   = $this->factory->getBoxByBaseType($first->kBoxvorlage, $first->eTyp);
            $box->map($boxes);
            if ($this->loadPluginBox($box) === false) {
                continue;
            }
            if ($box->getType() === Type::CONTAINER) {
                $box->setChildren($children);
            }
            $result[] = $box;
        }
        $result      = sort($result, static function (BoxInterface $first, BoxInterface $second) use ($pageType): int {
            return $first->getSort($pageType) <=> $second->getSort($pageType);
        });
        $this->boxes = group($result, fn(BoxInterface $e): string => $e->getPosition());

        return $this->boxes;
    }

    private function loadPluginBox(BoxInterface $box): bool
    {
        $class = \get_class($box);
        if ($class === Extension::class) {
            $loader = new PluginLoader($this->db, $this->cache);
            try {
                $plugin = $loader->init($box->getCustomID());
            } catch (InvalidArgumentException) {
                return false;
            }
            $box->setTemplateFile($plugin->getPaths()->getFrontendPath() . $box->getTemplateFile());
            $box->setExtension($plugin);
            $box->setPlugin($plugin);

            return true;
        }

        if ($class === Plugin::class) {
            $loader = new LegacyPluginLoader($this->db, $this->cache);
            try {
                $plugin = $loader->init($box->getCustomID());
            } catch (InvalidArgumentException) {
                return false;
            }
            $box->setTemplateFile(
                $plugin->getPaths()->getFrontendPath()
                . \PFAD_PLUGIN_BOXEN
                . $box->getTemplateFile()
            );
            $box->setPlugin($plugin);
        }

        return true;
    }
}
