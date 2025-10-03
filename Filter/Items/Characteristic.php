<?php

declare(strict_types=1);

namespace JTL\Filter\Items;

use JTL\Catalog\Category\Kategorie;
use JTL\Filter\CharacteristicOption;
use JTL\Filter\CharacteristicValueOption;
use JTL\Filter\Join;
use JTL\Filter\Option;
use JTL\Filter\ProductFilter;
use JTL\Filter\States\BaseCharacteristic;
use JTL\Filter\StateSQL;
use JTL\Filter\StateSQLInterface;
use JTL\Filter\Type;
use JTL\Language\LanguageHelper;
use JTL\MagicCompatibilityTrait;
use JTL\Media\Image;
use JTL\Shop;
use stdClass;

use function Functional\every;
use function Functional\first;
use function Functional\group;
use function Functional\map;

/**
 * Class Characteristic
 * @package JTL\Filter\Items
 */
class Characteristic extends BaseCharacteristic
{
    use MagicCompatibilityTrait;

    private ?int $id = null;

    private bool $isMultiSelect = false;

    /**
     * @var array<mixed>
     */
    private array $batchCharacteristicData = [];

    /**
     * @var array<string, string>
     */
    public static array $mapping = [
        'kMerkmal'     => 'CharacteristicIDCompat',
        'kMerkmalWert' => 'ValueCompat',
        'cName'        => 'Name',
        'cWert'        => 'Name'
    ];

    public function __construct(ProductFilter $productFilter)
    {
        parent::__construct($productFilter);
        $this->setIsCustom(false)
            ->setUrlParam(\QUERY_PARAM_CHARACTERISTIC_FILTER)
            ->setUrlParamSEO(\SEP_MERKMAL)
            ->setFrontendName(
                Shop::isAdmin()
                    ? \__('filterCharacteristics')
                    : Shop::Lang()->get('characteristics', 'comparelist')
            )
            ->setVisibility($this->getConfig('navigationsfilter')['merkmalfilter_verwenden']);
    }

    public function isMultiSelect(): bool
    {
        return $this->isMultiSelect;
    }

    public function setIsMultiSelect(bool $isMultiSelect): self
    {
        $this->isMultiSelect = $isMultiSelect;

        return $this;
    }

    public function setCharacteristicIDCompat(int|string $value): self
    {
        $this->id = (int)$value;
        if (\is_numeric($this->value) && $this->value > 0) {
            $this->getProductFilter()->enableFilter($this);
        }

        return $this;
    }

    public function getCharacteristicIDCompat(): ?int
    {
        return $this->id;
    }

    public function setID(int|string $value): self
    {
        $this->id = (int)$value;

        return $this;
    }

    public function getID(): ?int
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     */
    public function init($value): self
    {
        $this->isInitialized = true;
        if (\is_object($value) && isset($value->kMerkmalWert, $value->kMerkmal, $value->nMehrfachauswahl)) {
            $this->setValue($value->kMerkmalWert)
                ->setID($value->kMerkmal)
                ->setIsMultiSelect($value->nMehrfachauswahl === 1);

            return $this->setType($this->isMultiSelect() ? Type::OR : Type::AND)
                ->setSeo($this->getAvailableLanguages());
        }

        return $this->setValue($value)->setSeo($this->getAvailableLanguages());
    }

    /**
     * @inheritdoc
     */
    public function setSeo(array $languages): self
    {
        $value = $this->getValue();
        if (!\is_numeric($value)) {
            return $this;
        }
        $seoData       = $this->batchCharacteristicData[$value]
            ?? $this->getProductFilter()->getDB()->getObjects(
                'SELECT tmerkmalwertsprache.cWert, tmerkmalwert.kMerkmal, 
                    tmerkmalwertsprache.cSeo, tmerkmalwertsprache.kSprache,
                    tmerkmalsprache.cName AS characteristicName
                    FROM tmerkmalwertsprache
                    JOIN tmerkmalwert 
                        ON tmerkmalwert.kMerkmalWert = tmerkmalwertsprache.kMerkmalWert
                    JOIN tmerkmalsprache
                       ON tmerkmalsprache.kMerkmal = tmerkmalwert.kMerkmal
                        AND tmerkmalsprache.kSprache = tmerkmalwertsprache.kSprache
                    WHERE tmerkmalwertsprache.kMerkmalWert = :val',
                ['val' => $value]
            );
        $currentLangID = $this->getProductFilter()->getFilterConfig()->getLanguageID();
        foreach ($languages as $language) {
            $this->cSeo[$language->kSprache] = '';
            foreach ($seoData as $seo) {
                $seo->kSprache = (int)$seo->kSprache;
                if ($language->kSprache === $seo->kSprache) {
                    $this->cSeo[$language->kSprache] = $seo->cSeo;
                    if ($language->kSprache === $currentLangID) {
                        $this->setID((int)$seo->kMerkmal)
                            ->setName($seo->cWert)
                            ->setFilterName($seo->characteristicName)
                            ->setFrontendName($seo->cWert);
                    }
                    break;
                }
            }
        }

        return $this;
    }

    /**
     * @param array<mixed> $data
     */
    private function setBatchCharacteristicData(array $data): void
    {
        $this->batchCharacteristicData = $data;
    }

    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tartikelmerkmal';
    }

    /**
     * @inheritdoc
     */
    public function getSQLCondition(): string
    {
        /** @var int $value */
        $value = $this->getValue();

        return "\n" . 'tartikelmerkmal.kArtikel IN ('
            . 'SELECT kArtikel FROM ' . $this->getTableName()
            . ' WHERE ' . $this->getPrimaryKeyRow() . ' IN (' . $value . '))';
    }

    /**
     * @inheritdoc
     */
    public function getSQLJoin(): Join
    {
        return (new Join())
            ->setType('JOIN')
            ->setTable('tartikelmerkmal')
            ->setOn('tartikel.kArtikel = tartikelmerkmal.kArtikel')
            ->setOrigin(__CLASS__);
    }

    public function characteristicValueIsActive(int $characteristicValueID): bool
    {
        return \array_reduce(
            $this->getProductFilter()->getCharacteristicFilter(),
            static fn($a, Characteristic $b): bool => $a || $b->getValue() === $characteristicValueID,
            false
        );
    }

    protected function getState(?Kategorie $category = null): StateSQLInterface
    {
        $base  = $this->getProductFilter()->getCurrentStateData(self::class);
        $state = (new StateSQL())->from($base);
        $conf  = $this->getConfig('navigationsfilter');
        $state->setOrderBy('');
        $state->setLimit('');
        $state->setGroupBy([]);
        $state->setSelect(['tmerkmal.cName']);
        $state->addJoin(
            (new Join())
                ->setType('JOIN')
                ->setTable('tartikelmerkmal')
                ->setOn('tartikel.kArtikel = tartikelmerkmal.kArtikel')
                ->setOrigin(__CLASS__)
        );
        $state->addJoin(
            (new Join())
                ->setType('JOIN')
                ->setTable('tmerkmalwert')
                ->setOn('tmerkmalwert.kMerkmalWert = tartikelmerkmal.kMerkmalWert')
                ->setOrigin(__CLASS__)
        );
        $state->addJoin(
            (new Join())
                ->setType('JOIN')
                ->setTable('tmerkmal')
                ->setOn('tmerkmal.kMerkmal = tartikelmerkmal.kMerkmal')
                ->setOrigin(__CLASS__)
        );
        $langID           = $this->getLanguageID();
        $kStandardSprache = LanguageHelper::getDefaultLanguage(false)->kSprache;
        if ($langID !== $kStandardSprache) {
            $state->setSelect([
                'COALESCE(tmerkmalsprache.cName, tmerkmal.cName) AS cName',
                'COALESCE(fremdSprache.cSeo, standardSprache.cSeo) AS cSeo',
                'COALESCE(fremdSprache.cWert, standardSprache.cWert) AS cWert'
            ]);
            $state->addJoin(
                (new Join())
                    ->setType('LEFT JOIN')
                    ->setTable('tmerkmalsprache')
                    ->setOn(
                        'tmerkmalsprache.kMerkmal = tmerkmal.kMerkmal 
                                AND tmerkmalsprache.kSprache = ' . $langID
                    )
                    ->setOrigin(__CLASS__)
            );
            $state->addJoin(
                (new Join())
                    ->setType('INNER JOIN')
                    ->setTable('tmerkmalwertsprache AS standardSprache')
                    ->setOn(
                        'standardSprache.kMerkmalWert = tartikelmerkmal.kMerkmalWert
                                AND standardSprache.kSprache = ' . $kStandardSprache
                    )
                    ->setOrigin(__CLASS__)
            );
            $state->addJoin(
                (new Join())
                    ->setType('LEFT JOIN')
                    ->setTable('tmerkmalwertsprache AS fremdSprache')
                    ->setOn(
                        'fremdSprache.kMerkmalWert = tartikelmerkmal.kMerkmalWert 
                                AND fremdSprache.kSprache = ' . $langID
                    )
                    ->setOrigin(__CLASS__)
            );
        } else {
            $state->setSelect(['tmerkmalwertsprache.cWert', 'tmerkmalwertsprache.cSeo', 'tmerkmal.cName']);
            $state->addJoin(
                (new Join())
                    ->setType('INNER JOIN')
                    ->setTable('tmerkmalwertsprache')
                    ->setOn(
                        'tmerkmalwertsprache.kMerkmalWert = tartikelmerkmal.kMerkmalWert
                            AND tmerkmalwertsprache.kSprache = ' . $langID
                    )
                    ->setOrigin(__CLASS__)
            );
        }

        if ($this->getProductFilter()->hasCharacteristicFilter()) {
            $activeOrFilterIDs  = [];
            $activeAndFilterIDs = [];
            foreach ($this->getProductFilter()->getCharacteristicFilter() as $filter) {
                $values = $filter->getValue();
                if ($filter->getType() === Type::OR) {
                    if (\is_array($values)) {
                        $activeOrFilterIDs = $values;
                    } else {
                        $activeOrFilterIDs[] = $values;
                    }
                } elseif (\is_array($values)) {
                    $activeAndFilterIDs = $values;
                } else {
                    $activeAndFilterIDs[] = $values;
                }
            }
            $productFilter = $this->getProductFilter()->showChildProducts()
                ? '(innerProduct.kVaterArtikel > 0 OR innerProduct.nIstVater = 0)'
                : 'innerProduct.kVaterArtikel = 0';

            if (\count($activeAndFilterIDs) > 0) {
                $state->addJoin(
                    (new Join())
                        ->setType('JOIN')
                        ->setTable(
                            '(SELECT kArtikel
                                    FROM tartikelmerkmal
                                        WHERE kMerkmalWert IN (' . \implode(', ', $activeAndFilterIDs) . ' )
                                    GROUP BY kArtikel
                                    HAVING COUNT(*) = ' . \count($activeAndFilterIDs) . '
                                ) AS ssj1'
                        )
                        ->setOn('tartikel.kArtikel = ssj1.kArtikel')
                        ->setOrigin(__CLASS__)
                );
            }
            if (\count($activeOrFilterIDs) > 0) {
                if ($conf['merkmalfilter_trefferanzahl_anzeigen'] === 'Y') {
                    $state->addSelect(
                        'IF(EXISTS (SELECT 1
                             FROM tartikelmerkmal AS im1
                             INNER JOIN tartikel AS innerProduct ON innerProduct.kArtikel = im1.kArtikel
                                WHERE ' . $productFilter . ' AND im1.kMerkmalWert IN ('
                        . \implode(', ', \array_merge($activeOrFilterIDs, ['tartikelmerkmal.kMerkmalWert'])) . ')
                                    AND im1.kArtikel = tartikel.kArtikel
                                GROUP BY innerProduct.kArtikel
                                HAVING COUNT(im1.kArtikel) = (SELECT COUNT(DISTINCT im2.kMerkmal)
                                   FROM tartikelmerkmal im2
                                   INNER JOIN tartikel AS innerProduct ON innerProduct.kArtikel = im2.kArtikel
                                   WHERE ' . $productFilter . ' AND im2.kMerkmalWert IN ('
                        . \implode(
                            ', ',
                            \array_merge($activeOrFilterIDs, ['tartikelmerkmal.kMerkmalWert'])
                        ) . '))), tartikel.kArtikel, NULL) AS kArtikel'
                    );
                } else {
                    /* Der Kommentar mit den integrierten $activeOrFilterIDs ist hier notwendig,
                       um bei aktiviertem Cache die Query unterscheidbar zu machen.
                       Die Cache-ID wird als md5 über den Query-String ermittelt. */
                    $state->addSelect('#' . \implode(',', $activeOrFilterIDs) . "\ntartikel.kArtikel AS kArtikel");
                }
            } else {
                $state->addSelect('tartikel.kArtikel AS kArtikel');
            }
        } else {
            $state->addSelect('tartikel.kArtikel AS kArtikel');
        }
        $state->addSelect('tartikelmerkmal.kMerkmal');
        $state->addSelect('tartikelmerkmal.kMerkmalWert');
        $state->addSelect('tmerkmalwert.cBildPfad AS cMMWBildPfad');
        $state->addSelect('tmerkmal.nSort AS nSortMerkmal');
        $state->addSelect('tmerkmalwert.nSort');
        $state->addSelect('tmerkmal.cTyp');
        $state->addSelect('tmerkmal.nMehrfachauswahl');
        $state->addSelect('tmerkmal.cBildPfad AS cMMBildPfad');
        if (
            $category !== null
            && !empty($category->getCategoryFunctionAttribute(\KAT_ATTRIBUT_MERKMALFILTER))
            && $this->getProductFilter()->hasCategory()
        ) {
            $catAttributeFilters = \explode(';', $category->getCategoryFunctionAttribute(\KAT_ATTRIBUT_MERKMALFILTER));
            if (\count($catAttributeFilters) > 0) {
                $state->addCondition(
                    'tmerkmal.cName IN ('
                    . \implode(',', map($catAttributeFilters, static fn($e): string => '"' . $e . '"')) . ')'
                );
            }
        }

        return $state;
    }

    /**
     * @inheritdoc
     */
    public function getOptions($mixed = null): array
    {
        if ($this->options !== null) {
            return $this->options;
        }
        $conf                    = $this->getConfig('navigationsfilter');
        $force                   = $mixed['bForce'] ?? false;
        $characteristicFilters   = [];
        $useCharacteristicFilter = $conf['merkmalfilter_verwenden'] !== 'N';
        $limit                   = $force === true
            ? 0
            : (int)$conf['merkmalfilter_maxmerkmale'];
        $valueLimit              = $force === true
            ? 0
            : (int)$conf['merkmalfilter_maxmerkmalwerte'];
        if (!$force && !$useCharacteristicFilter) {
            return $characteristicFilters;
        }
        $state     = $this->getState($mixed['oAktuelleKategorie'] ?? null);
        $baseQuery = $this->getProductFilter()->getFilterSQL()->getBaseQuery($state);
        $cache     = $this->getProductFilter()->getCache();
        $cacheID   = $this->getCacheID($baseQuery) . '_' . $limit . '_' . $valueLimit;
        if (($filterCollection = $this->getProductFilter()->getCache()->get($cacheID)) === false) {
            $qryRes           = $this->getProductFilter()->getDB()->getObjects(
                'SELECT ssMerkmal.cSeo, ssMerkmal.kMerkmal, ssMerkmal.kMerkmalWert, ssMerkmal.cMMWBildPfad, 
            ssMerkmal.nMehrfachauswahl, ssMerkmal.cWert, ssMerkmal.cName, ssMerkmal.cTyp, 
            ssMerkmal.cMMBildPfad, '
                . ($conf['merkmalfilter_trefferanzahl_anzeigen'] !== 'N'
                    ? 'COUNT(DISTINCT ssMerkmal.kArtikel)'
                    : '1') . ' AS nAnzahl
                FROM (' . $baseQuery . ') AS ssMerkmal
                GROUP BY ssMerkmal.kMerkmalWert
                ORDER BY ssMerkmal.nSortMerkmal, ssMerkmal.nSort, ssMerkmal.cWert'
            );
            $filterCollection = group($qryRes, fn(stdClass $e) => $e->kMerkmal);
            foreach ($filterCollection as $characteristicID => $characteristicValues) {
                $first                                = first($characteristicValues);
                $characteristic                       = new stdClass();
                $characteristic->kMerkmal             = (int)$first->kMerkmal;
                $characteristic->nMehrfachauswahl     = (int)$first->nMehrfachauswahl;
                $characteristic->cName                = $first->cName;
                $characteristic->cMMBildPfad          = $first->cMMBildPfad;
                $characteristic->cTyp                 = $first->cTyp;
                $characteristic->characteristicValues = map(
                    $characteristicValues,
                    static function (stdClass $e): stdClass {
                        $av               = new stdClass();
                        $av->kMerkmal     = (int)$e->kMerkmal;
                        $av->kMerkmalWert = (int)$e->kMerkmalWert;
                        $av->cMMWBildPfad = $e->cMMWBildPfad;
                        $av->cWert        = $e->cWert;
                        $av->nAnzahl      = (int)$e->nAnzahl;

                        return $av;
                    }
                );
                $filterCollection[$characteristicID]  = $characteristic;
            }

            $cache->set(
                $cacheID,
                $filterCollection,
                [\CACHING_GROUP_FILTER, \CACHING_GROUP_FILTER_CHARACTERISTIC]
            );
        }
        $currentValue       = $this->getProductFilter()->getCharacteristicValue()->getValue();
        $additionalFilter   = new self($this->getProductFilter());
        $imageBaseURL       = Shop::getImageBaseURL();
        $filterURLGenerator = $this->getProductFilter()->getFilterURL();
        $i                  = 0;
        foreach ($filterCollection as $filter) {
            $baseSrcSmall  = \mb_strlen($filter->cMMBildPfad) > 0
                ? \PFAD_MERKMALBILDER_KLEIN . $filter->cMMBildPfad
                : \BILD_KEIN_MERKMALBILD_VORHANDEN;
            $baseSrcNormal = \mb_strlen($filter->cMMBildPfad) > 0
                ? \PFAD_MERKMALBILDER_NORMAL . $filter->cMMBildPfad
                : \BILD_KEIN_MERKMALBILD_VORHANDEN;

            $option = new CharacteristicOption();
            $option->setURL('');
            $option->setData('cTyp', $filter->cTyp)
                ->setData('kMerkmal', $filter->kMerkmal)
                ->setData('cBildpfadKlein', $baseSrcSmall)
                ->setData('cBildpfadNormal', $baseSrcNormal)
                ->setData('cBildURLKlein', $imageBaseURL . $baseSrcSmall)
                ->setData('cBildURLNormal', $imageBaseURL . $baseSrcNormal)
                ->setData('isMultiSelect', $filter->nMehrfachauswahl > 0);
            $option->setImageType(Image::TYPE_CHARACTERISTIC);
            $option->setID($filter->kMerkmal);
            $option->setParam($this->getUrlParam());
            $option->setType($filter->nMehrfachauswahl === 1 ? Type::OR : Type::AND);
            $option->setType($this->getType());
            $option->setClassName($this->getClassName());
            $option->setName($filter->cName);
            $option->setFrontendName($filter->cName);
            $option->setValue($filter->kMerkmal);
            $option->setCount(0);
            $option->generateCachableData($cache, $this->getLanguageID());
            $additionalFilter->setBatchCharacteristicData(
                $this->batchGetDataForCharacteristicValue($filter->characteristicValues)
            );
            foreach ($filter->characteristicValues as $filterValue) {
                $filterValue->kMerkmalWert = (int)$filterValue->kMerkmalWert;
                $characteristicOption      = new CharacteristicValueOption();
                $baseSrcSmall              = \mb_strlen($filterValue->cMMWBildPfad) > 0
                    ? \PFAD_MERKMALWERTBILDER_KLEIN . $filterValue->cMMWBildPfad
                    : \BILD_KEIN_MERKMALWERTBILD_VORHANDEN;
                $baseSrcNormal             = \mb_strlen($filterValue->cMMWBildPfad) > 0
                    ? \PFAD_MERKMALWERTBILDER_NORMAL . $filterValue->cMMWBildPfad
                    : \BILD_KEIN_MERKMALWERTBILD_VORHANDEN;
                $characteristicOption->setData('kMerkmalWert', $filterValue->kMerkmalWert)
                    ->setData('kMerkmal', (int)$filter->kMerkmal)
                    ->setData('cWert', $filterValue->cWert);
                $characteristicOption->setIsActive(
                    $currentValue === $filterValue->kMerkmalWert
                    || $this->characteristicValueIsActive($filterValue->kMerkmalWert)
                );
                $characteristicOption->setData('cBildpfadKlein', $baseSrcSmall)
                    ->setData('cBildpfadNormal', $baseSrcNormal)
                    ->setData('cBildURLKlein', $imageBaseURL . $baseSrcSmall)
                    ->setData('cBildURLNormal', $imageBaseURL . $baseSrcNormal);
                $characteristicOption->setType($filter->nMehrfachauswahl === 1 ? Type::OR : Type::AND);
                $characteristicOption->setClassName($this->getClassName());
                $characteristicOption->setParam($this->getUrlParam());
                $characteristicOption->setName(\htmlentities($filterValue->cWert));
                $characteristicOption->setValue($filterValue->cWert);
                $characteristicOption->setCount((int)$filterValue->nAnzahl);
                $characteristicOption->setImageType(Image::TYPE_CHARACTERISTIC_VALUE);
                $characteristicOption->setID($filterValue->kMerkmalWert);
                if ($characteristicOption->isActive()) {
                    $option->setIsActive(true);
                }
                $url = $filterURLGenerator->getURL($additionalFilter->init($filterValue->kMerkmalWert));
                $characteristicOption->setURL($url);
                $characteristicOption->generateCachableData($cache, $this->getLanguageID());
                $option->addOption($characteristicOption);
            }
            // backwards compatibility
            $characteristicOptions = $option->getOptions();
            $option->setData('oMerkmalWerte_arr', $characteristicOptions);
            if (($optionsCount = \count($characteristicOptions)) > 0) {
                $characteristicFilters[] = $option->setCount($optionsCount);
            }
            if ($limit > 0 && ++$i >= $limit) {
                break;
            }
        }
        foreach ($characteristicFilters as $af) {
            $options = $af->getOptions();
            if (!\is_array($options)) {
                continue;
            }
            if ($this->isNumeric($af)) {
                $this->sortNumeric($af);
            }
            $this->applyOptionLimit($af, $valueLimit);
        }
        $this->options = $characteristicFilters;

        return $characteristicFilters;
    }

    protected function isNumeric(Option $option): bool
    {
        return every($option->getOptions(), fn(Option $item): bool => \is_numeric($item->getValue()));
    }

    protected function sortNumeric(Option $option): void
    {
        $options = $option->getOptions();
        \usort($options, static fn(Option $a, Option $b): int => $a->getValue() <=> $b->getValue());
        $option->setOptions($options);
    }

    protected function sortByCountDesc(Option $option): void
    {
        $options = $option->getOptions();
        \usort($options, static fn(Option $a, Option $b): int => -($a->getCount() <=> $b->getCount()));
        $option->setOptions($options);
    }

    protected function applyOptionLimit(Option $option, int $limit): void
    {
        if ($limit <= 0 || $limit >= \count($option->getOptions())) {
            return;
        }
        $this->sortByCountDesc($option);
        $option->setOptions(\array_slice($option->getOptions(), 0, $limit));
    }

    /**
     * @param array<mixed> $characteristicValues
     * @return array<int, array<int, stdClass>>
     */
    protected function batchGetDataForCharacteristicValue(array $characteristicValues): array
    {
        if (\count($characteristicValues) === 0) {
            return [];
        }
        $characteristicValueIDs = \implode(
            ',',
            \array_map(static fn($row): int => (int)$row->kMerkmalWert, $characteristicValues)
        );
        $queryResult            = $this->getProductFilter()->getDB()->getObjects(
            'SELECT tmerkmalwertsprache.cWert, tmerkmalwertsprache.kMerkmalWert, tmerkmalwertsprache.cSeo,
            tmerkmalwert.kMerkmal, tmerkmalwertsprache.kSprache, tmerkmalsprache.cName AS characteristicName
                FROM tmerkmalwertsprache
                JOIN tmerkmalwert 
                    ON tmerkmalwert.kMerkmalWert = tmerkmalwertsprache.kMerkmalWert
                JOIN tmerkmalsprache
                    ON tmerkmalsprache.kMerkmal = tmerkmalwert.kMerkmal
                    AND tmerkmalsprache.kSprache = tmerkmalwertsprache.kSprache
                WHERE tmerkmalwertsprache.kMerkmalWert IN (' . $characteristicValueIDs . ')'
        );
        $result                 = [];
        foreach ($queryResult as $row) {
            $row->kMerkmalWert = (int)$row->kMerkmalWert;
            $row->kMerkmal     = (int)$row->kMerkmal;
            $row->kSprache     = (int)$row->kSprache;
            if (!isset($result[$row->kMerkmalWert])) {
                $result[$row->kMerkmalWert] = [];
            }
            $result[$row->kMerkmalWert][$row->kSprache] = $row;
        }

        return $result;
    }
}
