<?php

declare(strict_types=1);

namespace JTL\Filter\States;

use Illuminate\Support\Collection;
use JTL\Catalog\Hersteller;
use JTL\Filter\AbstractFilter;
use JTL\Filter\FilterInterface;
use JTL\Filter\Items\Manufacturer;
use JTL\Filter\Join;
use JTL\Filter\Option;
use JTL\Filter\ProductFilter;
use JTL\Filter\StateSQL;
use JTL\Filter\Type;
use JTL\MagicCompatibilityTrait;
use JTL\Media\Image;
use JTL\Router\RoutableTrait;
use JTL\Router\Router;

/**
 * Class BaseManufacturer
 * @package JTL\Filter\States
 */
class BaseManufacturer extends AbstractFilter
{
    use MagicCompatibilityTrait;
    use RoutableTrait;

    /**
     * @var array<string, string>
     */
    public static array $mapping = [
        'kHersteller' => 'ValueCompat',
        'cName'       => 'Name'
    ];

    public function __construct(ProductFilter $productFilter)
    {
        parent::__construct($productFilter);
        $this->setRouteType(Router::TYPE_MANUFACTURER);
        $this->setIsCustom(false)
            ->setUrlParam(\QUERY_PARAM_MANUFACTURER)
            ->setUrlParamSEO(\SEP_HST);
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
        $val = $this->getValue();
        $ok  = (\is_numeric($val) && $val > 0) || (\is_array($val) && \count($val) > 0);
        if (!$ok) {
            return $this;
        }
        if (!\is_array($val)) {
            $val = [$val];
        }
        $seoData = $this->getSeoData($val);
        foreach ($languages as $language) {
            $langID              = $language->kSprache;
            $this->cSeo[$langID] = '';
            foreach ($seoData as $seo) {
                if ($langID === (int)$seo->kSprache) {
                    $sep                  = $this->cSeo[$langID] === '' ? '' : \SEP_HST;
                    $this->cSeo[$langID]  .= $sep . $seo->cSeo;
                    $this->slugs[$langID] = $seo->cSeo;
                }
            }
        }
        $this->createBySlug();
        foreach ($this->getURLPaths() as $langID => $slug) {
            $this->cSeo[$langID] = \ltrim($slug, '/');
        }
        $first = $seoData->first();
        if ($first !== null) {
            $this->setName($first->cName);
        } else {
            // invalid manufacturer ID
            $this->fail();
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getRoute(array $additional): ?string
    {
        $manufacturerID    = (int)$this->getValue();
        $currentLanguageID = $this->getLanguageID();
        $manufacturer      = new Hersteller($manufacturerID, $currentLanguageID);
        $manufacturer->createBySlug($manufacturerID, $additional);

        return \ltrim($manufacturer->getURLPath($currentLanguageID) ?? '', '/');
    }

    /**
     * @inheritdoc
     */
    public function getPrimaryKeyRow(): string
    {
        return 'kHersteller';
    }

    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'thersteller';
    }

    /**
     * @inheritdoc
     */
    public function getSQLCondition(): string
    {
        $val = $this->getValue();
        if (!\is_array($val)) {
            $val = [$val];
        }

        return $this->getType() === Type::OR
            ? 'tartikel.' . $this->getPrimaryKeyRow() . ' IN (' . \implode(', ', $val) . ')'
            : \implode(
                ' AND ',
                \array_map(fn($e): string => 'tartikel.' . $this->getPrimaryKeyRow() . ' = ' . $e, $val)
            );
    }

    /**
     * @inheritdoc
     */
    public function getSQLJoin()
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
        $options = [];
        if ($this->getConfig('navigationsfilter')['allgemein_herstellerfilter_benutzen'] === 'N') {
            return $options;
        }
        $state = $this->getProductFilter()->getCurrentStateData(
            $this->getType() === Type::OR
                ? $this->getClassName()
                : null
        );
        $sql   = (new StateSQL())->from($state);
        $sql->setSelect([
            'thersteller.kHersteller',
            'thersteller.cName',
            'thersteller.nSortNr',
            'thersteller.cBildPfad',
            'tartikel.kArtikel'
        ]);
        $sql->setOrderBy(null);
        $sql->setLimit('');
        $sql->setGroupBy(['tartikel.kArtikel']);
        $sql->addJoin(
            (new Join())
                ->setType('JOIN')
                ->setTable('thersteller')
                ->setOn('tartikel.kHersteller = thersteller.kHersteller')
                ->setOrigin(__CLASS__)
        );
        $baseQuery = $this->getProductFilter()->getFilterSQL()->getBaseQuery($sql);
        $cacheID   = $this->getCacheID($baseQuery);
        if (($manufacturers = $this->getProductFilter()->getCache()->get($cacheID)) === false) {
            $manufacturers = $this->getProductFilter()->getDB()->getObjects(
                'SELECT tseo.cSeo, ssMerkmal.kHersteller AS id, ssMerkmal.cName AS name,
                ssMerkmal.nSortNr AS sort, ssMerkmal.cBildPfad, COUNT(*) AS cnt
                FROM (' . $baseQuery . ") AS ssMerkmal
                    LEFT JOIN tseo 
                        ON tseo.kKey = ssMerkmal.kHersteller
                        AND tseo.cKey = 'kHersteller'
                        AND tseo.kSprache = :lid
                    GROUP BY ssMerkmal.kHersteller
                    ORDER BY ssMerkmal.nSortNr, ssMerkmal.cName",
                ['lid' => $this->getLanguageID()]
            );
            foreach ($manufacturers as $manufacturer) {
                $manufacturer->id   = (int)$manufacturer->id;
                $manufacturer->cnt  = (int)$manufacturer->cnt;
                $manufacturer->sort = (int)$manufacturer->sort;
            }
            $this->getProductFilter()->getCache()->set($cacheID, $manufacturers, [\CACHING_GROUP_FILTER]);
        }
        $additionalFilter = new Manufacturer($this->getProductFilter());
        foreach ($manufacturers as $manufacturer) {
            // attributes for old filter templates
            $manufacturer->url = $this->getProductFilter()->getFilterURL()->getURL(
                $additionalFilter->init($manufacturer->id)
            );
            $manufacturerData  = new Hersteller($manufacturer->id, $this->getLanguageID());

            $opt = new Option();
            $opt->setURL($manufacturer->url);
            $opt->setIsActive(
                $this->getProductFilter()->filterOptionIsActive(
                    $this->getClassName(),
                    $manufacturer->id
                )
            );
            $opt->setType($this->getType());
            $opt->setFrontendName($manufacturer->name);
            $opt->setClassName($this->getClassName());
            $opt->setParam($this->getUrlParam());
            $opt->setName($manufacturer->name);
            $opt->setValue($manufacturer->id);
            $opt->setCount($manufacturer->cnt);
            $opt->setSort($manufacturer->sort);
            $opt->setData('cBildpfadKlein', $manufacturerData->getImage(Image::SIZE_XS));
            $options[] = $opt;
        }
        $this->options = $options;

        return $options;
    }

    /**
     * @param array<int, numeric-string> $keys
     * @return Collection<int, \stdClass>
     */
    protected function getSeoData(array $keys): Collection
    {
        $keys    = \array_map('\intval', $keys);
        $cache   = $this->getProductFilter()->getCache();
        $cacheID = 'fltr_mnf_seo_data';
        $query   = "SELECT tseo.cSeo, tseo.kSprache, thersteller.cName, thersteller.kHersteller AS id
                    FROM tseo
                    JOIN thersteller
                        ON thersteller.kHersteller = tseo.kKey
                        AND thersteller.nAktiv = 1
                    WHERE cKey = 'kHersteller'";
        if ($cache->isActive() === false) {
            return $this->getProductFilter()->getDB()->getCollection(
                $query . ' AND kKey IN (' . \implode(', ', $keys) . ')'
            );
        }
        if (($seoData = $cache->get($cacheID)) === false) {
            $seoData = $this->getProductFilter()->getDB()->getCollection($query);
            foreach ($seoData as $item) {
                $item->kSprache = (int)$item->kSprache;
                $item->id       = (int)$item->id;
            }
            $cache->set($cacheID, $seoData, [\CACHING_GROUP_FILTER]);
        }

        return $seoData->filter(fn(\stdClass $item): bool => \in_array($item->id, $keys, true));
    }
}
