<?php

declare(strict_types=1);

namespace JTL\Checkbox\CheckboxFunction;

use JTL\DataObjects\AbstractDomainObject;

/**
 * Class CheckboxFunctionDomainObject
 * @package JTL\Checkbox\CheckboxFunction
 */
class CheckboxFunctionDomainObject extends AbstractDomainObject
{
    private string $primaryKey;

    public function __construct(
        protected readonly ?int $pluginID = null,
        protected readonly ?int $checkboxFunctionID = 0,
        protected readonly string $name = '',
        protected readonly string $identifier = '',
        array $modifiedKeys = []
    ) {
        $this->primaryKey = 'checkboxFunctionID';

        parent::__construct($modifiedKeys);
    }

    /**
     * @return array<string, string>
     */
    private function getMappingArray(): array
    {
        return [
            'checkboxFunctionID' => 'checkboxFunctionID',
            'pluginID'           => 'pluginID',
            'name'               => 'name',
            'identifier'         => 'identifier',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getColumnMappingArray(): array
    {
        return [
            'kCheckBoxFunktion' => 'checkboxFunctionID',
            'kPlugin'           => 'pluginID',
            'cName'             => 'name',
            'cID'               => 'identifier',
            'modifiedKeys'      => 'modifiedKeys',
        ];
    }

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
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
     * @return array<string, string>
     */
    public function getColumnMapping(): array
    {
        return \array_flip($this->getColumnMappingArray());
    }

    public function getID(): int
    {
        return (int)$this->{$this->getPrimaryKey()};
    }

    public function getPluginID(): ?int
    {
        return $this->pluginID;
    }

    public function getCheckboxFunctionID(): int
    {
        return $this->checkboxFunctionID;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
