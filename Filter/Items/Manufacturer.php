<?php

declare(strict_types=1);

namespace JTL\Filter\Items;

use JTL\Filter\FilterInterface;
use JTL\Filter\ProductFilter;
use JTL\Filter\States\BaseManufacturer;
use JTL\Filter\Type;
use JTL\Shop;

/**
 * Class Manufacturer
 * @package JTL\Filter\Items
 */
class Manufacturer extends BaseManufacturer
{
    public function __construct(ProductFilter $productFilter)
    {
        parent::__construct($productFilter);
        $this->setIsCustom(false)
            ->setUrlParam(\QUERY_PARAM_MANUFACTURER_FILTER)
            ->setUrlParamSEO(\SEP_HST)
            ->setVisibility($this->getConfig('navigationsfilter')['allgemein_herstellerfilter_benutzen'])
            ->setFrontendName(Shop::isAdmin() ? \__('filterManufacturers') : Shop::Lang()->get('allManufacturers'))
            ->setFilterName(Shop::isAdmin() ? \__('manufacturers') : Shop::Lang()->get('manufacturers'))
            ->setType(
                $this->getConfig('navigationsfilter')['manufacturer_filter_type'] === 'O'
                    ? Type::OR
                    : Type::AND
            );
    }

    /**
     * @inheritdoc
     */
    public function setSeo(array $languages): FilterInterface
    {
        $this->setCurrentLanguageID($this->getLanguageID());
        parent::setSeo($languages);
        foreach ($this->slugs as $langID => $slug) {
            $this->cSeo[$langID] = $slug;
        }

        return $this;
    }

    /**
     * @param array<mixed>|int $value
     * @return $this
     */
    public function setValue($value): FilterInterface
    {
        $this->value = \is_array($value) ? \array_map('\intval', $value) : (int)$value;

        return $this;
    }
}
