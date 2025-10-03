<?php

declare(strict_types=1);

namespace JTL\Backend\Wizard;

/**
 * Class SelectOption
 * @package JTL\Backend\Wizard
 */
final class SelectOption
{
    private string $value = '';

    private string $name = '';

    private ?string $logoPath = null;

    private ?string $link = null;

    private ?string $description = null;

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getLogoPath(): ?string
    {
        return $this->logoPath;
    }

    public function setLogoPath(string $logoPath): void
    {
        $this->logoPath = $logoPath;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(string $link): void
    {
        $this->link = $link;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function isSelected(mixed $val): bool
    {
        return $val === $this->getValue() || (\is_array($val) && \in_array($this->getValue(), $val, true));
    }
}
