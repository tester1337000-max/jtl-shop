<?php

declare(strict_types=1);

namespace JTL\Backend\Settings;

use JTL\MagicCompatibilityTrait;
use stdClass;

/**
 * Class Item
 * @package JTL\Backend\Settings
 */
class Item
{
    use MagicCompatibilityTrait;

    private int $id = 0;

    private bool $configurable = false;

    private ?string $inputType;

    private mixed $setValue;

    private string $name = '';

    private string $highlightedName = '';

    private string $valueName = '';

    private string $description = '';

    private int $configSectionID = 0;

    private int $showDefault = 0;

    private int $sort = 0;

    private ?string $moduleID;

    private int $moduleNumber = 0;

    private int $pluginID = 0;

    /**
     * @var stdClass[]|null
     */
    private ?array $values;

    private bool $highlight = false;

    private mixed $defaultValue = null;

    private mixed $currentValue = null;

    protected ?string $url = null;

    protected ?string $path = null;

    /**
     * @var string[]
     */
    protected static array $mapping = [
        'cConf'                 => 'ConfigurableCompat',
        'cInputTyp'             => 'InputType',
        'gesetzterWert'         => 'SetValue',
        'cWertName'             => 'ValueName',
        'cName'                 => 'Name',
        'kEinstellungenSektion' => 'ConfigSectionID',
        'nStandardAnzeigen'     => 'ShowDefault',
        'nSort'                 => 'Sort',
        'nModul'                => 'ModuleNumber',
        'cModulId'              => 'ModuleID',
        'kEinstellungenConf'    => 'ID',
        'cBeschreibung'         => 'Description',
        'ConfWerte'             => 'Values',
    ];

    public function parseFromDB(stdClass $dbItem): void
    {
        $this->setID((int)($dbItem->kEinstellungenConf ?? 0));
        $this->setConfigSectionID((int)($dbItem->kEinstellungenSektion ?? 0));
        $this->setName($dbItem->cName ?? '');
        $this->setValueName($dbItem->cWertName ?? '');
        $this->setDescription($dbItem->cBeschreibung ?? '');
        $this->setInputType($dbItem->cInputTyp ?? null);
        $this->setModuleID($dbItem->cModulId ?? null);
        $this->setSort((int)($dbItem->nSort ?? 0));
        $this->setShowDefault((int)($dbItem->nStandardAnzeigen ?? 0));
        $this->setModuleNumber((int)($dbItem->nModul ?? 0));
        $this->setConfigurable(($dbItem->cConf ?? 'N') === 'Y' || ($dbItem->cConf ?? 'N') === 'M');
        $this->setCurrentValue($dbItem->currentValue ?? null);
        $this->setDefaultValue($dbItem->defaultValue ?? null);
        $this->setPluginID((int)($dbItem->kPlugin ?? 0));
        if ($this->getValueName() === 'caching_types_disabled') {
            $this->setConfigurable(false);
        }
    }

    public function getConfigurableCompat(): string
    {
        return $this->configurable ? 'Y' : 'N';
    }

    public function setConfigurableCompat(string $value): void
    {
        $this->configurable = $value === 'Y';
    }

    public function getID(): int
    {
        return $this->id;
    }

    public function setID(int $id): void
    {
        $this->id = $id;
    }

    public function isConfigurable(): bool
    {
        return $this->configurable;
    }

    public function setConfigurable(bool $configurable): void
    {
        $this->configurable = $configurable;
    }

    public function getInputType(): ?string
    {
        return $this->inputType;
    }

    public function setInputType(?string $inputType): void
    {
        $this->inputType = $inputType;
    }

    public function getSetValue(): mixed
    {
        return $this->setValue;
    }

    public function setSetValue(mixed $setValue): void
    {
        $this->setValue = $setValue;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getHighlightedName(): string
    {
        return $this->highlightedName;
    }

    public function setHighlightedName(string $highlightedName): void
    {
        $this->highlightedName = $highlightedName;
    }

    public function getValueName(): string
    {
        return $this->valueName;
    }

    public function setValueName(string $valueName): void
    {
        $this->valueName = $valueName;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getConfigSectionID(): int
    {
        return $this->configSectionID;
    }

    public function setConfigSectionID(int $configSectionID): void
    {
        $this->configSectionID = $configSectionID;
    }

    public function getShowDefault(): int
    {
        return $this->showDefault;
    }

    public function setShowDefault(int $showDefault): void
    {
        $this->showDefault = $showDefault;
    }

    public function getSort(): int
    {
        return $this->sort;
    }

    public function setSort(int $sort): void
    {
        $this->sort = $sort;
    }

    public function getModuleID(): ?string
    {
        return $this->moduleID;
    }

    public function setModuleID(?string $moduleID): void
    {
        $this->moduleID = $moduleID;
    }

    public function getModuleNumber(): int
    {
        return $this->moduleNumber;
    }

    public function setModuleNumber(int $moduleNumber): void
    {
        $this->moduleNumber = $moduleNumber;
    }

    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    public function setDefaultValue(mixed $defaultValue): void
    {
        $this->defaultValue = $defaultValue;
    }

    public function getCurrentValue(): mixed
    {
        return $this->currentValue;
    }

    public function setCurrentValue(mixed $currentValue): void
    {
        $this->currentValue = $currentValue;
    }

    /**
     * @return stdClass[]|null
     */
    public function getValues(): ?array
    {
        return $this->values;
    }

    /**
     * @param stdClass[]|null $values
     */
    public function setValues(?array $values): void
    {
        $this->values = $values;
    }

    public function getPluginID(): int
    {
        return $this->pluginID;
    }

    public function setPluginID(int $pluginID): void
    {
        $this->pluginID = $pluginID;
    }

    public function isHighlight(): bool
    {
        return $this->highlight;
    }

    public function setHighlight(bool $highlight): void
    {
        $this->highlight = $highlight;
    }

    public function getURL(): ?string
    {
        return $this->url;
    }

    public function setURL(?string $url): void
    {
        $this->url = $url;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): void
    {
        $this->path = $path;
    }
}
