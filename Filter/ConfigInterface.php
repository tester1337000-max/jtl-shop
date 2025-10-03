<?php

declare(strict_types=1);

namespace JTL\Filter;

use JTL\Language\LanguageModel;

/**
 * Interface ConfigInterface
 * @package JTL\Filter
 */
interface ConfigInterface
{
    /**
     * @return ConfigInterface
     */
    public static function getDefault(): self;

    /**
     * @return int
     */
    public function getLanguageID(): int;

    /**
     * @param int $langID
     */
    public function setLanguageID(int $langID): void;

    /**
     * @return LanguageModel[]
     */
    public function getLanguages(): array;

    /**
     * @param LanguageModel[] $languages
     */
    public function setLanguages(array $languages): void;

    /**
     * @param string|null $section
     * @return mixed
     */
    public function getConfig(?string $section = null): mixed;

    /**
     * @param array<mixed> $config
     */
    public function setConfig(array $config): void;

    /**
     * @return int
     */
    public function getCustomerGroupID(): int;

    /**
     * @param int $customerGroupID
     */
    public function setCustomerGroupID(int $customerGroupID): void;

    /**
     * @return string
     */
    public function getBaseURL(): string;

    /**
     * @param string $baseURL
     */
    public function setBaseURL(string $baseURL);
}
