<?php

declare(strict_types=1);

namespace JTL\Checkbox\CheckboxFunction;

use JTL\DataObjects\AbstractDataObject;
use JTL\DataObjects\DataTableObjectInterface;

/**
 * Class CheckboxFunctionDataTableObject
 * @package JTL\Checkbox\CheckboxFunction
 */
class CheckboxFunctionDataTableObject extends AbstractDataObject implements DataTableObjectInterface
{
    private string $primaryKey = 'kCheckBoxFunktion';

    protected ?int $pluginID = null;

    protected int $checkboxFunctionID = 0;

    protected string $name = '';

    protected string $identifier = '';

    /**
     * @var array<string, string>
     */
    private static array $mapping = [
        'checkboxFunctionID' => 'checkboxFunctionID',
        'pluginID'           => 'pluginID',
        'name'               => 'name',
        'identifier'         => 'identifier',
    ];

    /**
     * @var array<string, string>
     */
    private static array $columnMapping = [
        'kCheckBoxFunktion' => 'checkboxFunctionID',
        'kPlugin'           => 'pluginID',
        'cName'             => 'name',
        'cID'               => 'identifier',
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

    public function getPluginID(): ?int
    {
        return $this->pluginID;
    }

    public function setPluginID(null|int|string $pluginID): CheckboxFunctionDataTableObject
    {
        $this->pluginID = (int)$pluginID;

        return $this;
    }

    public function getCheckboxFunctionID(): int
    {
        return $this->checkboxFunctionID;
    }

    public function setCheckboxFunctionID(null|int|string $checkboxFunctionID): CheckboxFunctionDataTableObject
    {
        $this->checkboxFunctionID = (int)$checkboxFunctionID;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): CheckboxFunctionDataTableObject
    {
        $this->name = $name;

        return $this;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): CheckboxFunctionDataTableObject
    {
        $this->identifier = $identifier;

        return $this;
    }
}
