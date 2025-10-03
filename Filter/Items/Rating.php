<?php

declare(strict_types=1);

namespace JTL\Filter\Items;

use JTL\Filter\AbstractFilter;
use JTL\Filter\FilterInterface;
use JTL\Filter\Join;
use JTL\Filter\Option;
use JTL\Filter\ProductFilter;
use JTL\Filter\StateSQL;
use JTL\MagicCompatibilityTrait;
use JTL\Shop;

/**
 * Class Rating
 * @package JTL\Filter\Items
 */
class Rating extends AbstractFilter
{
    use MagicCompatibilityTrait;

    /**
     * @var array<string, string>
     */
    public static array $mapping = [
        'nSterne' => 'Value'
    ];

    public function __construct(ProductFilter $productFilter)
    {
        parent::__construct($productFilter);
        $this->setIsCustom(false)
            ->setUrlParam(\QUERY_PARAM_RATING_FILTER)
            ->setVisibility($this->getConfig('navigationsfilter')['bewertungsfilter_benutzen'])
            ->setParamExclusive(true)
            ->setFrontendName(Shop::isAdmin() ? \__('filterRatings') : Shop::Lang()->get('Votes'))
            ->setFilterName($this->getFrontendName());
    }

    /**
     * @inheritdoc
     */
    public function setValue($value): FilterInterface
    {
        return parent::setValue((int)$value);
    }

    /**
     * @inheritdoc
     */
    public function setSeo(array $languages): FilterInterface
    {
        $stars = (int)$this->getValue();
        $this->setName(
            Shop::Lang()->get('from', 'productDetails') . ' '
            . $stars . ' '
            . Shop::Lang()->get($stars > 0 ? 'starPlural' : 'starSingular')
        );

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getPrimaryKeyRow(): string
    {
        return 'nSterne';
    }

    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'ttags';
    }

    /**
     * @inheritdoc
     */
    public function getSQLCondition(): string
    {
        return 'ROUND(tartikelext.fDurchschnittsBewertung, 0) >= ' . (int)$this->getValue();
    }

    /**
     * @inheritdoc
     */
    public function getSQLJoin(): Join
    {
        return (new Join())
            ->setType('JOIN')
            ->setTable('tartikelext')
            ->setOn('tartikel.kArtikel = tartikelext.kArtikel')
            ->setOrigin(__CLASS__);
    }

    /**
     * @inheritdoc
     */
    public function getOptions($mixed = null): array
    {
        if ($this->options !== null) {
            return $this->options;
        }
        if ($this->getConfig('navigationsfilter')['bewertungsfilter_benutzen'] === 'N') {
            $this->hide();
            $this->options = [];

            return $this->options;
        }
        $options = [];
        $state   = $this->getProductFilter()->getCurrentStateData();
        $sql     = (new StateSQL())->from($state);
        $sql->setSelect(['ROUND(tartikelext.fDurchschnittsBewertung, 0) AS nSterne', 'tartikel.kArtikel']);
        $sql->setOrderBy(null);
        $sql->setLimit('');
        $sql->setGroupBy(['tartikel.kArtikel']);
        $sql->addJoin($this->getSQLJoin());

        $baseQuery = $this->getProductFilter()->getFilterSQL()->getBaseQuery($sql);
        $cacheID   = $this->getCacheID($baseQuery)
            . '_' . $this->getProductFilter()->getFilterConfig()->getLanguageID();
        if (($cached = $this->getProductFilter()->getCache()->get($cacheID)) !== false) {
            $this->options = $cached;

            return $this->options;
        }
        $res         = $this->getProductFilter()->getDB()->getObjects(
            'SELECT ssMerkmal.nSterne, COUNT(*) AS nAnzahl
                FROM (' . $baseQuery . ' ) AS ssMerkmal
                GROUP BY ssMerkmal.nSterne
                ORDER BY ssMerkmal.nSterne DESC'
        );
        $stars       = 0;
        $extraFilter = new self($this->getProductFilter());
        foreach ($res as $row) {
            $stars += (int)$row->nAnzahl;

            $opt = new Option();
            $opt->setParam($this->getUrlParam());
            $opt->setURL($this->getProductFilter()->getFilterURL()->getURL($extraFilter->init((int)$row->nSterne)));
            $opt->setType($this->getType());
            $opt->setClassName($this->getClassName());
            $opt->setName(
                Shop::Lang()->get('from', 'productDetails')
                . ' ' . $row->nSterne . ' '
                . Shop::Lang()->get($row->nSterne > 1 ? 'starPlural' : 'starSingular')
            );
            $opt->setValue((int)$row->nSterne);
            $opt->setCount($stars);
            $options[] = $opt;
        }
        $this->options = $options;
        if (\count($options) === 0) {
            $this->hide();
        }
        $this->getProductFilter()->getCache()->set($cacheID, $options, [\CACHING_GROUP_FILTER]);

        return $options;
    }
}
