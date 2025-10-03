<?php

declare(strict_types=1);

namespace JTL\Filter\Items;

use JTL\Filter\AbstractFilter;
use JTL\Filter\FilterInterface;
use JTL\Filter\Option;
use JTL\Filter\ProductFilter;
use JTL\Settings\Option\Overview;
use JTL\Settings\Settings;
use JTL\Shop;

/**
 * Class Limit
 * @package JTL\Filter\Items
 */
class Limit extends AbstractFilter
{
    public function __construct(ProductFilter $productFilter)
    {
        parent::__construct($productFilter);
        $this->setIsCustom(false)
            ->setUrlParam(\QUERY_PARAM_PRODUCTS_PER_PAGE)
            ->setFrontendName(Shop::Lang()->get('productsPerPage', 'productOverview'))
            ->setFilterName($this->getFrontendName());
    }

    public function getProductsPerPageLimit(): int
    {
        if ((int)$this->productFilter?->getProductLimit() !== 0) {
            $limit = (int)$this->productFilter?->getProductLimit();
        } elseif (!empty($_SESSION['ArtikelProSeite'])) {
            $limit = $_SESSION['ArtikelProSeite'];
        } elseif (!empty($_SESSION['oErweiterteDarstellung']->nAnzahlArtikel)) {
            $limit = $_SESSION['oErweiterteDarstellung']->nAnzahlArtikel;
        } else {
            $limit = Settings::intValue(Overview::PRODUCTS_PER_PAGE) ?: 20;
        }

        return \min((int)$limit, \ARTICLES_PER_PAGE_HARD_LIMIT);
    }

    /**
     * @inheritdoc
     */
    public function setSeo(array $languages): FilterInterface
    {
        return $this;
    }

    /**
     * @inheritdoc
     * @return array{}
     */
    public function getSQLJoin(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getOptions($mixed = null): array
    {
        if ($this->options !== null) {
            return $this->options;
        }
        $options          = [];
        $additionalFilter = new self($this->getProductFilter());
        $params           = $this->getProductFilter()->getParams();
        $view             = $this->getProductFilter()->getMetaData()
            ->getExtendedView($params['nDarstellung'])->nDarstellung;
        $optionIdx        = $view === \ERWDARSTELLUNG_ANSICHT_LISTE
            ? 'products_per_page_list'
            : 'products_per_page_gallery';
        $limitOptions     = \explode(',', $this->getConfig('artikeluebersicht')[$optionIdx]);
        $activeValue      = $_SESSION['ArtikelProSeite'] ?? $this->getProductsPerPageLimit();
        foreach ($limitOptions as $i => $limitOption) {
            $limitOption = (int)\trim($limitOption);
            $name        = $limitOption > 0 ? $limitOption : Shop::Lang()->get('showAll');
            $opt         = new Option();
            $opt->setIsActive($activeValue === $limitOption);
            $opt->setURL($this->getProductFilter()->getFilterURL()->getURL($additionalFilter->init($limitOption)));
            $opt->setType($this->getType());
            $opt->setClassName($this->getClassName());
            $opt->setParam($this->getUrlParam());
            $opt->setName((string)$name);
            $opt->setValue($limitOption);
            $opt->setSort($i);
            $options[] = $opt;
        }
        $this->options = $options;

        return $options;
    }
}
