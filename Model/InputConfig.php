<?php

declare(strict_types=1);

namespace JTL\Model;

use JTL\Plugin\Admin\InputType;

/**
 * Class InputConfig
 * @package JTL\Model
 */
class InputConfig
{
    /**
     * @var array<int|string, string>
     */
    public array $allowedValues = [];

    /**
     * @phpstan-var InputType::*
     */
    public string $inputType = InputType::TEXT;

    public bool $modifyable = true;

    public bool $hidden = false;

    public bool $multiselect = false;

    public bool $required = false;

    /**
     * @return array<int|string, string>
     */
    public function getAllowedValues(): array
    {
        return $this->allowedValues;
    }

    /**
     * @param array<int|string, string> $allowedValues
     */
    public function setAllowedValues(array $allowedValues): void
    {
        $this->allowedValues = $allowedValues;
    }

    /**
     * @return InputType::*
     */
    public function getInputType(): string
    {
        return $this->inputType;
    }

    /**
     * @phpstan-param InputType::* $inputType
     */
    public function setInputType(string $inputType): void
    {
        $this->inputType = $inputType;
    }

    public function isModifyable(): bool
    {
        return $this->modifyable;
    }

    public function setModifyable(bool $modifyable): void
    {
        $this->modifyable = $modifyable;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): void
    {
        $this->hidden = $hidden;
    }

    public function isMultiselect(): bool
    {
        return $this->multiselect;
    }

    public function setMultiselect(bool $multiselect): void
    {
        $this->multiselect = $multiselect;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): void
    {
        $this->required = $required;
    }
}
