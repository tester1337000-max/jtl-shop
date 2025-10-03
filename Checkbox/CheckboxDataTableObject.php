<?php

declare(strict_types=1);

namespace JTL\Checkbox;

use JTL\Checkbox\CheckboxLanguage\CheckboxLanguageDataTableObject;
use JTL\DataObjects\AbstractDataObject;
use JTL\DataObjects\DataTableObjectInterface;

/**
 * Class CheckboxDataTableObject
 * @package JTL\Checkbox
 */
class CheckboxDataTableObject extends AbstractDataObject implements DataTableObjectInterface
{
    private string $primaryKey = 'kCheckBox';

    protected int $checkboxID = 0;

    protected int $linkID = 0;

    protected int $checkboxFunctionID = 0;

    protected string $name = '';

    protected string $customerGroupsSelected = '';

    protected string $displayAt = '';

    protected bool $active = true;

    protected bool $isMandatory = false;

    protected bool $hasLogging = true;

    protected int $sort = 0;

    protected string $created = '';

    protected bool $internal = false;

    private string $created_DE = '';

    /**
     * @var array<string, array<mixed>>
     */
    private array $languages = [];

    private bool $nLink = false;

    /**
     * @var CheckboxLanguageDataTableObject[]
     */
    private array $checkBoxLanguage_arr = [];

    /**
     * @var array<mixed>
     */
    private array $customerGroup_arr = [];

    /**
     * @var string[]
     */
    private array $displayAt_arr = [];

    /**
     * @var array<string, string>
     */
    private static array $mapping = [
        'checkboxID'             => 'checkboxID',
        'linkID'                 => 'linkID',
        'checkboxFunctionID'     => 'checkboxFunctionID',
        'name'                   => 'name',
        'customerGroupsSelected' => 'customerGroupsSelected',
        'kKundengruppe'          => 'customerGroupsSelected',
        'displayAt'              => 'displayAt',
        'active'                 => 'active',
        'isMandatory'            => 'isMandatory',
        'hasLogging'             => 'hasLogging',
        'sort'                   => 'sort',
        'created'                => 'created',
        'nlink'                  => 'hasLink',
        'nFunction'              => 'hasFunction',
        'created_DE'             => 'createdDE',
        'oCheckBoxLanguage_arr'  => 'checkBoxLanguage_arr',
        'customerGroup_arr'      => 'customerGroup_arr',
        'displayAt_arr'          => 'displayAt_arr',
        'internal'               => 'internal',
    ];

    /**
     * @var array<string, string>
     */
    private static array $columnMapping = [
        'kCheckBox'            => 'checkboxID',
        'kLink'                => 'linkID',
        'kCheckBoxFunktion'    => 'checkboxFunctionID',
        'cName'                => 'name',
        'cKundengruppe'        => 'customerGroupsSelected',
        'cAnzeigeOrt'          => 'displayAt',
        'nAktiv'               => 'active',
        'nPflicht'             => 'isMandatory',
        'nLogging'             => 'hasLogging',
        'nSort'                => 'sort',
        'dErstellt'            => 'created',
        'dErstellt_DE'         => 'createdDE',
        'oCheckBoxSprache_arr' => 'checkBoxLanguage_arr',
        'kKundengruppe_arr'    => 'customerGroup_arr',
        'kAnzeigeOrt_arr'      => 'displayAt_arr',
        'nInternal'            => 'internal',
    ];

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    /**
     * @inheritdoc
     */
    public function getMapping(): array
    {
        return \array_merge(self::$mapping, self::$columnMapping);
    }

    /**
     * @inheritdoc
     */
    public function getReverseMapping(): array
    {
        return \array_flip(self::$mapping);
    }

    /**
     * @inheritdoc
     */
    public function getColumnMapping(): array
    {
        return \array_flip(self::$columnMapping);
    }

    /**
     * @inheritdoc
     */
    public function getID(): mixed
    {
        return $this->{$this->getPrimaryKey()};
    }

    public function getCheckboxID(): int
    {
        return $this->checkboxID;
    }

    public function setCheckboxID(int|string $checkboxID): CheckboxDataTableObject
    {
        $this->checkboxID = (int)$checkboxID;

        return $this;
    }

    public function getLinkID(): int
    {
        return $this->linkID;
    }

    public function setLinkID(int|string|null $linkID): CheckboxDataTableObject
    {
        $this->linkID = (int)$linkID;

        return $this;
    }

    public function getCheckboxFunctionID(): int
    {
        return $this->checkboxFunctionID;
    }

    public function setCheckboxFunctionID(int|string|null $checkboxFunctionID): CheckboxDataTableObject
    {
        $this->checkboxFunctionID = (int)$checkboxFunctionID;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): CheckboxDataTableObject
    {
        $this->name = $name;

        return $this;
    }

    public function getCustomerGroupsSelected(): string
    {
        return $this->customerGroupsSelected;
    }

    /**
     * @param string[]|string $customerGroupsSelected
     * @return CheckboxDataTableObject
     */
    public function setCustomerGroupsSelected(array|string $customerGroupsSelected): CheckboxDataTableObject
    {
        if (\is_array($customerGroupsSelected)) {
            $customerGroupsSelected = ';' . \implode(';', $customerGroupsSelected) . ';';
        }
        $this->customerGroupsSelected = $customerGroupsSelected;
        $this->setCustomerGroupArr(\array_filter(\explode(';', $customerGroupsSelected)));

        return $this;
    }

    public function getDisplayAt(): string
    {
        return $this->displayAt;
    }

    /**
     * @param string[]|string $displayAt
     * @return CheckboxDataTableObject
     */
    public function setDisplayAt(array|string $displayAt): CheckboxDataTableObject
    {
        if (\is_array($displayAt)) {
            $displayAt = ';' . \implode(';', $displayAt) . ';';
        }
        $this->displayAt = $displayAt;
        $this->setDisplayAtArr(\array_filter(\explode(';', $displayAt)));

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool|int|string $active): CheckboxDataTableObject
    {
        $this->active = $this->checkAndReturnBoolValue($active);

        return $this;
    }

    public function isMandatory(): bool
    {
        return $this->isMandatory;
    }

    public function setIsMandatory(bool|int|string $isMandatory): CheckboxDataTableObject
    {
        $this->isMandatory = $this->checkAndReturnBoolValue($isMandatory);

        return $this;
    }

    public function isLogging(): bool
    {
        return $this->hasLogging;
    }

    public function setHasLogging(bool|int|string $hasLogging): CheckboxDataTableObject
    {
        $this->hasLogging = $this->checkAndReturnBoolValue($hasLogging);

        return $this;
    }

    public function getSort(): int
    {
        return $this->sort;
    }

    public function setSort(int|string $sort): CheckboxDataTableObject
    {
        $this->sort = (int)$sort;

        return $this;
    }

    public function getCreated(): string
    {
        return $this->created;
    }

    public function setCreated(string $created): CheckboxDataTableObject
    {
        $this->created = $created;

        return $this;
    }

    public function getInternal(): bool
    {
        return $this->internal;
    }

    public function setInternal(bool|int|string $internal): void
    {
        $this->internal = $this->checkAndReturnBoolValue($internal);
    }

    public function getCreatedDE(): string
    {
        return $this->created_DE;
    }

    public function setCreatedDE(string $created_DE): CheckboxDataTableObject
    {
        $this->created_DE = $created_DE;

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function getLanguages(): array
    {
        return $this->languages;
    }

    /**
     * @param array<mixed> $language
     */
    public function addLanguage(string $code, array $language): CheckboxDataTableObject
    {
        $this->languages[$code] = $language;

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function getCheckBoxLanguageArr(): array
    {
        return $this->checkBoxLanguage_arr;
    }

    /**
     * @param array<mixed> $checkBoxLanguage_arr
     */
    public function setCheckBoxLanguageArr(array $checkBoxLanguage_arr): CheckboxDataTableObject
    {
        $this->checkBoxLanguage_arr = $checkBoxLanguage_arr;

        return $this;
    }

    public function addCheckBoxLanguageArr(CheckboxLanguageDataTableObject $checkBoxLanguage): CheckboxDataTableObject
    {
        $this->checkBoxLanguage_arr[] = $checkBoxLanguage;

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function getCustomerGroupArr(): array
    {
        return $this->customerGroup_arr;
    }

    /**
     * @param array<mixed> $customerGroup_arr
     */
    public function setCustomerGroupArr(array $customerGroup_arr): CheckboxDataTableObject
    {
        $this->customerGroup_arr = $customerGroup_arr;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getDisplayAtArr(): array
    {
        return $this->displayAt_arr;
    }

    /**
     * @param string[] $displayAt_arr
     * @return CheckboxDataTableObject
     */
    public function setDisplayAtArr(array $displayAt_arr): CheckboxDataTableObject
    {
        $this->displayAt_arr = $displayAt_arr;

        return $this;
    }

    public function getHasLink(): bool
    {
        return $this->nLink;
    }

    public function setHasLink(bool $nLink): CheckboxDataTableObject
    {
        $this->nLink = $this->checkAndReturnBoolValue($nLink);

        return $this;
    }
}
