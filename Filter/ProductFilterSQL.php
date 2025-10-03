<?php

declare(strict_types=1);

namespace JTL\Filter;

use JTL\Shop;

use function Functional\reduce_left;

/**
 * Class ProductFilterSQL
 * @package JTL\Filter
 */
class ProductFilterSQL implements ProductFilterSQLInterface
{
    /**
     * @var array<mixed>
     */
    private array $conf;

    public function __construct(private readonly ProductFilter $productFilter)
    {
        $this->conf = $productFilter->getFilterConfig()->getConfig();
    }

    /**
     * @inheritdoc
     */
    public function getBaseQuery(StateSQLInterface $state, string $type = 'filter'): string
    {
        $select     = $state->getSelect();
        $joins      = $state->getJoins();
        $conditions = $state->getConditions();
        $having     = $state->getHaving();
        $sort       = $state->getOrderBy();
        $limit      = $state->getLimit();
        $groupBy    = $state->getGroupBy();
        if ($sort === null) {
            $sort    = $this->productFilter->getSorting()->getActiveSorting();
            $joins[] = $sort->getJoin();
            $sort    = $sort->getOrderBy();
        }
        if (!Shop::get('skipProductVisibilityCheck')) {
            $joins[] = (new Join())
                ->setType('LEFT JOIN')
                ->setTable('tartikelsichtbarkeit')
                ->setOrigin(__CLASS__)
                ->setOn(
                    'tartikel.kArtikel = tartikelsichtbarkeit.kArtikel 
                    AND tartikelsichtbarkeit.kKundengruppe = '
                    . $this->productFilter->getFilterConfig()->getCustomerGroupID()
                );
        }
        // remove duplicate joins
        $checked = [];
        $joins   = reduce_left($joins, static function (JoinInterface $value, $i, $c, $reduction) use (&$checked) {
            $key = $value->getTable();
            if (!\in_array($key, $checked, true)) {
                $checked[]   = $key;
                $reduction[] = $value;
            }

            return $reduction;
        }, []);
        // default base conditions
        if (!Shop::get('skipProductVisibilityCheck')) {
            $conditions[] = 'tartikelsichtbarkeit.kArtikel IS NULL';
        }

        $showChildProducts = $this->productFilter->showChildProducts();
        if (
            $showChildProducts === 2
            || ($showChildProducts === 1
                && ($type === 'filter' || $this->productFilter->getFilterCount() > 0))
        ) {
            $conditions[] = '(tartikel.kVaterArtikel > 0 
                                OR NOT EXISTS 
                                    (SELECT 1 FROM tartikel cps WHERE cps.kVaterArtikel = tartikel.kArtikel))';
        } else {
            $conditions[] = 'tartikel.kVaterArtikel = 0';
        }
        $conditions[] = $this->getStockFilterSQL(false);
        // remove empty conditions
        $conditions = \array_filter($conditions);
        \executeHook(\HOOK_PRODUCTFILTER_GET_BASE_QUERY, [
            'select'           => &$select,
            'joins'            => &$joins,
            'conditions'       => &$conditions,
            'groupBy'          => &$groupBy,
            'having'           => &$having,
            'order'            => &$sort,
            'limit'            => &$limit,
            'productFilter'    => $this->productFilter,
            'productFilterSQL' => $this
        ]);
        // merge Query-Conditions
        $filterQueryIndices = [];
        $filterQueries      = \array_filter(
            $conditions,
            static fn($f): bool => \is_object($f) && \get_class($f) === Query::class
        );
        /** @var QueryInterface $condition */
        foreach ($filterQueries as $idx => $condition) {
            if (\count($filterQueryIndices) === 0) {
                $filterQueryIndices[] = $idx;
                continue;
            }
            $found        = false;
            $currentWhere = $condition->getWhere();
            foreach ($filterQueryIndices as $i) {
                /** @var QueryInterface $check */
                $check = $conditions[$i];
                if ($currentWhere === $check->getWhere()) {
                    $found = true;
                    $check->setParams(\array_merge_recursive($check->getParams(), $condition->getParams()));
                    unset($conditions[$idx]);
                    break;
                }
            }
            if ($found === false) {
                $filterQueryIndices[] = $idx;
            }
        }
        // build sql string
        $cond = \implode(
            ' AND ',
            \array_map(
                static function ($a) {
                    if (\is_string($a) || (\is_object($a) && \get_class($a) === Query::class)) {
                        return $a;
                    }

                    return '(' . \implode(' AND ', $a) . ')';
                },
                $conditions
            )
        );

        return 'SELECT ' . \implode(', ', $select) . '
            FROM tartikel ' . \implode("\n", $joins) . "\n" .
            (empty($cond) ? '' : (' WHERE ' . $cond . "\n")) .
            (empty($groupBy) ? '' : ('GROUP BY ' . \implode(', ', $groupBy) . "\n")) .
            (\implode(' AND ', $having) . "\n") .
            (empty($sort) ? '' : ('ORDER BY ' . $sort)) .
            (empty($limit) ? '' : ('LIMIT ' . $limit));
    }

    /**
     * @inheritdoc
     */
    public function getStockFilterSQL(bool $withAnd = true): string
    {
        $filterSQL  = '';
        $filterType = (int)$this->conf['global']['artikel_artikelanzeigefilter'];
        if (
            $filterType === \EINSTELLUNGEN_ARTIKELANZEIGEFILTER_LAGER
            || $filterType === \EINSTELLUNGEN_ARTIKELANZEIGEFILTER_LAGERNULL
        ) {
            $or        = $filterType === \EINSTELLUNGEN_ARTIKELANZEIGEFILTER_LAGERNULL
                ? " OR tartikel.cLagerKleinerNull = 'Y'"
                : '';
            $filterSQL = ($withAnd === true ? ' AND ' : ' ') .
                "(tartikel.cLagerBeachten != 'Y'
                    OR tartikel.fLagerbestand > 0
                    OR (tartikel.cLagerVariation = 'Y'
                        AND (
                            SELECT MAX(teigenschaftwert.fLagerbestand)
                            FROM teigenschaft
                            INNER JOIN teigenschaftwert ON teigenschaftwert.kEigenschaft = teigenschaft.kEigenschaft
                            WHERE teigenschaft.kArtikel = tartikel.kArtikel
                        ) > 0
                    )" . $or .
                ')';
        }
        \executeHook(\HOOK_STOCK_FILTER, [
            'conf'      => $filterType,
            'filterSQL' => &$filterSQL,
            'withAnd'   => $withAnd,
        ]);

        return $filterSQL;
    }
}
