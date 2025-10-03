<?php

declare(strict_types=1);

namespace JTL\Backend\Wizard;

use Exception;
use stdClass;

/**
 * Interface QuestionInterface
 * @package JTL\Backend\Wizard
 */
interface QuestionInterface
{
    /**
     * @param array<mixed> $post
     * @return mixed
     */
    public function answerFromPost(array $post): mixed;

    /**
     * @param array<mixed> $data
     */
    public function loadAnswer(array $data): void;

    /**
     * @param string $configName
     * @param mixed  $value
     * @return int
     */
    public function updateConfig(string $configName, mixed $value): int;

    /**
     * Add or update a row in tsprachwerte
     *
     * @param string $locale locale iso code e.g. "ger"
     * @param string $section section e.g. "global". See tsprachsektion for all sections
     * @param string $key unique name to identify localization
     * @param string $value localized text
     * @param bool   $system optional flag for system-default.
     * @throws Exception if locale key or section is wrong
     */
    public function setLocalization(
        string $locale,
        string $section,
        string $key,
        string $value,
        bool $system = true
    ): void;

    /**
     * @return int
     */
    public function save(): int;

    /**
     * @return int
     */
    public function getID(): int;

    /**
     * @param int $id
     */
    public function setID(int $id): void;

    /**
     * @return null|string
     */
    public function getText(): ?string;

    /**
     * @param string $text
     */
    public function setText(string $text): void;

    /**
     * @return null|string
     */
    public function getDescription(): ?string;

    /**
     * @param string $description
     */
    public function setDescription(string $description): void;

    /**
     * @return null|string
     */
    public function getSubheading(): ?string;

    /**
     * @param string $subheading
     */
    public function setSubheading(string $subheading): void;

    /**
     * @return null|string
     */
    public function getSubheadingDescription(): ?string;

    /**
     * @param string $subheadingDescription
     */
    public function setSubheadingDescription(string $subheadingDescription): void;

    /**
     * @return null|string
     */
    public function getSummaryText(): ?string;

    /**
     * @param string $summaryText
     */
    public function setSummaryText(string $summaryText): void;

    /**
     * @return null|string
     */
    public function getLabel(): ?string;

    /**
     * @param string $label
     */
    public function setLabel(string $label): void;

    /**
     * @return int
     */
    public function getType(): int;

    /**
     * @param int $type
     */
    public function setType(int $type): void;

    /**
     * @return mixed
     */
    public function getValue(): mixed;

    /**
     * @param mixed $value
     * @param bool  $sessionFirst
     */
    public function setValue(mixed $value, bool $sessionFirst = true): void;

    /**
     * @return int|null
     */
    public function getDependency(): ?int;

    /**
     * @param int $dependency
     */
    public function setDependency(int $dependency): void;

    /**
     * @return callable|null
     */
    public function getOnSave(): ?callable;

    /**
     * @param callable $onSave
     */
    public function setOnSave(callable $onSave): void;

    /**
     * @return SelectOption[]
     */
    public function getOptions(): array;

    /**
     * @param SelectOption[] $options
     */
    public function setOptions(array $options): void;

    /**
     * @param SelectOption $option
     */
    public function addOption(SelectOption $option): void;

    /**
     * @return bool
     */
    public function isMultiSelect(): bool;

    /**
     * @param bool $multi
     */
    public function setIsMultiSelect(bool $multi): void;

    /**
     * @return bool
     */
    public function isRequired(): bool;

    /**
     * @param bool $required
     */
    public function setIsRequired(bool $required): void;

    /**
     * @return bool
     */
    public function isFullWidth(): bool;

    /**
     * @param bool $fullWidth
     */
    public function setIsFullWidth(bool $fullWidth): void;

    /**
     * @return int
     */
    public function validate(): int;

    /**
     * @param callable|null $validation
     */
    public function setValidation(?callable $validation): void;

    /**
     * @return callable
     */
    public function getValidation(): callable;

    /**
     * @return stdClass
     */
    public function jsonSerialize(): stdClass;
}
