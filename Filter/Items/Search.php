<?php

declare(strict_types=1);

namespace JTL\Filter\Items;

use JTL\Filter\AbstractFilter;
use JTL\Filter\FilterInterface;
use JTL\Filter\Join;
use JTL\Filter\Option;
use JTL\Filter\ProductFilter;
use JTL\Filter\States\BaseSearchQuery;
use JTL\Filter\StateSQL;
use JTL\Helpers\Request;
use JTL\Helpers\Seo;
use JTL\MagicCompatibilityTrait;
use JTL\Shop;
use stdClass;

/**
 * Class Search
 * @package JTL\Filter\Items
 */
class Search extends AbstractFilter
{
    use MagicCompatibilityTrait;

    private int $searchCacheID = 0;

    private ?string $error = null;

    /**
     * @former kSuchanfrage
     */
    private ?int $searchID = null;

    public bool $bExtendedJTLSearch = false;

    /**
     * @var array<string, string>
     */
    public static array $mapping = [
        'kSuchanfrage' => 'Value',
        'cSuche'       => 'Name',
        'Fehler'       => 'Error'
    ];

    public function __construct(ProductFilter $productFilter)
    {
        parent::__construct($productFilter);
        $this->setIsCustom(false)
            ->setVisibility($this->getConfig('navigationsfilter')['suchtrefferfilter_nutzen'])
            ->setFrontendName(Shop::isAdmin() ? \__('filterSearch') : Shop::Lang()->get('searchFilter'))
            ->setFilterName($this->getFrontendName())
            ->setUrlParam(\QUERY_PARAM_SEARCH_FILTER);
    }

    public function getSearchCacheID(): int
    {
        return $this->searchCacheID;
    }

    public function setSearchCacheID(int $id): FilterInterface
    {
        $this->searchCacheID = $id;

        return $this;
    }

    public function setError(string $errorMsg): FilterInterface
    {
        $this->error = $errorMsg;

        return $this;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * @inheritdoc
     */
    public function setValue($value): FilterInterface
    {
        $this->searchID = $value;

        return $this;
    }

    /**
     * @inheritdoc
     * @return int
     */
    public function getValue()
    {
        return $this->searchID;
    }

    /**
     * @inheritdoc
     */
    public function setSeo(array $languages): FilterInterface
    {
        $seo = $this->getProductFilter()->getDB()->getSingleObject(
            'SELECT cSuche
                FROM tsuchanfrage
                WHERE kSuchanfrage = :kkey
                  AND kSprache = :languageID',
            [
                'kkey'       => $this->getValue(),
                'languageID' => $this->getLanguageID()
            ]
        );
        if ($seo !== null && !empty($seo->cSuche)) {
            $this->setName($seo->cSuche);
        }

        return $this;
    }

    public function setQueryID(string $searchTerm, int $languageID): FilterInterface
    {
        $searchQuery = null;
        if ($languageID > 0 && \mb_strlen($searchTerm) > 0) {
            $searchQuery = $this->getProductFilter()->getDB()->select(
                'tsuchanfrage',
                'cSuche',
                $searchTerm,
                'kSprache',
                $languageID
            );
        }
        $this->setValue(
            (isset($searchQuery->kSuchanfrage) && $searchQuery->kSuchanfrage > 0)
                ? (int)$searchQuery->kSuchanfrage
                : 0
        );

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getPrimaryKeyRow(): string
    {
        return 'kSuchanfrage';
    }

    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tsuchanfrage';
    }

    public function saveQuery(
        int $hits,
        string $query = '',
        bool $real = false,
        int $languageID = 0,
        bool $filterSpam = true
    ): bool {
        if ($query === '') {
            $query = $this->getName() ?? '';
        }
        if (empty($query) || $this->getProductFilter()->getFilterCount() > 0) {
            // only save non-filtered queries
            return false;
        }
        $query       = \str_replace(["'", '\\', '*', '%'], '', $query);
        $languageID  = $languageID > 0 ? $languageID : $this->getLanguageID();
        $tempQueries = \explode(';', $query);
        $blacklist   = $this->getProductFilter()->getDB()->select(
            'tsuchanfrageblacklist',
            'kSprache',
            $languageID,
            'cSuche',
            $this->getProductFilter()->getDB()->escape($tempQueries[0])
        );
        if ($filterSpam && $blacklist !== null && !empty($blacklist->kSuchanfrageBlacklist)) {
            return false;
        }
        // Ist md5(IP) bereits X mal im Cache
        $maxHits       = (int)$this->getConfig('artikeluebersicht')['livesuche_max_ip_count'];
        $userCacheHits = $this->getProductFilter()->getDB()->getSingleInt(
            'SELECT COUNT(*) AS cnt
                FROM tsuchanfragencache
                WHERE kSprache = :lang
                AND cIP = :ip',
            'cnt',
            ['lang' => $languageID, 'ip' => Request::getRealIP()]
        );
        $ipUsed        = $this->getProductFilter()->getDB()->select(
            'tsuchanfragencache',
            'kSprache',
            $languageID,
            'cSuche',
            $query,
            'cIP',
            Request::getRealIP(),
            false,
            'kSuchanfrageCache'
        );
        if (!$filterSpam || ($userCacheHits < $maxHits && ($ipUsed === null || empty($ipUsed->kSuchanfrageCache)))) {
            $searchQueryCache           = new stdClass();
            $searchQueryCache->kSprache = $languageID;
            $searchQueryCache->cIP      = Request::getRealIP();
            $searchQueryCache->cSuche   = $query;
            $searchQueryCache->dZeit    = 'NOW()';
            $this->getProductFilter()->getDB()->insert('tsuchanfragencache', $searchQueryCache);
            // Cacheeinträge die > 1 Stunde sind, löschen
            $this->getProductFilter()->getDB()->query(
                'DELETE
                    FROM tsuchanfragencache
                    WHERE dZeit < DATE_SUB(NOW(), INTERVAL 1 HOUR)'
            );
            if ($hits > 0) {
                $searchQuery                  = new stdClass();
                $searchQuery->kSprache        = $languageID;
                $searchQuery->cSuche          = $query;
                $searchQuery->nAnzahlTreffer  = $hits;
                $searchQuery->nAnzahlGesuche  = 1;
                $searchQuery->dZuletztGesucht = 'NOW()';
                $searchQuery->cSeo            = Seo::getSeo($query);
                $searchQuery->cSeo            = Seo::checkSeo($searchQuery->cSeo);
                $previuousQuery               = $this->getProductFilter()->getDB()->select(
                    'tsuchanfrage',
                    'kSprache',
                    $searchQuery->kSprache,
                    'cSuche',
                    $query,
                    null,
                    null,
                    false,
                    'kSuchanfrage'
                );
                if ($real && $previuousQuery !== null && $previuousQuery->kSuchanfrage > 0) {
                    $this->getProductFilter()->getDB()->queryPrepared(
                        'UPDATE tsuchanfrage
                            SET nAnzahlTreffer = :hc,
                                nAnzahlGesuche = nAnzahlGesuche + 1,
                                dZuletztGesucht = NOW()
                            WHERE kSuchanfrage = :qid',
                        ['hc' => $searchQuery->nAnzahlTreffer, 'qid' => (int)$previuousQuery->kSuchanfrage]
                    );
                } elseif (!isset($previuousQuery->kSuchanfrage) || !$previuousQuery->kSuchanfrage) {
                    $this->getProductFilter()->getDB()->delete(
                        'tsuchanfrageerfolglos',
                        ['kSprache', 'cSuche'],
                        [$searchQuery->kSprache, $query]
                    );

                    return $this->getProductFilter()->getDB()->insert('tsuchanfrage', $searchQuery) > 0;
                }
            } else {
                $queryMiss                  = new stdClass();
                $queryMiss->kSprache        = $languageID;
                $queryMiss->cSuche          = $query;
                $queryMiss->nAnzahlGesuche  = 1;
                $queryMiss->dZuletztGesucht = 'NOW()';
                $oldMiss                    = $this->getProductFilter()->getDB()->select(
                    'tsuchanfrageerfolglos',
                    'kSprache',
                    $queryMiss->kSprache,
                    'cSuche',
                    $query,
                    null,
                    null,
                    false,
                    'kSuchanfrageErfolglos'
                );
                if ($real && $oldMiss !== null && $oldMiss->kSuchanfrageErfolglos > 0) {
                    $this->getProductFilter()->getDB()->queryPrepared(
                        'UPDATE tsuchanfrageerfolglos
                            SET nAnzahlGesuche = nAnzahlGesuche + 1,
                                dZuletztGesucht = NOW()
                            WHERE kSuchanfrageErfolglos = :qid',
                        ['qid' => (int)$oldMiss->kSuchanfrageErfolglos]
                    );
                } else {
                    $this->getProductFilter()->getDB()->delete(
                        'tsuchanfrage',
                        ['kSprache', 'cSuche'],
                        [$queryMiss->kSprache, $query]
                    );
                    $this->getProductFilter()->getDB()->insert('tsuchanfrageerfolglos', $queryMiss);
                }
            }
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function getSQLJoin(): Join
    {
        $count        = 0;
        $searchCache  = [];
        $searchFilter = $this->getProductFilter()->getSearchFilter();
        if (\is_array($searchFilter)) {
            $count       = \count($searchFilter);
            $searchCache = \array_map(static fn(Search $f) => $f->getValue(), $searchFilter);
        } elseif ($searchFilter->getSearchCacheID() > 0) {
            $searchCache[] = $searchFilter->getSearchCacheID();
            $count         = 1;
        } elseif (($value = $searchFilter->getValue()) > 0) {
            $searchCache = [$value];
            $count       = 1;
        }
        if (\count($searchCache) === 0 && $this->getValue() !== null) {
            $searchCache = [$this->getValue()];
            $count       = 1;
        }

        return (new Join())
            ->setType('JOIN')
            ->setTable(
                '(SELECT tsuchcachetreffer.kArtikel, tsuchcachetreffer.kSuchCache,
                        MIN(tsuchcachetreffer.nSort) AS nSort
                          FROM tsuchcachetreffer
                          JOIN tsuchcache
                              ON tsuchcachetreffer.kSuchCache = tsuchcache.kSuchCache
                          JOIN tsuchanfrage
                              ON tsuchanfrage.cSuche = tsuchcache.cSuche
                              AND tsuchanfrage.kSuchanfrage IN (' . \implode(',', $searchCache) . ')
                          GROUP BY tsuchcachetreffer.kArtikel
                          HAVING COUNT(*) = ' . $count . '
                    ) AS jfSuche'
            )
            ->setOn('jfSuche.kArtikel = tartikel.kArtikel');
    }

    private function generateSearchCaches(int $limit = 0): self
    {
        $allQueries = $this->getProductFilter()->getDB()->getObjects(
            'SELECT tsuchanfrage.cSuche FROM tsuchanfrage
                LEFT JOIN tsuchcache
                    ON tsuchcache.cSuche = tsuchanfrage.cSuche
                WHERE tsuchanfrage.nAktiv = 1
                    AND tsuchcache.kSuchCache IS NULL
                ORDER BY tsuchanfrage.nAnzahlTreffer, tsuchanfrage.nAnzahlGesuche'
            . ($limit > 0 ? ' LIMIT ' . $limit : '')
        );
        foreach ($allQueries as $nonCachedQuery) {
            $bsq = new BaseSearchQuery($this->getProductFilter());
            $bsq->init($nonCachedQuery->cSuche)
                ->setName($nonCachedQuery->cSuche);
            $bsq->editSearchCache();
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getOptions($mixed = null): array
    {
        if ($this->options !== null) {
            return $this->options;
        }
        $limit   = (int)$this->getConfig('navigationsfilter')['suchtrefferfilter_anzahl'];
        $options = [];
        if ($this->getConfig('navigationsfilter')['suchtrefferfilter_nutzen'] === 'N') {
            return $options;
        }
        $sql = (new StateSQL())->from($this->getProductFilter()->getCurrentStateData());
        $sql->setSelect([
            'tsuchanfrage.kSuchanfrage',
            'tsuchcache.kSuchCache',
            'tsuchanfrage.cSuche',
            'tartikel.kArtikel'
        ]);
        $sql->setOrderBy(null);
        $sql->setLimit('');
        $sql->setGroupBy(['tsuchanfrage.kSuchanfrage', 'tartikel.kArtikel']);
        $sql->addJoin(
            (new Join())
                ->setType('JOIN')
                ->setTable('tsuchcachetreffer')
                ->setOn('tartikel.kArtikel = tsuchcachetreffer.kArtikel')
                ->setOrigin(__CLASS__)
        );
        $sql->addJoin(
            (new Join())
                ->setType('JOIN')
                ->setTable('tsuchcache')
                ->setOn('tsuchcache.kSuchCache = tsuchcachetreffer.kSuchCache')
                ->setOrigin(__CLASS__)
        );
        $sql->addJoin(
            (new Join())
                ->setType('JOIN')
                ->setTable('tsuchanfrage')
                ->setOn(
                    'tsuchanfrage.cSuche = tsuchcache.cSuche
                        AND tsuchanfrage.kSprache = ' . $this->getLanguageID()
                )
                ->setOrigin(__CLASS__)
        );
        $sql->addCondition('tsuchanfrage.nAktiv = 1');

        $baseQuery = $this->getProductFilter()->getFilterSQL()->getBaseQuery($sql);
        $cacheID   = $this->getCacheID($baseQuery);
        if (($cached = $this->getProductFilter()->getCache()->get($cacheID)) !== false) {
            $this->options = $cached;

            return $this->options;
        }
        $nLimit = $limit > 0 ? ' LIMIT ' . $limit : '';
        $this->generateSearchCaches($limit > 0 ? $limit : 10);
        $searchFilters = $this->getProductFilter()->getDB()->getObjects(
            'SELECT ssMerkmal.kSuchanfrage, ssMerkmal.kSuchCache, ssMerkmal.cSuche, COUNT(*) AS nAnzahl
                FROM (' . $baseQuery . ') AS ssMerkmal
                    GROUP BY ssMerkmal.kSuchanfrage
                    ORDER BY ssMerkmal.cSuche' . $nLimit
        );
        $searchQueries = [];
        if ($this->getProductFilter()->hasSearch()) {
            $searchQueries[] = $this->getProductFilter()->getSearch()->getValue();
        }
        if ($this->getProductFilter()->hasSearchFilter()) {
            foreach ($this->getProductFilter()->getSearchFilter() as $item) {
                if ($item->getValue() > 0) {
                    $searchQueries[] = (int)$item->getValue();
                }
            }
        }
        // entferne bereits gesetzte Filter aus dem Ergebnis-Array
        foreach ($searchFilters as $j => $searchFilter) {
            foreach ($searchQueries as $searchQuery) {
                if ($searchFilter->kSuchanfrage === $searchQuery) {
                    unset($searchFilters[$j]);
                    break;
                }
            }
        }
        $searchFilters    = \array_merge($searchFilters);
        $additionalFilter = new self($this->getProductFilter());
        $count            = \count($searchFilters);
        $stepPrio         = $count > 0
            ? ($searchFilters[0]->nAnzahl - $searchFilters[$count - 1]->nAnzahl) / 9
            : 0;
        $activeValues     = \array_map(
            static fn(Search $f) => $f->getValue(),
            $this->getProductFilter()->getSearchFilter()
        );
        foreach ($searchFilters as $searchFilter) {
            $class = \random_int(1, 10);
            if (isset($searchFilter->kSuchCache) && $searchFilter->kSuchCache > 0 && $stepPrio > 0) {
                $class = \round(($searchFilter->nAnzahl - $searchFilters[$count - 1]->nAnzahl) / $stepPrio) + 1;
            }
            $opt = new Option();
            $opt->setURL(
                $this->getProductFilter()->getFilterURL()->getURL(
                    $additionalFilter->init((int)$searchFilter->kSuchanfrage)
                )
            );
            $opt->setData('cSuche', $searchFilter->cSuche);
            $opt->setData('kSuchanfrage', $searchFilter->kSuchanfrage);
            $opt->setIsActive(\in_array((int)$searchFilter->kSuchanfrage, $activeValues, true));
            $opt->setType($this->getType());
            $opt->setClassName($this->getClassName());
            $opt->setClass((string)$class);
            $opt->setParam($this->getUrlParam());
            $opt->setName($searchFilter->cSuche);
            $opt->setValue((int)$searchFilter->kSuchanfrage);
            $opt->setCount((int)$searchFilter->nAnzahl);
            $options[] = $opt;
        }
        $this->options = $options;
        $this->getProductFilter()->getCache()->set($cacheID, $options, [\CACHING_GROUP_FILTER]);

        return $options;
    }
}
