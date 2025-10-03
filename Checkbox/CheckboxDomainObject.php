<?php

declare(strict_types=1);

namespace JTL\Checkbox;

use JTL\Checkbox\CheckboxFunction\CheckboxFunctionDomainObject;
use JTL\DataObjects\AbstractDomainObject;
use JTL\DataObjects\DataTableObjectInterface;

/**
 * Class CheckboxDomainObject
 * @package JTL\Checkbox
 */
class CheckboxDomainObject extends AbstractDomainObject implements DataTableObjectInterface
{
    private string $primaryKey;

    /**
     * @param array<mixed> $languages
     * @param array<mixed> $checkBoxLanguage_arr
     * @param array<mixed> $customerGroup_arr
     * @param array<mixed> $displayAt_arr
     * @param array<mixed> $modifiedKeys
     */
    public function __construct(
        protected readonly int $checkboxID = 0,
        protected readonly int $linkID = 0,
        protected readonly int $checkboxFunctionID = 0,
        protected readonly string $name = '',
        protected readonly string $customerGroupsSelected = '',
        protected readonly string $displayAt = '',
        protected readonly bool $active = true,
        protected readonly bool $isMandatory = false,
        protected readonly bool $hasLogging = true,
        protected readonly int $sort = 0,
        protected readonly string $created = '',
        protected readonly bool $internal = false,
        private readonly string $created_DE = '',
        private readonly array $languages = [],
        private readonly bool $nLink = false,
        private readonly array $checkBoxLanguage_arr = [],
        private readonly array $customerGroup_arr = [],
        private readonly array $displayAt_arr = [],
        private readonly ?CheckboxFunctionDomainObject $oCheckBoxFunction = null,
        array $modifiedKeys = []
    ) {
        $this->primaryKey = 'checkboxID';

        parent::__construct($modifiedKeys);
    }

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    /**
     * @return array<string, string>
     */
    private function getMappingArray(): array
    {
        return [
            'checkboxID'             => 'checkboxID',
            'linkID'                 => 'linkID',
            'checkboxFunctionID'     => 'checkboxFunctionID',
            'name'                   => 'name',
            'customerGroupsSelected' => 'customerGroupsSelected',
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
    }

    /**
     * @return array<string, string>
     */
    private function getColumnMappingArray(): array
    {
        return [
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
            'modifiedKeys'         => 'modifiedKeys',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getMapping(): array
    {
        return \array_merge($this->getMappingArray(), $this->getColumnMappingArray());
    }

    /**
     * @return array<string, string>
     */
    public function getReverseMapping(): array
    {
        return \array_flip($this->getMappingArray());
    }

    /**
     * @inheritdoc
     */
    public function getColumnMapping(): array
    {
        return \array_flip($this->getColumnMappingArray());
    }

    public function getID(): int
    {
        return $this->{$this->getPrimaryKey()};
    }

    public function getCheckboxID(): int
    {
        return $this->checkboxID;
    }

    public function getLinkID(): int
    {
        return $this->linkID;
    }

    public function getCheckboxFunctionID(): int
    {
        return $this->checkboxFunctionID;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCustomerGroupsSelected(): string
    {
        return $this->customerGroupsSelected;
    }

    public function getDisplayAt(): string
    {
        return $this->displayAt;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isMandatory(): bool
    {
        return $this->isMandatory;
    }

    public function isLogging(): bool
    {
        return $this->hasLogging;
    }

    public function getSort(): int
    {
        return $this->sort;
    }

    public function getCreated(): string
    {
        return $this->created;
    }

    public function getInternal(): bool
    {
        return $this->internal;
    }

    public function getCreatedDE(): string
    {
        return $this->created_DE;
    }

    /**
     * @return array<mixed>
     */
    public function getLanguages(): array
    {
        return $this->languages;
    }

    /**
     * @return array<mixed>
     */
    public function getCheckBoxLanguageArr(): array
    {
        return $this->checkBoxLanguage_arr;
    }

    /**
     * @return array<mixed>
     */
    public function getCustomerGroupArr(): array
    {
        return $this->customerGroup_arr;
    }

    /**
     * @return array<mixed>
     */
    public function getDisplayAtArr(): array
    {
        return $this->displayAt_arr;
    }

    public function getHasLink(): bool
    {
        return $this->nLink;
    }

    public function getCheckBoxFunction(): ?CheckboxFunctionDomainObject
    {
        return $this->oCheckBoxFunction;
    }
}
