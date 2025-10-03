<?php

declare(strict_types=1);

namespace JTL\Filter\Items;

use JTL\Catalog\Currency;
use JTL\Catalog\Product\Preise;
use JTL\Filter\AbstractFilter;
use JTL\Filter\FilterInterface;
use JTL\Filter\Join;
use JTL\Filter\JoinInterface;
use JTL\Filter\MultiJoin;
use JTL\Filter\Option;
use JTL\Filter\ProductFilter;
use JTL\Filter\StateSQL;
use JTL\Filter\StateSQLInterface;
use JTL\Helpers\GeneralObject;
use JTL\Helpers\Tax;
use JTL\MagicCompatibilityTrait;
use JTL\Session\Frontend;
use JTL\Shop;
use stdClass;

/**
 * Class PriceRange
 * @package JTL\Filter\Items
 */
class PriceRange extends AbstractFilter
{
    use MagicCompatibilityTrait;

    private float $offsetStart;

    private float $offsetEnd;

    private string $offsetStartLocalized;

    private string $offsetEndLocalized;

    private string $condition = '';

    /**
     * @var array<string, string>
     */
    public static array $mapping = [
        'cName'          => 'Name',
        'nAnzahlArtikel' => 'Count',
        'cWert'          => 'Value',
        'fVon'           => 'OffsetStart',
        'fBis'           => 'OffsetEnd',
        'cVonLocalized'  => 'OffsetStartLocalized',
        'cBisLocalized'  => 'OffsetEndLocalized'
    ];

    public function __construct(ProductFilter $productFilter)
    {
        parent::__construct($productFilter);
        $this->setIsCustom(false)
            ->setUrlParam(\QUERY_PARAM_PRICE_FILTER)
            ->setVisibility($this->getConfig('navigationsfilter')['preisspannenfilter_benutzen'])
            ->setFrontendName(Shop::isAdmin() ? \__('filterPriceRange') : Shop::Lang()->get('rangeOfPrices'))
            ->setFilterName($this->getFrontendName());
    }

    public function getOffsetStart(): float
    {
        return $this->offsetStart;
    }

    public function setOffsetStart(float $offsetStart): PriceRange
    {
        $this->offsetStart = $offsetStart;

        return $this;
    }

    public function getOffsetEnd(): float
    {
        return $this->offsetEnd;
    }

    public function setOffsetEnd(float $offsetEnd): PriceRange
    {
        $this->offsetEnd = $offsetEnd;

        return $this;
    }

    public function getOffsetStartLocalized(): string
    {
        return $this->offsetStartLocalized;
    }

    public function setOffsetStartLocalized(string $offsetStartLocalized): PriceRange
    {
        $this->offsetStartLocalized = $offsetStartLocalized;

        return $this;
    }

    public function getOffsetEndLocalized(): string
    {
        return $this->offsetEndLocalized;
    }

    public function setOffsetEndLocalized(string $offsetEndLocalized): PriceRange
    {
        $this->offsetEndLocalized = $offsetEndLocalized;

        return $this;
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
     */
    public function init($value): FilterInterface
    {
        if (empty($value)) {
            $value = '0_0';
        }
        [$start, $end] = \explode('_', $value);

        $currency          = Frontend::getCurrency();
        $this->offsetStart = (float)$start;
        $this->offsetEnd   = (float)$end;
        $this->setValue($value === '0_0' ? 0 : ($this->offsetStart . '_' . $this->offsetEnd));
        $this->offsetStartLocalized = Preise::getLocalizedPriceWithoutFactor($this->offsetStart, $currency);
        $this->offsetEndLocalized   = Preise::getLocalizedPriceWithoutFactor($this->offsetEnd, $currency);
        $this->setName(\html_entity_decode($this->offsetStartLocalized . ' - ' . $this->offsetEndLocalized));
        $this->isInitialized = true;
        $this->condition     = '';
        $conversionFactor    = $currency->getConversionFactor();
        $groupDiscount       = Frontend::getCustomerGroup()->getDiscount();
        $discount            = (isset($_SESSION['Kunde']->fRabatt) && $_SESSION['Kunde']->fRabatt > 0)
            ? (float)$_SESSION['Kunde']->fRabatt
            : 0.0;
        if (!isset($_SESSION['Steuersatz'])) {
            Tax::setTaxRates();
        }
        $rateKeys = \array_keys($_SESSION['Steuersatz'] ?? []);
        if (Frontend::getCustomerGroup()->isMerchant()) {
            $this->condition .= ' ROUND(LEAST((tpreisdetail.fVKNetto * ' .
                $conversionFactor . ') * ((100 - GREATEST(IFNULL(tartikelkategorierabatt.fRabatt, 0), ' .
                $groupDiscount . ', ' . $discount . ', 0)) / 100), ' .
                'IFNULL(tsonderpreise.fNettoPreis, (tpreisdetail.fVKNetto * ' .
                $conversionFactor . '))), 2)';
        } else {
            foreach ($rateKeys as $taxID) {
                $this->condition .= ' IF(tartikel.kSteuerklasse = ' . $taxID . ', ROUND(
                    LEAST(tpreisdetail.fVKNetto *
                    ((100 - GREATEST(IFNULL(tartikelkategorierabatt.fRabatt, 0), ' .
                    $groupDiscount . ', ' . $discount . ', 0)) / 100), ' .
                    'IFNULL(tsonderpreise.fNettoPreis, (tpreisdetail.fVKNetto * ' .
                    $conversionFactor . '))) * ((100 + ' . (float)$_SESSION['Steuersatz'][$taxID] . ') / 100), 2),';
            }
            $this->condition .= '0';

            $count = \count($rateKeys);
            for ($x = 0; $x < $count; ++$x) {
                $this->condition .= ')';
            }
        }
        $this->condition .= ' < ' . $this->offsetEnd . ' AND ';
        if (Frontend::getCustomerGroup()->isMerchant()) {
            $this->condition .= ' ROUND(LEAST(tpreisdetail.fVKNetto *
                ((100 - GREATEST(IFNULL(tartikelkategorierabatt.fRabatt, 0), ' .
                $groupDiscount . ', ' . $discount . ', 0)) / 100), ' .
                'IFNULL(tsonderpreise.fNettoPreis, (tpreisdetail.fVKNetto * ' . $conversionFactor . '))), 2)';
        } else {
            foreach ($rateKeys as $taxID) {
                $this->condition .= ' IF(tartikel.kSteuerklasse = ' . $taxID . ',
                    ROUND(LEAST(tpreisdetail.fVKNetto * ((100 - GREATEST(IFNULL(tartikelkategorierabatt.fRabatt, 0), ' .
                    $groupDiscount . ', ' . $discount . ', 0)) / 100), 
                    IFNULL(tsonderpreise.fNettoPreis, (tpreisdetail.fVKNetto * ' .
                    $conversionFactor . '))) * ((100 + ' . (float)$_SESSION['Steuersatz'][$taxID] . ') / 100), 2),';
            }
            $this->condition .= '0';
            $count           = \count($rateKeys);
            for ($x = 0; $x < $count; ++$x) {
                $this->condition .= ')';
            }
        }
        $this->condition     .= ' >= ' . $this->offsetStart;
        $this->isInitialized = true;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getSQLCondition(): string
    {
        return $this->condition;
    }

    /**
     * @inheritdoc
     * @return JoinInterface[]
     */
    public function getSQLJoin(): array
    {
        return [
            (new MultiJoin())
                ->addJoin(
                    (new Join())
                        ->setType('JOIN')
                        ->setTable('tpreisdetail')
                        ->setOn('tpreisdetail.kPreis = tpreis.kPreis AND tpreisdetail.nAnzahlAb = 0')
                )
                ->setType('JOIN')
                ->setTable('tpreis')
                ->setOn(
                    'tartikel.kArtikel = tpreis.kArtikel
                    AND tpreis.kKundengruppe = ' . $this->getCustomerGroupID()
                )
                ->setOrigin(__CLASS__),
            (new Join())
                ->setType('LEFT JOIN')
                ->setTable('tartikelkategorierabatt')
                ->setOn(
                    'tartikelkategorierabatt.kKundengruppe = ' . $this->getCustomerGroupID() .
                    ' AND tartikelkategorierabatt.kArtikel = tartikel.kArtikel'
                )
                ->setOrigin(__CLASS__),
            (new Join())
                ->setType('LEFT JOIN')
                ->setTable('tartikelsonderpreis')
                ->setOn(
                    "tartikelsonderpreis.kArtikel = tartikel.kArtikel
                         AND tartikelsonderpreis.cAktiv = 'Y'
                         AND tartikelsonderpreis.dStart <= NOW()
                         AND (tartikelsonderpreis.nIstDatum = 0
                                OR tartikelsonderpreis.dEnde IS NULL
                                OR tartikelsonderpreis.dEnde >= CURDATE()
                            )
                         AND (tartikelsonderpreis.nIstAnzahl = 0
                                OR tartikelsonderpreis.nAnzahl <= tartikel.fLagerbestand)"
                )
                ->setOrigin(__CLASS__),
            (new Join())
                ->setType('LEFT JOIN')
                ->setTable('tsonderpreise')
                ->setOn(
                    'tartikelsonderpreis.kArtikelSonderpreis = tsonderpreise.kArtikelSonderpreis 
                     AND tsonderpreise.kKundengruppe = ' . $this->getCustomerGroupID()
                )
                ->setOrigin(__CLASS__)
        ];
    }

    /**
     * @param Option[] $options
     * @return Option[]
     */
    private function removeEmptyOptions(array $options): array
    {
        if ($this->getConfig('navigationsfilter')['preisspannenfilter_spannen_ausblenden'] !== 'Y') {
            return $options;
        }

        return \array_filter($options, static fn(Option $e): bool => $e->getCount() > 0);
    }

    /**
     * @param stdClass[] $ranges
     */
    public function getPriceRangeSQL(stdClass $steps, Currency $currency, array $ranges = []): string
    {
        $sql               = '';
        $customerDisctount = (isset($_SESSION['Kunde']->fRabatt) && $_SESSION['Kunde']->fRabatt > 0)
            ? $_SESSION['Kunde']->fRabatt
            : 0.0;
        if ($this->getConfig('navigationsfilter')['preisspannenfilter_anzeige_berechnung'] === 'A') {
            $minPrice = $steps->fMinPreis;
            $step     = $steps->fStep;
            $ranges   = [];
            for ($i = 0; $i < $steps->nAnzahlSpannen; ++$i) {
                $fakePriceRange       = new stdClass();
                $fakePriceRange->nBis = $minPrice + ($i + 1) * $step;
                $ranges[$i]           = $fakePriceRange;
            }
        }
        $max        = \count($ranges) - 1;
        $isMerchant = Frontend::getCustomerGroup()->isMerchant();
        $discount   = Frontend::getCustomerGroup()->getDiscount();
        $factor     = $currency->getConversionFactor();
        foreach ($ranges as $i => $rangeFilter) {
            $sql  .= 'COUNT(DISTINCT IF(';
            $nBis = $rangeFilter->nBis;
            // Finde den höchsten und kleinsten Steuersatz
            if (\is_array($_SESSION['Steuersatz']) && !$isMerchant) {
                $rates = \array_keys($_SESSION['Steuersatz']);
                foreach ($rates as $taxID) {
                    $sql .= 'IF(tartikel.kSteuerklasse = ' . $taxID . ',
                        ROUND(LEAST((tpreisdetail.fVKNetto * ' . $factor .
                        ') * ((100 - GREATEST(IFNULL(tartikelkategorierabatt.fRabatt, 0), ' .
                        $discount . ', ' . $customerDisctount .
                        ', 0)) / 100), IFNULL(tsonderpreise.fNettoPreis, (tpreisdetail.fVKNetto * ' .
                        $factor . '))) * ((100 + ' . (float)$_SESSION['Steuersatz'][$taxID] . ') / 100), 2),';
                }
                $sql   .= '0';
                $count = \count($rates);
                $sql   .= \str_repeat(')', $count);
            } elseif ($isMerchant) {
                $sql .= 'ROUND(LEAST((tpreisdetail.fVKNetto * ' . $factor .
                    ') * ((100 - GREATEST(IFNULL(tartikelkategorierabatt.fRabatt, 0), ' .
                    $discount . ', ' . $customerDisctount .
                    ', 0)) / 100), IFNULL(tsonderpreise.fNettoPreis, (tpreisdetail.fVKNetto * ' .
                    $factor . '))), 2)';
            }

            $sql .= ' < ' . $nBis . ', tartikel.kArtikel, NULL)) AS anz' . $i;
            if ($i < $max) {
                $sql .= ', ';
            }
        }

        return $sql;
    }

    /**
     * @param StateSQLInterface $sql
     * @return Option[]
     */
    private function getAutomaticRangeOptions(StateSQLInterface $sql): array
    {
        $currency      = Frontend::getCurrency();
        $options       = [];
        $maxTaxRate    = 0.0;
        $minTaxRate    = 0.0;
        $isMerchant    = Frontend::getCustomerGroup()->isMerchant();
        $factor        = $currency->getConversionFactor();
        $groupDiscount = ($groupDiscount = Frontend::getCustomerGroup()->getDiscount()) > 0
            ? $groupDiscount
            : 0.0;
        $discount      = (isset($_SESSION['Kunde']->fRabatt) && $_SESSION['Kunde']->fRabatt > 0)
            ? (float)$_SESSION['Kunde']->fRabatt
            : 0.0;
        $state         = (new StateSQL())->from($this->getProductFilter()->getCurrentStateData(self::class));
        if (!$isMerchant && GeneralObject::hasCount('Steuersatz', $_SESSION)) {
            $maxTaxRate = \max($_SESSION['Steuersatz']);
            $minTaxRate = \min($_SESSION['Steuersatz']);
        }
        foreach ($this->getSQLJoin() as $join) {
            $state->addJoin($join);
        }
        $state->setSelect([
            'ROUND(
                LEAST(
                    (tpreisdetail.fVKNetto * ' . $factor . ') *
                    ((100 - GREATEST(IFNULL(tartikelkategorierabatt.fRabatt, 0), ' . $groupDiscount . ', '
            . $discount . ', 0)) / 100),
                    IFNULL(tsonderpreise.fNettoPreis, (tpreisdetail.fVKNetto * '
            . $factor . '))) * ((100 + ' . $maxTaxRate . ') / 100), 2) AS fMax,
                    ROUND(LEAST((tpreisdetail.fVKNetto * ' . $factor . ') *
                    ((100 - GREATEST(IFNULL(tartikelkategorierabatt.fRabatt, 0), ' . $groupDiscount . ', '
            . $discount . ', 0)) / 100),
                    IFNULL(tsonderpreise.fNettoPreis, (tpreisdetail.fVKNetto * '
            . $factor . '))) * ((100 + ' . $minTaxRate . ') / 100), 2) AS fMin'
        ]);
        $state->setOrderBy(null);
        $state->setLimit('');
        $state->setGroupBy(['tartikel.kArtikel']);
        $baseQuery = $this->getProductFilter()->getFilterSQL()->getBaseQuery($state);
        $cacheID   = $this->getCacheID($baseQuery);
        if (($cached = $this->getProductFilter()->getCache()->get($cacheID)) !== false) {
            $this->options = $cached;

            return $this->options;
        }
        $minMax = $this->getProductFilter()->getDB()->getSingleObject(
            'SELECT MAX(ssMerkmal.fMax) AS fMax, MIN(ssMerkmal.fMin) AS fMin 
                FROM (' . $baseQuery . ' ) AS ssMerkmal'
        );
        if ($minMax !== null && $minMax->fMax > 0) {
            $selectSQL             = [];
            $steps                 = $this->calculateSteps(
                $minMax->fMax * $factor,
                $minMax->fMin * $factor
            );
            $steps->nAnzahlSpannen = \min(20, (int)$steps->nAnzahlSpannen);
            for ($i = 0; $i < $steps->nAnzahlSpannen; ++$i) {
                $selectSQL[] = ' SUM(ssMerkmal.anz' . $i . ') AS anz' . $i;
            }

            $sql->setSelect([$this->getPriceRangeSQL($steps, $currency)]);
            $sql->setOrderBy(null);
            $sql->setLimit('');
            $sql->setGroupBy(['tartikel.kArtikel']);

            $baseQuery        = $this->getProductFilter()->getFilterSQL()->getBaseQuery($sql);
            $dbRes            = $this->getProductFilter()->getDB()->getSingleObject(
                'SELECT ' . \implode(',', $selectSQL) . ' FROM (' .
                $baseQuery . ' ) AS ssMerkmal'
            );
            $priceRanges      = [];
            $priceRangeCounts = $dbRes !== null
                ? \get_object_vars($dbRes)
                : [];
            for ($i = 0; $i < $steps->nAnzahlSpannen; ++$i) {
                $sub           = $i === 0
                    ? 0
                    : $priceRangeCounts['anz' . ($i - 1)];
                $priceRanges[] = $priceRangeCounts['anz' . $i] - $sub;
            }
            $maxPrice         = $steps->fMaxPreis;
            $minPrice         = $steps->fMinPreis;
            $nStep            = $steps->fStep;
            $additionalFilter = new self($this->getProductFilter());
            foreach ($priceRanges as $i => $count) {
                $fo   = new Option();
                $from = $minPrice + $i * $nStep;
                $to   = $minPrice + ($i + 1) * $nStep;
                if ($to > $maxPrice) {
                    if ($from >= $maxPrice) {
                        $from = $minPrice + ($i - 1) * $nStep;
                    }
                    $to = $maxPrice;
                }
                $fromLocalized     = Preise::getLocalizedPriceWithoutFactor($from, $currency);
                $toLocalized       = Preise::getLocalizedPriceWithoutFactor($to, $currency);
                $fo->nVon          = $from;
                $fo->nBis          = $to;
                $fo->cVonLocalized = $fromLocalized;
                $fo->cBisLocalized = $toLocalized;

                $options[] = $fo->setParam($this->getUrlParam())
                    ->setURL(
                        $this->getProductFilter()->getFilterURL()->getURL(
                            $additionalFilter->init($from . '_' . $to)
                        )
                    )
                    ->setType($this->getType())
                    ->setClassName($this->getClassName())
                    ->setName($fromLocalized . ' - ' . $toLocalized)
                    ->setValue($from . '_' . $to)
                    ->setCount($count)
                    ->setSort(0);
            }
        }
        $options = $this->removeEmptyOptions($options);
        $this->getProductFilter()->getCache()->set($cacheID, $options, [\CACHING_GROUP_FILTER]);

        return $options;
    }

    /**
     * @param StateSQLInterface $sql
     * @return Option[]
     */
    private function getManualRangeOptions(StateSQLInterface $sql): array
    {
        $currency  = Frontend::getCurrency();
        $options   = [];
        $selectSQL = [];
        $ranges    = $this->getProductFilter()->getDB()->getObjects('SELECT * FROM tpreisspannenfilter');
        if (\count($ranges) === 0) {
            return $options;
        }
        $steps = $this->calculateSteps(
            $ranges[\count($ranges) - 1]->nBis * $currency->getConversionFactor(),
            $ranges[0]->nVon * $currency->getConversionFactor()
        );
        if (!$steps->nAnzahlSpannen || !$steps->fMaxPreis) {
            return [];
        }
        foreach ($ranges as $i => $range) {
            $selectSQL[] = 'SUM(ssMerkmal.anz' . $i . ') AS anz' . $i;
        }
        $state = (new StateSQL())->from($sql);
        $state->setSelect([$this->getPriceRangeSQL($steps, $currency, $ranges)]);
        $state->setOrderBy(null);
        $state->setLimit('');
        $state->setGroupBy(['tartikel.kArtikel']);
        foreach ($this->getSQLJoin() as $join) {
            $state->addJoin($join);
        }
        $baseQuery = $this->getProductFilter()->getFilterSQL()->getBaseQuery($state);
        $cacheID   = $this->getCacheID($baseQuery);
        if (($cached = $this->getProductFilter()->getCache()->get($cacheID)) !== false) {
            $this->options = $cached;

            return $this->options;
        }
        $dbRes = $this->getProductFilter()->getDB()->getSingleObject(
            'SELECT ' . \implode(',', $selectSQL) . ' FROM (' . $baseQuery . ' ) AS ssMerkmal'
        );

        $additionalFilter = new self($this->getProductFilter());
        $priceRangeCounts = $dbRes !== null ? \get_object_vars($dbRes) : [];
        $priceRanges      = [];
        $count            = \count($priceRangeCounts);
        for ($i = 0; $i < $count; ++$i) {
            $sub           = $i === 0
                ? 0
                : $priceRangeCounts['anz' . ($i - 1)];
            $priceRanges[] = $priceRangeCounts['anz' . $i] - $sub;
        }
        foreach ($ranges as $i => $range) {
            $fo                = new Option();
            $fo->nVon          = $range->nVon;
            $fo->nBis          = $range->nBis;
            $fo->cVonLocalized = Preise::getLocalizedPriceWithoutFactor($fo->nVon, $currency);
            $fo->cBisLocalized = Preise::getLocalizedPriceWithoutFactor($fo->nBis, $currency);
            $options[]         = $fo->setParam($this->getUrlParam())
                ->setURL(
                    $this->getProductFilter()->getFilterURL()->getURL(
                        $additionalFilter->init($fo->nVon . '_' . $fo->nBis)
                    )
                )
                ->setType($this->getType())
                ->setClassName($this->getClassName())
                ->setName($fo->cVonLocalized . ' - ' . $fo->cBisLocalized)
                ->setValue($i)
                ->setCount(isset($priceRanges[$i]) ? (int)$priceRanges[$i] : 0)
                ->setSort(0);
        }
        $options = $this->removeEmptyOptions($options);
        $this->getProductFilter()->getCache()->set($cacheID, $options, [\CACHING_GROUP_FILTER]);

        return $options;
    }

    /**
     * @inheritdoc
     */
    public function getOptions($mixed = null): array
    {
        if ($this->options !== null) {
            return $this->options;
        }
        $productCount = $mixed;
        // Prüfe, ob es nur einen Artikel in der Artikelübersicht gibt
        // falls ja und es ist noch kein Preisspannenfilter gesetzt, dürfen keine Preisspannenfilter angezeigt werden
        if (
            ($productCount === 1 && !$this->isInitialized())
            || $this->getConfig('navigationsfilter')['preisspannenfilter_benutzen'] === 'N'
        ) {
            return [];
        }
        $sql = (new StateSQL())->from($this->getProductFilter()->getCurrentStateData());

        $sql->addJoin(
            (new Join())
                ->setType('LEFT JOIN')
                ->setTable('tartikelkategorierabatt')
                ->setOn(
                    'tartikelkategorierabatt.kKundengruppe = ' . $this->getCustomerGroupID() .
                    ' AND tartikelkategorierabatt.kArtikel = tartikel.kArtikel'
                )
                ->setOrigin(__CLASS__)
        );
        $sql->addJoin(
            (new Join())
                ->setType('LEFT JOIN')
                ->setTable('tartikelsonderpreis')
                ->setOn(
                    "tartikelsonderpreis.kArtikel = tartikel.kArtikel
                        AND tartikelsonderpreis.cAktiv = 'Y'
                        AND tartikelsonderpreis.dStart <= NOW()
                        AND (tartikelsonderpreis.nIstDatum = 0
                            OR tartikelsonderpreis.dEnde IS NULL
                            OR tartikelsonderpreis.dEnde >= CURDATE())
                        AND (tartikelsonderpreis.nistAnzahl = 0
                            OR tartikelsonderpreis.nAnzahl <= tartikel.fLagerbestand)"
                )
                ->setOrigin(__CLASS__)
        );
        $sql->addJoin(
            (new Join())
                ->setType('LEFT JOIN')
                ->setTable('tsonderpreise')
                ->setOn(
                    'tartikelsonderpreis.kArtikelSonderpreis = tsonderpreise.kArtikelSonderpreis 
                    AND tsonderpreise.kKundengruppe = ' . $this->getCustomerGroupID()
                )
                ->setOrigin(__CLASS__)
        );
        $sql->addJoin(
            (new MultiJoin())->addJoin(
                (new Join())
                    ->setType('JOIN')
                    ->setTable('tpreisdetail')
                    ->setOn('tpreisdetail.kPreis = tpreis.kPreis AND tpreisdetail.nAnzahlAb = 0')
            )
                ->setTable('tpreis')
                ->setType('JOIN')
                ->setOn(
                    'tpreis.kArtikel = tartikel.kArtikel
                AND tpreis.kKundengruppe = ' . $this->getCustomerGroupID()
                )
                ->setOrigin(__CLASS__)
        );
        /*$sql->addJoin((new Join())
            ->setType('JOIN')
            ->setTable('tpreisdetail')
            ->setOn('tpreisdetail.kPreis = tpreis.kPreis AND tpreisdetail.nAnzahlAb = 0')
            ->setOrigin(__CLASS__));*/
        if (!Shop::get('skipProductVisibilityCheck')) {
            $sql->addJoin(
                (new Join())
                    ->setTable('tartikelsichtbarkeit')
                    ->setType('LEFT JOIN')
                    ->setOn(
                        'tartikel.kArtikel = tartikelsichtbarkeit.kArtikel 
                        AND tartikelsichtbarkeit.kKundengruppe = ' . $this->getCustomerGroupID()
                    )
                    ->setOrigin(__CLASS__)
            );
        }

        $this->options = $this->getConfig('navigationsfilter')['preisspannenfilter_anzeige_berechnung'] === 'A'
            ? $this->getAutomaticRangeOptions($sql)
            : $this->getManualRangeOptions($sql);

        return $this->options;
    }

    /**
     * @param float|int|numeric-string $fMax
     * @param float|int|numeric-string $fMin
     * @former berechneMaxMinStep
     */
    public function calculateSteps(float|int|string $fMax, float|int|string $fMin): stdClass
    {
        static $steps = [
            0.001,
            0.005,
            0.01,
            0.05,
            0.10,
            0.25,
            0.5,
            1.0,
            2.5,
            5.0,
            7.5,
            10.0,
            12.5,
            15.0,
            20.0,
            25.0,
            50.0,
            100.0,
            250.0,
            300.0,
            350.0,
            400.0,
            500.0,
            750.0,
            1000.0,
            1500.0,
            2500.0,
            5000.0,
            10000.0,
            25000.0,
            30000.0,
            40000.0,
            50000.0,
            60000.0,
            75000.0,
            100000.0,
            150000.0,
            250000.0,
            350000.0,
            400000.0,
            500000.0,
            550000.0,
            600000.0,
            750000.0,
            1000000.0,
            1500000.0,
            5000000.0,
            7500000.0,
            10000000.0,
            12500000.0,
            15000000.0,
            25000000.0,
            50000000.0,
            100000000.0
        ];

        $step     = 10;
        $diff     = (float)($fMax - $fMin) * 1000;
        $maxSteps = $this->getConfig('navigationsfilter')['preisspannenfilter_anzeige_berechnung'] === 'M'
            ? 10
            : 5;
        foreach ($steps as $i => $value) {
            if (($diff / ($value * 1000)) < $maxSteps) {
                $step = $i;
                break;
            }
        }
        $fMax  *= 1000.0;
        $fMin  *= 1000.0;
        $value = $steps[$step] * 1000;

        $fmmh  = (int)($fMax * 100);
        $fvmh  = (int)($value * 100);
        $fminh = (int)($fMin * 100);

        $maxPrice  = \round((($fmmh - ($fmmh % $fvmh)) + $fvmh) / 100);
        $minPrice  = \round(($fminh - ($fminh % $fvmh)) / 100);
        $diff      = $maxPrice - $minPrice;
        $stepCount = \round($diff / $value);

        $res                 = new stdClass();
        $res->fMaxPreis      = $maxPrice / 1000;
        $res->fMinPreis      = $minPrice / 1000;
        $res->fStep          = $steps[$step];
        $res->fDiffPreis     = $diff / 1000;
        $res->nAnzahlSpannen = $stepCount;

        return $res;
    }
}
