<?php

declare(strict_types=1);

namespace JTL\Filter\Items;

use JTL\Filter\AbstractFilter;
use JTL\Filter\FilterInterface;
use JTL\Filter\Join;
use JTL\Filter\Option;
use JTL\Filter\ProductFilter;
use JTL\Filter\StateSQL;
use JTL\Filter\Type;
use JTL\Helpers\SearchSpecial as Helper;
use JTL\MagicCompatibilityTrait;
use JTL\Session\Frontend;
use JTL\Shop;

/**
 * Class SearchSpecial
 * @package JTL\Filter\Items
 */
class SearchSpecial extends AbstractFilter
{
    use MagicCompatibilityTrait;

    /**
     * @var array<string, string>
     */
    public static array $mapping = [
        'cName' => 'Name',
        'kKey'  => 'ValueCompat'
    ];

    public function __construct(ProductFilter $productFilter)
    {
        parent::__construct($productFilter);
        $this->setIsCustom(false)
            ->setUrlParam(\QUERY_PARAM_SEARCH_SPECIAL_FILTER)
            ->setFrontendName(Shop::isAdmin() ? \__('filterSearchSpecial') : Shop::Lang()->get('specificProducts'))
            ->setFilterName($this->getFrontendName())
            ->setVisibility($this->getConfig('navigationsfilter')['allgemein_suchspecialfilter_benutzen'])
            ->setType(
                $this->getConfig('navigationsfilter')['search_special_filter_type'] === 'O'
                    ? Type::OR
                    : Type::AND
            );
    }

    /**
     * @inheritdoc
     */
    public function setValue($value): FilterInterface
    {
        $this->value = \is_array($value) ? \array_map('\intval', $value) : (int)$value;

        return $this;
    }

    /**
     * @param array<mixed>|int|string $value
     * @return $this
     */
    public function setValueCompat($value): FilterInterface
    {
        $this->value = [$value];

        return $this;
    }

    /**
     * @return int
     */
    public function getValueCompat()
    {
        return \is_array($this->value) ? $this->value[0] : $this->value;
    }

    /**
     * @inheritdoc
     */
    public function setSeo(array $languages): FilterInterface
    {
        $val = $this->getValue();
        $ok  = (\is_numeric($val) && $val > 0) || (\is_array($val) && \count($val) > 0);
        if (!$ok) {
            return $this;
        }
        if (!\is_array($val)) {
            $val = [$val];
        }
        $helper   = new Helper($this->getProductFilter()->getDB(), $this->getProductFilter()->getCache());
        $allSlugs = $helper->getAllSlugs();
        if (isset($allSlugs[$val[0]])) {
            $this->cSeo = $allSlugs[$val[0]];
        } else {
            foreach ($languages as $language) {
                $this->cSeo[$language->kSprache] = '';
            }
        }
        $name = match ($val[0]) {
            \SEARCHSPECIALS_BESTSELLER       => Shop::Lang()->get('bestsellers'),
            \SEARCHSPECIALS_SPECIALOFFERS    => Shop::Lang()->get('specialOffers'),
            \SEARCHSPECIALS_NEWPRODUCTS      => Shop::Lang()->get('newProducts'),
            \SEARCHSPECIALS_TOPOFFERS        => Shop::Lang()->get('topOffers'),
            \SEARCHSPECIALS_UPCOMINGPRODUCTS => Shop::Lang()->get('upcomingProducts'),
            \SEARCHSPECIALS_TOPREVIEWS       => Shop::Lang()->get('topReviews'),
            default                          => null
        };
        if ($name === null) { // invalid search special ID
            return $this->handInvalidID();
        }
        $this->setName($name);

        return $this;
    }

    private function handInvalidID(): self
    {
        $this->notFound                   = true;
        Shop::$is404                      = true;
        Shop::$kSuchspecial               = 0;
        Shop::getState()->is404           = true;
        Shop::getState()->searchSpecialID = 0;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getPrimaryKeyRow(): string
    {
        return 'kKey';
    }

    /**
     * @inheritdoc
     */
    public function getSQLCondition(): string
    {
        $or         = $this->getType() === Type::OR;
        $conf       = $this->getConfig();
        $conditions = [];
        $values     = $this->getValue();
        if (!\is_array($values)) {
            $values = [$values];
        }
        foreach ($values as $value) {
            switch ($value) {
                case \SEARCHSPECIALS_BESTSELLER:
                    $conditions[] = 'tbestseller.isBestseller = 1';
                    break;

                case \SEARCHSPECIALS_SPECIALOFFERS:
                    if ($this->getProductFilter()->hasSearchSpecial()) {
                        break;
                    }
                    $tasp = 'tartikelsonderpreis';
                    $tsp  = 'tsonderpreise';
                    if (!$this->getProductFilter()->hasPriceRangeFilter()) {
                        $tasp = 'tasp';
                        $tsp  = 'tsp';
                    }
                    $conditions[] = $tasp . ' .kArtikel = tartikel.kArtikel
                                        AND ' . $tasp . ".cAktiv = 'Y' 
                                        AND " . $tasp . '.dStart <= NOW()
                                        AND (' . $tasp . '.nIstDatum = 0
                                            OR ' . $tasp . '.dEnde >= CURDATE()
                                            OR ' . $tasp . '.dEnde IS NULL)
                                        AND (' . $tasp . '.nIstAnzahl = 0
                                            OR ' . $tasp . '.nAnzahl <= tartikel.fLagerbestand)
                                        AND ' . $tsp . ' .kKundengruppe = ' . Frontend::getCustomerGroup()->getID();
                    break;

                case \SEARCHSPECIALS_NEWPRODUCTS:
                    $days = ($d = $conf['boxen']['box_neuimsortiment_alter_tage']) > 0
                        ? (int)$d
                        : 30;

                    $conditions[] = "tartikel.cNeu = 'Y' 
                                AND DATE_SUB(NOW(),INTERVAL " . $days . ' DAY) < tartikel.dErstellt';
                    break;

                case \SEARCHSPECIALS_TOPOFFERS:
                    $conditions[] = "tartikel.cTopArtikel = 'Y'";
                    break;

                case \SEARCHSPECIALS_UPCOMINGPRODUCTS:
                    $conditions[] = 'NOW() < tartikel.dErscheinungsdatum';
                    break;

                case \SEARCHSPECIALS_TOPREVIEWS:
                    if (!$this->getProductFilter()->hasRatingFilter()) {
                        $minStars     = ($m = $conf['boxen']['boxen_topbewertet_minsterne']) > 0
                            ? (int)$m
                            : 4;
                        $conditions[] = 'ROUND(taex.fDurchschnittsBewertung) >= ' . $minStars;
                    }
                    break;

                default:
                    break;
            }
        }
        $conditions = \array_map(static fn($e): string => '(' . $e . ')', $conditions);

        return \count($conditions) > 0
            ? '(' . \implode($or === true ? ' OR ' : ' AND ', $conditions) . ')'
            : '';
    }

    /**
     * @inheritdoc
     * @return Join[]
     */
    public function getSQLJoin(): array
    {
        $joins     = [];
        $values    = $this->getValue();
        $joinType  = $this->getType() === Type::AND
            ? 'JOIN'
            : 'LEFT JOIN';
        $baseValue = $this->getProductFilter()->getSearchSpecial()->getValue();
        if (!\is_array($values)) {
            $values = [$values];
        }
        foreach ($values as $value) {
            switch ($value) {
                case \SEARCHSPECIALS_BESTSELLER:
                    if ($baseValue === $value) {
                        break;
                    }
                    $joins[] = (new Join())
                        ->setType($joinType)
                        ->setTable('tbestseller')
                        ->setOn('tbestseller.kArtikel = tartikel.kArtikel')
                        ->setOrigin(__CLASS__);
                    break;

                case \SEARCHSPECIALS_SPECIALOFFERS:
                    if ($baseValue === $value) {
                        break;
                    }
                    if (!$this->getProductFilter()->hasPriceRangeFilter()) {
                        $joins[] = (new Join())
                            ->setType($joinType)
                            ->setTable('tartikelsonderpreis AS tasp')
                            ->setOn('tasp.kArtikel = tartikel.kArtikel')
                            ->setOrigin(__CLASS__);
                        $joins[] = (new Join())
                            ->setType($joinType)
                            ->setTable('tsonderpreise AS tsp')
                            ->setOn('tsp.kArtikelSonderpreis = tasp.kArtikelSonderpreis')
                            ->setOrigin(__CLASS__);
                    }
                    break;

                case \SEARCHSPECIALS_TOPREVIEWS:
                    if ($baseValue === $value) {
                        break;
                    }
                    if (!$this->getProductFilter()->hasRatingFilter()) {
                        $joins[] = (new Join())
                            ->setType($joinType)
                            ->setTable('tartikelext AS taex ')
                            ->setOn('taex.kArtikel = tartikel.kArtikel')
                            ->setOrigin(__CLASS__);
                    }
                    break;

                case \SEARCHSPECIALS_NEWPRODUCTS:
                case \SEARCHSPECIALS_TOPOFFERS:
                case \SEARCHSPECIALS_UPCOMINGPRODUCTS:
                default:
                    break;
            }
        }

        return $joins;
    }

    /**
     * @inheritdoc
     */
    public function getOptions($mixed = null): array
    {
        if ($this->getConfig('navigationsfilter')['allgemein_suchspecialfilter_benutzen'] === 'N') {
            $this->options = [];
        }
        if ($this->options !== null) {
            return $this->options;
        }
        $baseValue        = $this->getProductFilter()->getSearchSpecial()->getValue();
        $name             = '';
        $options          = [];
        $additionalFilter = new self($this->getProductFilter());
        $ignore           = $this->getType() === Type::OR
            ? $this->getClassName()
            : null;
        $state            = (new StateSQL())->from($this->getProductFilter()->getCurrentStateData($ignore));
        $cacheID          = $this->getCacheID($this->getProductFilter()->getFilterSQL()->getBaseQuery($state))
            . '_' . $this->getProductFilter()->getFilterConfig()->getLanguageID();
        $wasCached        = true;
        /** @var false|array<int<1, 6>, int> $cached */
        $cached = $this->getProductFilter()->getCache()->get($cacheID);
        if ($cached === false) {
            $cached    = [];
            $wasCached = false;
        }
        for ($i = 1; $i < 7; ++$i) {
            $state = (new StateSQL())->from($this->getProductFilter()->getCurrentStateData($ignore));
            $state->setSelect(['tartikel.kArtikel']);
            $state->setOrderBy(null);
            $state->setLimit('');
            $state->setGroupBy(['tartikel.kArtikel']);
            switch ($i) {
                case \SEARCHSPECIALS_BESTSELLER:
                    $name = Shop::Lang()->get('bestsellers');

                    $state->addJoin(
                        (new Join())
                            ->setType('JOIN')
                            ->setTable('tbestseller')
                            ->setOn('tbestseller.kArtikel = tartikel.kArtikel')
                            ->setOrigin(__CLASS__)
                    );
                    $state->addCondition('tbestseller.isBestseller = 1');
                    break;
                case \SEARCHSPECIALS_SPECIALOFFERS:
                    $name = Shop::Lang()->get('specialOffer');
                    $state->addJoin(
                        (new Join())
                            ->setType('JOIN')
                            ->setTable('tartikelsonderpreis')
                            ->setOn('tartikelsonderpreis.kArtikel = tartikel.kArtikel')
                            ->setOrigin(__CLASS__)
                    );
                    $state->addJoin(
                        (new Join())
                            ->setType('JOIN')
                            ->setTable('tsonderpreise')
                            ->setOn('tsonderpreise.kArtikelSonderpreis = tartikelsonderpreis.kArtikelSonderpreis')
                            ->setOrigin(__CLASS__)
                    );
                    $tsonderpreise = 'tsonderpreise';
                    $state->addCondition(
                        "tartikelsonderpreis.cAktiv = 'Y' 
                        AND tartikelsonderpreis.dStart <= NOW()"
                    );
                    $state->addCondition(
                        '(tartikelsonderpreis.nIstDatum = 0
                            OR tartikelsonderpreis.dEnde IS NULL
                            OR tartikelsonderpreis.dEnde >= CURDATE())'
                    );
                    $state->addCondition(
                        '(tartikelsonderpreis.nIstAnzahl = 0
                            OR tartikelsonderpreis.nAnzahl <= tartikel.fLagerbestand)'
                    );
                    $state->addCondition($tsonderpreise . '.kKundengruppe = ' . $this->getCustomerGroupID());
                    break;
                case \SEARCHSPECIALS_NEWPRODUCTS:
                    $name = Shop::Lang()->get('newProducts');
                    $days = (($age = $this->getConfig('boxen')['box_neuimsortiment_alter_tage']) > 0)
                        ? (int)$age
                        : 30;
                    $state->addCondition(
                        "tartikel.cNeu = 'Y' 
                        AND DATE_SUB(NOW(), INTERVAL " . $days . ' DAY) < tartikel.dErstellt'
                    );
                    break;
                case \SEARCHSPECIALS_TOPOFFERS:
                    $name = Shop::Lang()->get('topOffer');
                    $state->addCondition("tartikel.cTopArtikel = 'Y'");
                    break;
                case \SEARCHSPECIALS_UPCOMINGPRODUCTS:
                    $name = Shop::Lang()->get('upcomingProducts');
                    $state->addCondition('NOW() < tartikel.dErscheinungsdatum');
                    break;
                case \SEARCHSPECIALS_TOPREVIEWS:
                    $name = Shop::Lang()->get('topReviews');
                    if (!$this->getProductFilter()->hasRatingFilter()) {
                        $state->addJoin(
                            (new Join())
                                ->setType('JOIN')
                                ->setTable('tartikelext')
                                ->setOn('tartikelext.kArtikel = tartikel.kArtikel')
                                ->setOrigin(__CLASS__)
                        );
                    }
                    $state->addCondition(
                        'ROUND(tartikelext.fDurchschnittsBewertung) >= ' .
                        (int)$this->getConfig('boxen')['boxen_topbewertet_minsterne']
                    );
                    break;
                default:
                    break;
            }
            if ($wasCached === false) {
                $qry        = $this->getProductFilter()->getFilterSQL()->getBaseQuery($state);
                $qryRes     = $this->getProductFilter()->getDB()->getObjects($qry);
                $count      = \count($qryRes);
                $cached[$i] = $count;
            } else {
                $count = $cached[$i];
            }
            if ($count > 0) {
                if ($baseValue === $i) {
                    continue;
                }
                $opt = new Option();
                $opt->setIsActive($this->getProductFilter()->filterOptionIsActive($this->getClassName(), $i));
                $opt->setURL($this->getProductFilter()->getFilterURL()->getURL($additionalFilter->init($i)));
                $opt->setType($this->getType());
                $opt->setClassName($this->getClassName());
                $opt->setParam($this->getUrlParam());
                $opt->setName($name);
                $opt->setValue($i);
                $opt->setCount($count);
                $opt->setSort(0);
                $options[$i] = $opt;
            }
        }
        $this->options = $options;
        if ($wasCached === false) {
            $this->getProductFilter()->getCache()->set($cacheID, $cached, [\CACHING_GROUP_FILTER]);
        }

        return $options;
    }
}
