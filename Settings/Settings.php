<?php

declare(strict_types=1);

namespace JTL\Settings;

use JTL\Settings\Option\OptionInterface;
use JTL\Shopsetting;

readonly class Settings
{
    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(private array $settings)
    {
    }

    public static function rawValue(OptionInterface $option): mixed
    {
        return self::fromSection($option->getSection())->raw($option);
    }

    public static function boolValue(OptionInterface $option): bool
    {
        return self::fromSection($option->getSection())->bool($option);
    }

    public static function intValue(OptionInterface $option): int
    {
        return self::fromSection($option->getSection())->int($option);
    }

    public static function stringValue(OptionInterface $option): string
    {
        return self::fromSection($option->getSection())->string($option);
    }

    public static function fromAll(): self
    {
        return new self(Shopsetting::getInstance()->getAll());
    }

    public static function fromSection(Section $option): self
    {
        return new self(Shopsetting::getInstance()->getSectionByName($option->value) ?? []);
    }

    public static function fromSectionID(int $sectionID): self
    {
        return new self(Shopsetting::getInstance()->getSection($sectionID) ?? []);
    }

    /**
     * @param array<mixed> $settings
     */
    public static function fromArray(array $settings): self
    {
        return new self($settings);
    }

    public function raw(OptionInterface $option): mixed
    {
        $section = $option->getSection();

        return $this->settings[$section->value][$option->getValue()]
            ?? $this->settings[$option->getValue()]
            ?? null;
    }

    public function section(Section $section): self
    {
        return new self($this->settings[$section->value] ?? []);
    }

    public function subsection(Subsection $subsection): self
    {
        return new self($this->settings[$subsection->value] ?? []);
    }

    public function string(OptionInterface $option): string
    {
        return (string)$this->raw($option);
    }

    public function int(OptionInterface $option): int
    {
        return (int)($this->raw($option));
    }

    public function bool(OptionInterface $option): bool
    {
        return \in_array($this->raw($option), ['1', 1, true, 'true', 'on', 'yes', 'Y', 'y'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->settings;
    }
}
