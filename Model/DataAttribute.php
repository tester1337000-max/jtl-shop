<?php

declare(strict_types=1);

namespace JTL\Model;

/**
 * Class DataAttribute
 * @package JTL\Model
 */
class DataAttribute
{
    public string $name;

    public string $dataType;

    public bool $nullable = true;

    public mixed $default = null;

    public bool $isPrimaryKey = false;

    public ?string $foreignKey = null;

    public ?string $foreignKeyChild = null;

    public bool $dynamic = false;

    public InputConfig $inputConfig;

    public function __construct()
    {
        $this->inputConfig = new InputConfig();
    }

    public function getInputConfig(): InputConfig
    {
        return $this->inputConfig;
    }

    public function setInputConfig(InputConfig $inputConfig): DataAttribute
    {
        $this->inputConfig = $inputConfig;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): DataAttribute
    {
        $this->name = $name;

        return $this;
    }

    public function getDataType(): string
    {
        return $this->dataType;
    }

    public function setDataType(string $dataType): DataAttribute
    {
        $this->dataType = $dataType;

        return $this;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function setNullable(bool $nullable): DataAttribute
    {
        $this->nullable = $nullable;

        return $this;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function setDefault(mixed $default): DataAttribute
    {
        $this->default = $default;

        return $this;
    }

    public function isPrimaryKey(): bool
    {
        return $this->isPrimaryKey;
    }

    public function setIsPrimaryKey(bool $isPrimaryKey): DataAttribute
    {
        $this->isPrimaryKey = $isPrimaryKey;

        return $this;
    }

    public function getForeignKey(): ?string
    {
        return $this->foreignKey;
    }

    public function setForeignKey(?string $foreignKey): DataAttribute
    {
        $this->foreignKey = $foreignKey;

        return $this;
    }

    public function getForeignKeyChild(): ?string
    {
        return $this->foreignKeyChild;
    }

    public function setForeignKeyChild(?string $foreignKeyChild): DataAttribute
    {
        $this->foreignKeyChild = $foreignKeyChild;

        return $this;
    }

    public function isDynamic(): bool
    {
        return $this->dynamic;
    }

    public function setDynamic(bool $dynamic): DataAttribute
    {
        $this->dynamic = $dynamic;

        return $this;
    }

    /**
     * Creates a new DataAttribute instance
     *
     * @param string      $name - name of the attribute
     * @param string      $dataType - type of the attribute
     * @param null|mixed  $default - default value of the attribute
     * @param bool        $nullable - true if the attribute is nullable, false otherwise
     * @param bool        $isPrimaryKey - true if the attribute is the primary key, false otherwise
     * @param string|null $foreignKey
     * @param string|null $foreignKeyChild
     * @param bool        $dynamic
     * @return self
     */
    public static function create(
        string $name,
        string $dataType,
        mixed $default = null,
        bool $nullable = true,
        bool $isPrimaryKey = false,
        ?string $foreignKey = null,
        ?string $foreignKeyChild = null,
        bool $dynamic = false
    ): self {
        $item = new self();
        $item->setName($name)
            ->setDataType($dataType)
            ->setDefault($default)
            ->setNullable($nullable)
            ->setIsPrimaryKey($isPrimaryKey)
            ->setForeignKey($foreignKey)
            ->setForeignKeyChild($foreignKeyChild)
            ->setDynamic($dynamic);

        return $item;
    }
}
