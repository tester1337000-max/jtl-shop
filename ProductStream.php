<?php

declare(strict_types=1);

namespace JTL\OPC\Portlets\ProductStream;

use Illuminate\Support\Collection;
use JTL\Catalog\Product\Artikel;
use JTL\Filter\Config;
use JTL\Filter\FilterInterface;
use JTL\Filter\Items\Manufacturer;
use JTL\Filter\ProductFilter;
use JTL\Filter\Type;
use JTL\Helpers\Product;
use JTL\Helpers\Text;
use JTL\OPC\InputType;
use JTL\OPC\Portlet;
use JTL\OPC\PortletInstance;
use JTL\Session\Frontend;
use JTL\Shop;

/**
 * Class ProductStream
 * @package JTL\OPC\Portlets
 */
class ProductStream extends Portlet
{
    /**
     * @inheritdoc
     */
    public function getPropertyDesc(): array
    {
        $displayCountBase = [
            'type'             => InputType::NUMBER,
            'width'            => 25,
            'constraintProp'   => 'listStyle',
            'constraintValues' => ['simpleSlider', 'slider'],
        ];
        $sortingTypes     = [
            \SEARCH_SORT_STANDARD,
            \SEARCH_SORT_NAME_ASC,
            \SEARCH_SORT_NAME_DESC,
            \SEARCH_SORT_PRICE_ASC,
            \SEARCH_SORT_PRICE_DESC,
            \SEARCH_SORT_EAN,
            \SEARCH_SORT_NEWEST_FIRST,
            \SEARCH_SORT_PRODUCTNO,
            \SEARCH_SORT_WEIGHT,
            \SEARCH_SORT_DATEOFISSUE,
            \SEARCH_SORT_BESTSELLER,
            \SEARCH_SORT_RATING,
        ];
        $sortingOptions   = [];
        foreach ($sortingTypes as $sortingType) {
            $sortingOptions[$sortingType] = \__('artikeluebersicht_artikelsortierung_value(' . $sortingType . ')');
        }

        return [
            'listStyle'      => [
                'type'    => InputType::SELECT,
                'label'   => \__('presentation'),
                'width'   => 25,
                'options' => [
                    'gallery'      => \__('presentationGallery'),
                    'list'         => \__('presentationList'),
                    'simpleSlider' => \__('presentationSimpleSlider'),
                    'slider'       => \__('presentationSlider'),
                    'box-slider'   => \__('presentationBoxSlider'),
                ],
                'default' => 'gallery',
            ],
            'maxProducts'    => [
                'type'     => InputType::NUMBER,
                'label'    => \__('maxProducts'),
                'desc'     => \__('maxProductsDesc'),
                'width'    => 25,
                'default'  => 15,
                'required' => true,
            ],
            'source'         => [
                'type'     => InputType::SELECT,
                'label'    => \__('productSource'),
                'width'    => 25,
                'options'  => [
                    'filter'   => \__('productSourceFiltering'),
                    'explicit' => \__('productSourceExplicit'),
                ],
                'default'  => 'filter',
                'required' => true,
            ],
            'sorting'        => [
                'type'     => InputType::SELECT,
                'label'    => \__('sorting'),
                'width'    => 25,
                'options'  => $sortingOptions,
                'default'  => \SEARCH_SORT_STANDARD,
                'required' => true,
            ],
            'displayCountSM' => [
                ... $displayCountBase,
                'label'   => \__('displayCountSM'),
                'default' => 2,
                'desc'    => \__('displayCountDesc'),
            ],
            'displayCountMD' => [
                ... $displayCountBase,
                'label'   => \__('displayCountMD'),
                'default' => 3,
                'desc'    => \__('displayCountDesc'),
            ],
            'displayCountLG' => [
                ... $displayCountBase,
                'label'   => \__('displayCountLG'),
                'default' => 5,
                'desc'    => \__('displayCountDesc'),
            ],
            'displayCountXL' => [
                ... $displayCountBase,
                'label'   => \__('displayCountXL'),
                'default' => 7,
                'desc'    => \__('displayCountDesc'),
            ],
            'search'         => [
                'type'             => InputType::SEARCH,
                'label'            => '',
                'placeholder'      => \__('searchFilters'),
                'width'            => 100,
                'constraintProp'   => 'source',
                'constraintValues' => ['filter'],
            ],
            'filters'        => [
                'type'             => InputType::FILTER,
                'label'            => \__('itemFilter'),
                'default'          => [],
                'searcher'         => 'search',
                'constraintProp'   => 'source',
                'constraintValues' => ['filter'],
            ],
            'searchExplicit' => [
                'type'             => InputType::SEARCH,
                'label'            => '',
                'placeholder'      => \__('labelSearchProduct'),
                'width'            => 100,
                'constraintProp'   => 'source',
                'constraintValues' => ['explicit'],
            ],
            'productIds'     => [
                'type'             => InputType::SEARCHPICKER,
                'searcher'         => 'searchExplicit',
                'dataIoFuncName'   => 'getProducts',
                'keyName'          => 'kArtikel',
                'constraintProp'   => 'source',
                'constraintValues' => ['explicit'],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function getPropertyTabs(): array
    {
        return [
            \__('Styles') => 'styles',
        ];
    }

    /**
     * @param PortletInstance $instance
     * @return int[]
     */
    public function getExplicitProductIds(PortletInstance $instance): array
    {
        return Text::parseSSKint($instance->getProperty('productIds'));
    }

    /**
     * @param PortletInstance $instance
     * @return Collection<int, int>
     */
    public function getFilteredProductIds(PortletInstance $instance): Collection
    {
        if ($instance->getProperty('source') === 'explicit') {
            return (new Collection($this->getExplicitProductIds($instance)))
                ->slice(0, $instance->getProperty('maxProducts'));
        }

        $params         = [
            'MerkmalFilter_arr'   => [],
            'SuchFilter_arr'      => [],
            'SuchFilter'          => [],
            'manufacturerFilters' => []
        ];
        $enabledFilters = $instance->getProperty('filters');
        $pf             = new ProductFilter(
            Config::getDefault(),
            Shop::Container()->getDB(),
            Shop::Container()->getCache()
        );
        $service        = Shop::Container()->getOPC();
        $pf->getBaseState()->init(0);
        /** @var array{class: class-string<FilterInterface>, value: mixed} $enabledFilter */
        foreach ($enabledFilters as $enabledFilter) {
            $service->getFilterClassParamMapping($enabledFilter['class'], $params, $enabledFilter['value'], $pf);
        }
        $service->overrideConfig($pf);
        $pf->initStates($params);
        foreach ($pf->getActiveFilters() as $filter) {
            if ($filter->getClassName() !== Manufacturer::class) {
                $filter->setType(Type::AND);
            }
        }

        $sorting = (int)$instance->getProperty('sorting');
        if (empty($sorting)) {
            $sorting = \SEARCH_SORT_STANDARD;
        }
        $pf->getSorting()->setActiveSortingType($sorting);

        return $pf->getProductKeys()->slice(0, $instance->getProperty('maxProducts'));
    }

    /**
     * @param PortletInstance $instance
     * @return Artikel[]
     */
    public function getFilteredProducts(PortletInstance $instance): array
    {
        $products       = [];
        $defaultOptions = Artikel::getDefaultOptions();
        $db             = Shop::Container()->getDB();
        $cache          = Shop::Container()->getCache();
        $customerGroup  = Frontend::getCustomerGroup();
        $currency       = Frontend::getCurrency();
        foreach ($this->getFilteredProductIds($instance) as $productID) {
            $product = new Artikel($db, $customerGroup, $currency, $cache);
            $product->fuelleArtikel($productID, $defaultOptions);
            $products[] = $product;
        }

        return Product::separateByAvailability($products);
    }

    public function rendersForms(PortletInstance $instance): bool
    {
        $listStyle = $instance->getProperty('listStyle');

        return $listStyle === 'gallery' || $listStyle === 'list';
    }
}
