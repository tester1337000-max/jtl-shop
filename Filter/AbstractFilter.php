<?php

declare(strict_types=1);

namespace JTL\Filter;

use JTL\Language\LanguageModel;
use JTL\Shop;

/**
 * Class AbstractFilter
 * @package JTL\Filter
 */
abstract class AbstractFilter implements FilterInterface
{
    protected ?string $icon = null;

    protected bool $isCustom = true;

    private ?string $name = null;

    /**
     * @var array<int, string>
     */
    public array $cSeo = [];

    /**
     * @phpstan-var Type::*
     */
    protected int $type = Type::AND;

    protected string $urlParam = '';

    protected ?string $urlParamSEO = '';

    /**
     * @var int|string|array<mixed>|null
     */
    protected mixed $value = null;

    protected int $customerGroupID = 0;

    /**
     * @var LanguageModel[]
     */
    protected array $availableLanguages = [];

    protected bool $isInitialized = false;

    /**
     * @var class-string<FilterInterface>
     */
    protected string $className = '';

    protected string $niceName = '';

    /**
     * @phpstan-var InputType::*
     */
    protected int $inputType = InputType::SELECT;

    /**
     * @var Option[]|null
     */
    protected ?array $activeValues = null;

    /**
     * workaround since built-in filters can be registered multiple times (like Navigationsfilter->KategorieFilter)
     * this makes sure there value is not used more then once when Navigationsfilter::getURL()
     * generates the current URL.
     */
    private bool $isChecked = false;

    /**
     * used to create FilterLoesenURLs
     */
    private bool $doUnset = false;

    /**
     * @var string|array<int|string, string>
     */
    private string|array $unsetFilterURL = '';

    /**
     * @phpstan-var Visibility::*
     */
    private int $visibility = Visibility::SHOW_ALWAYS;

    private int $count = 0;

    private int $sort = 0;

    protected string $frontendName = '';

    /**
     * list of filter options for CharacteristicFilters etc. that consist of multiple different filter options
     *
     * @var FilterInterface[]
     */
    private array $filterCollection = [];

    protected ?ProductFilter $productFilter = null;

    /**
     * @var mixed|null
     */
    protected mixed $options = null;

    protected string $tableName = '';

    protected bool $isActive = false;

    protected bool $paramExclusive = false;

    /**
     * @var string|null - localized name of the characteristic itself
     */
    protected ?string $filterName = null;

    protected bool $notFound = false;

    public function __construct(?ProductFilter $productFilter = null)
    {
        if ($productFilter !== null) {
            $this->setBaseData($productFilter)->setClassName(\get_class($this));
        }
    }

    /**
     * @inheritdoc
     */
    public function init($value): FilterInterface
    {
        if ($value !== null) {
            $this->isInitialized = true;
            $this->setValue($value)->setSeo($this->availableLanguages);
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * @inheritdoc
     */
    public function setIsActive(bool $active): FilterInterface
    {
        $this->isActive = $active;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setIsInitialized(bool $value): FilterInterface
    {
        $this->isInitialized = $value;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function generateActiveFilterData(): FilterInterface
    {
        $this->activeValues = [];
        $values             = $this->getValue();
        $split              = true;
        if (!\is_array($values)) {
            $split  = false;
            $values = [$values];
        }
        foreach ($values as $value) {
            if ($split === true) {
                $class = $this->getClassName();
                /** @var FilterInterface $instance */
                $instance = new $class($this->getProductFilter());
                $instance->init($value);
            } else {
                $instance = $this;
            }
            $option = new Option();
            $option->setURL($this->getSeo($this->getLanguageID()));
            $option->setFrontendName($instance->getName() ?? '');
            $option->setValue($value);
            $option->setName($instance->getFrontendName());
            $option->setType($this->getType());

            $this->activeValues[] = $option;
        }
        $this->isActive = true;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setFilterCollection(array $collection): FilterInterface
    {
        $this->filterCollection = $collection;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getFilterCollection(bool $onlyVisible = true): array
    {
        return $onlyVisible === false
            ? $this->filterCollection
            : \array_filter(
                $this->filterCollection,
                static fn(FilterInterface $f): bool => $f->getVisibility() !== Visibility::SHOW_NEVER
            );
    }

    /**
     * @inheritdoc
     */
    public function setFrontendName(string $name): FilterInterface
    {
        $this->frontendName = \htmlspecialchars($name);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getFrontendName(): string
    {
        return $this->frontendName;
    }

    /**
     * @inheritdoc
     */
    public function getVisibility(): int
    {
        return $this->visibility;
    }

    /**
     * @inheritdoc
     */
    public function setVisibility($visibility): FilterInterface
    {
        $this->visibility = Visibility::SHOW_NEVER;
        if (\is_numeric($visibility)) {
            $this->visibility = (int)$visibility;
        } elseif ($visibility === 'content') {
            $this->visibility = Visibility::SHOW_CONTENT;
        } elseif ($visibility === 'box') {
            $this->visibility = Visibility::SHOW_BOX;
        } elseif ($visibility === 'Y') {
            $this->visibility = Visibility::SHOW_ALWAYS;
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setUnsetFilterURL($url): FilterInterface
    {
        $this->unsetFilterURL = $url;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getUnsetFilterURL($idx = null): ?string
    {
        if (\is_array($idx) && \count($idx) === 1) {
            $idx = $idx[0];
        }

        return $idx === null || \is_string($this->unsetFilterURL)
            ? $this->unsetFilterURL
            : $this->unsetFilterURL[$idx];
    }

    /**
     * @inheritdoc
     */
    public function getAvailableLanguages(): array
    {
        return $this->availableLanguages;
    }

    /**
     * @inheritdoc
     */
    public function addValue($value): FilterInterface
    {
        $this->value[] = (int)$value;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isInitialized(): bool
    {
        return $this->isInitialized;
    }

    /**
     * @inheritdoc
     */
    public function getSeo($idx = null)
    {
        return $idx !== null
            ? ($this->cSeo[$idx] ?? null)
            : $this->cSeo;
    }

    /**
     * @inheritdoc
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * @inheritdoc
     */
    public function setType(int $type): FilterInterface
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function setName($name): FilterInterface
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getOptions($mixed = null): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function setOptions($options): FilterInterface
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setProductFilter(ProductFilter $productFilter): FilterInterface
    {
        $this->productFilter = $productFilter;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getProductFilter(): ProductFilter
    {
        return $this->productFilter;
    }

    /**
     * @inheritdoc
     */
    public function setBaseData(ProductFilter $productFilter): FilterInterface
    {
        $this->productFilter      = $productFilter;
        $this->customerGroupID    = $productFilter->getFilterConfig()->getCustomerGroupID();
        $this->availableLanguages = $productFilter->getFilterConfig()->getLanguages();

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getUrlParam(): string
    {
        return $this->urlParam;
    }

    /**
     * @inheritdoc
     */
    public function setUrlParam($param): FilterInterface
    {
        $this->urlParam = $param;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getUrlParamSEO(): ?string
    {
        return $this->urlParamSEO;
    }

    /**
     * @inheritdoc
     */
    public function setUrlParamSEO($param): FilterInterface
    {
        $this->urlParamSEO = $param;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isCustom(): bool
    {
        return $this->isCustom;
    }

    /**
     * @inheritdoc
     */
    public function setIsCustom(bool $custom): FilterInterface
    {
        $this->isCustom = $custom;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getLanguageID(): int
    {
        return $this->getProductFilter()->getFilterConfig()->getLanguageID() ?: Shop::getLanguageID();
    }

    /**
     * @inheritdoc
     */
    public function getCustomerGroupID(): int
    {
        return $this->customerGroupID;
    }

    /**
     * @inheritdoc
     */
    public function getConfig($idx = null): array
    {
        return $this->getProductFilter()->getFilterConfig()->getConfig($idx);
    }

    /**
     * @inheritdoc
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * @inheritdoc
     */
    public function setClassName($className): FilterInterface
    {
        $this->className = $className;
        $this->setNiceName(\basename(\str_replace('\\', '/', $className)));

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getNiceName(): string
    {
        return $this->niceName;
    }

    /**
     * @inheritdoc
     */
    public function setNiceName($name): FilterInterface
    {
        $this->niceName = $name;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getIsChecked(): bool
    {
        return $this->isChecked;
    }

    /**
     * @inheritdoc
     */
    public function setIsChecked(bool $isChecked): FilterInterface
    {
        $this->isChecked = $isChecked;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getDoUnset(): bool
    {
        return $this->doUnset;
    }

    /**
     * @inheritdoc
     */
    public function setDoUnset(bool $doUnset): FilterInterface
    {
        $this->doUnset = $doUnset;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getInputType(): int
    {
        return $this->inputType;
    }

    /**
     * @inheritdoc
     */
    public function setInputType(int $type): FilterInterface
    {
        $this->inputType = $type;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /**
     * @inheritdoc
     */
    public function setIcon($icon): FilterInterface
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getTableAlias(): string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * @inheritdoc
     */
    public function setTableName($name): FilterInterface
    {
        $this->tableName = $name;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getActiveValues()
    {
        $activeValues = $this->activeValues ?? $this;
        if (\is_array($activeValues) && \count($activeValues) === 1) {
            $activeValues = $activeValues[0];
        }

        return $activeValues;
    }

    /**
     * @inheritdoc
     */
    public function hide(): FilterInterface
    {
        $this->visibility = Visibility::SHOW_NEVER;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isHidden(): bool
    {
        return $this->visibility === Visibility::SHOW_NEVER;
    }

    /**
     * @inheritdoc
     */
    public function getPrimaryKeyRow(): string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getSQLCondition(): string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getSQLJoin()
    {
        return new Join();
    }

    /**
     * @inheritdoc
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @inheritdoc
     */
    public function setValue($value): FilterInterface
    {
        $this->value = $value;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * @inheritdoc
     */
    public function setCount(int $count): FilterInterface
    {
        $this->count = $count;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getSort(): int
    {
        return $this->sort;
    }

    /**
     * @inheritdoc
     */
    public function setSort(int $sort): FilterInterface
    {
        $this->sort = $sort;

        return $this;
    }

    /**
     * @return int
     */
    public function getValueCompat()
    {
        return $this->value;
    }

    /**
     * this is only called when someone tries to directly set $NaviFilter->Suchanfrage->kSuchanfrage,
     * $NaviFilter-Kategorie->kKategorie etc.
     * it implies that this filter has to be enabled afterwards
     *
     * @param int $value
     * @return $this
     */
    public function setValueCompat($value): FilterInterface
    {
        $this->value = (int)$value;
        if ($this->value > 0 && $this->productFilter !== null) {
            $this->productFilter->enableFilter($this);
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isParamExclusive(): bool
    {
        return $this->paramExclusive;
    }

    /**
     * @inheritdoc
     */
    public function setParamExclusive(bool $paramExclusive): FilterInterface
    {
        $this->paramExclusive = $paramExclusive;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getFilterName(): ?string
    {
        return $this->filterName;
    }

    /**
     * @inheritdoc
     */
    public function setFilterName(?string $characteristic): FilterInterface
    {
        $this->filterName = $characteristic;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $res                  = \get_object_vars($this);
        $res['config']        = '*truncated*';
        $res['productFilter'] = '*truncated*';

        return $res;
    }

    /**
     * @inheritdoc
     */
    public function getCacheID(string $query): string
    {
        $value = $this->getValue();
        try {
            $valuePart = $value === null ? '' : \json_encode($value, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $valuePart = '';
        }

        return 'fltr_' . \str_replace('\\', '', static::class)
            . '_' . $this->getLanguageID()
            . '_' . \md5($query)
            . $valuePart;
    }

    /**
     * @inheritdoc
     */
    public function getRoute(array $additional): ?string
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function isNotFound(): bool
    {
        return $this->notFound;
    }

    protected function fail(): void
    {
        Shop::$is404             = true;
        Shop::$kKategorie        = 0;
        Shop::$kSuchspecial      = 0;
        Shop::$kMerkmalWert      = 0;
        Shop::$kHersteller       = 0;
        $this->notFound          = true;
        $state                   = Shop::getState();
        $state->is404            = true;
        $state->categoryID       = 0;
        $state->searchSpecialID  = 0;
        $state->characteristicID = 0;
        $state->manufacturerID   = 0;
    }
}
