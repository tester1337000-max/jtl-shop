<?php

declare(strict_types=1);

namespace JTL\Services\JTL;

use JTL\Boxes\FactoryInterface;
use JTL\Boxes\Items\BoxInterface;
use JTL\Boxes\Renderer\RendererInterface;
use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Filter\ProductFilter;
use JTL\Smarty\JTLSmarty;

/**
 * Interface BoxServiceInterface
 * @package JTL\Services\JTL
 */
interface BoxServiceInterface
{
    /**
     * @param array<string, array<string, mixed>> $config
     * @param FactoryInterface                    $factory
     * @param DbInterface                         $db
     * @param JTLCacheInterface                   $cache
     * @param JTLSmarty                           $smarty
     * @param RendererInterface                   $renderer
     * @return BoxServiceInterface
     */
    public static function getInstance(
        array $config,
        FactoryInterface $factory,
        DbInterface $db,
        JTLCacheInterface $cache,
        JTLSmarty $smarty,
        RendererInterface $renderer
    ): BoxServiceInterface;

    /**
     * @param array<string, array<string, mixed>> $config
     * @param FactoryInterface                    $factory
     * @param DbInterface                         $db
     * @param JTLCacheInterface                   $cache
     * @param JTLSmarty                           $smarty
     * @param RendererInterface                   $renderer
     */
    public function __construct(
        array $config,
        FactoryInterface $factory,
        DbInterface $db,
        JTLCacheInterface $cache,
        JTLSmarty $smarty,
        RendererInterface $renderer
    );

    /**
     * @param int      $productID
     * @param int|null $limit
     */
    public function addRecentlyViewed(int $productID, ?int $limit = null): void;

    /**
     * @param int  $pageType
     * @param bool $global
     * @return array<string, bool>|bool
     */
    public function getVisibility(int $pageType, bool $global = true): array|bool;

    /**
     * @param int             $boxID
     * @param int             $pageType
     * @param string|string[] $filter
     * @return int
     */
    public function filterBoxVisibility(int $boxID, int $pageType, string|array $filter = ''): int;

    /**
     * @param ProductFilter $pf
     * @return bool
     */
    public function showBoxes(ProductFilter $pf): bool;

    /**
     * get raw data from visible boxes
     * to allow custom renderes
     *
     * @return array<string, \stdClass[]>
     */
    public function getRawData(): array;

    /**
     * @return array<string, BoxInterface[]>
     */
    public function getBoxes(): array;

    /**
     * compatibility layer for gibBoxen() which returns unrendered content
     *
     * @return array<string, array<int, BoxInterface>>
     */
    public function compatGet(): array;

    /**
     * @param array<string, BoxInterface[]> $positionedBoxes
     * @param int                           $pageType
     * @return array<'left'|'right'|'top'|'bottom', string>
     * @throws \Exception
     * @throws \SmartyException
     */
    public function render(array $positionedBoxes, int $pageType): array;

    /**
     * @param int  $pageType
     * @param bool $activeOnly
     * @return array<string, BoxInterface[]>
     */
    public function buildList(int $pageType = \PAGE_UNBEKANNT, bool $activeOnly = true): array;
}
