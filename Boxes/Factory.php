<?php

declare(strict_types=1);

namespace JTL\Boxes;

use JTL\Boxes\Items\BestsellingProducts;
use JTL\Boxes\Items\BoxDefault;
use JTL\Boxes\Items\BoxInterface;
use JTL\Boxes\Items\Cart;
use JTL\Boxes\Items\CompareList;
use JTL\Boxes\Items\Container;
use JTL\Boxes\Items\DirectPurchase;
use JTL\Boxes\Items\Extension;
use JTL\Boxes\Items\FilterAttribute;
use JTL\Boxes\Items\FilterAvailability;
use JTL\Boxes\Items\FilterCategory;
use JTL\Boxes\Items\FilterItem;
use JTL\Boxes\Items\FilterManufacturer;
use JTL\Boxes\Items\FilterPricerange;
use JTL\Boxes\Items\FilterRating;
use JTL\Boxes\Items\FilterSearch;
use JTL\Boxes\Items\LinkGroup;
use JTL\Boxes\Items\Login;
use JTL\Boxes\Items\Manufacturer;
use JTL\Boxes\Items\NewProducts;
use JTL\Boxes\Items\NewsCategories;
use JTL\Boxes\Items\NewsCurrentMonth;
use JTL\Boxes\Items\Plain;
use JTL\Boxes\Items\Plugin;
use JTL\Boxes\Items\ProductCategories;
use JTL\Boxes\Items\RecentlyViewedProducts;
use JTL\Boxes\Items\SearchCloud;
use JTL\Boxes\Items\SpecialOffers;
use JTL\Boxes\Items\TopOffers;
use JTL\Boxes\Items\TopRatedProducts;
use JTL\Boxes\Items\UpcomingProducts;
use JTL\Boxes\Items\Wishlist;

/**
 * Class Factory
 * @package JTL\Boxes
 */
readonly class Factory implements FactoryInterface
{
    public function __construct(private array $config)
    {
    }

    /**
     * @inheritdoc
     */
    public function getBoxByBaseType(int $baseType, ?string $type = null): BoxInterface
    {
        $box = match ($baseType) {
            \BOX_BESTSELLER             => new BestsellingProducts($this->config),
            \BOX_CONTAINER              => new Container($this->config),
            \BOX_IN_KUERZE_VERFUEGBAR   => new UpcomingProducts($this->config),
            \BOX_ZULETZT_ANGESEHEN      => new RecentlyViewedProducts($this->config),
            \BOX_NEUE_IM_SORTIMENT      => new NewProducts($this->config),
            \BOX_TOP_ANGEBOT            => new TopOffers($this->config),
            \BOX_SONDERANGEBOT          => new SpecialOffers($this->config),
            \BOX_LOGIN                  => new Login($this->config),
            \BOX_KATEGORIEN             => new ProductCategories($this->config),
            \BOX_NEWS_KATEGORIEN        => new NewsCategories($this->config),
            \BOX_NEWS_AKTUELLER_MONAT   => new NewsCurrentMonth($this->config),
            \BOX_WUNSCHLISTE            => new Wishlist($this->config),
            \BOX_WARENKORB              => new Cart($this->config),
            \BOX_SCHNELLKAUF            => new DirectPurchase($this->config),
            \BOX_VERGLEICHSLISTE        => new CompareList($this->config),
            \BOX_EIGENE_BOX_MIT_RAHMEN,
            \BOX_EIGENE_BOX_OHNE_RAHMEN => new Plain($this->config),
            \BOX_LINKGRUPPE             => new LinkGroup($this->config),
            \BOX_HERSTELLER             => new Manufacturer($this->config),
            \BOX_FILTER_MERKMALE        => new FilterAttribute($this->config),
            \BOX_FILTER_KATEGORIE       => new FilterCategory($this->config),
            \BOX_FILTER_HERSTELLER      => new FilterManufacturer($this->config),
            \BOX_FILTER_PREISSPANNE     => new FilterPricerange($this->config),
            \BOX_FILTER_BEWERTUNG       => new FilterRating($this->config),
            \BOX_FILTER_SUCHE           => new FilterSearch($this->config),
            \BOX_FILTER_SUCHSPECIAL     => new FilterItem($this->config),
            \BOX_FILTER_AVAILABILITY    => new FilterAvailability($this->config),
            \BOX_TOP_BEWERTET           => new TopRatedProducts($this->config),
            \BOX_SUCHWOLKE              => new SearchCloud($this->config),
            default                     => null,
        };
        if ($box !== null) {
            return $box;
        }
        if ($type === Type::PLUGIN) {
            $box = new Plugin($this->config);
        } elseif ($type === Type::EXTENSION) {
            $box = new Extension($this->config);
        } else {
            $box = new BoxDefault($this->config);
        }

        return $box;
    }
}
