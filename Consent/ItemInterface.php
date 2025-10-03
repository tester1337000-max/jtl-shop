<?php

declare(strict_types=1);

namespace JTL\Consent;

/**
 * Interface ItemInterface
 * @package JTL\Consent
 */
interface ItemInterface
{
    /**
     * @param int|null $idx
     * @return string
     */
    public function getName(?int $idx = null): string;

    /**
     * @param string   $name
     * @param int|null $idx
     */
    public function setName(string $name, ?int $idx = null): void;

    /**
     * @return int
     */
    public function getID(): int;

    /**
     * @param int $id
     */
    public function setID(int $id): void;

    /**
     * @param int|null $idx
     * @return string
     */
    public function getDescription(?int $idx = null): string;

    /**
     * @param string   $description
     * @param int|null $idx
     */
    public function setDescription(string $description, ?int $idx = null): void;

    /**
     * @param int|null $idx
     * @return string
     */
    public function getPurpose(?int $idx = null): string;

    /**
     * @param string   $purpose
     * @param int|null $idx
     */
    public function setPurpose(string $purpose, ?int $idx = null): void;

    /**
     * @param int|null $idx
     * @return string
     */
    public function getCompany(?int $idx = null): string;

    /**
     * @param string   $company
     * @param int|null $idx
     */
    public function setCompany(string $company, ?int $idx = null): void;

    /**
     * @param int|null $idx
     * @return string
     */
    public function getPrivacyPolicy(?int $idx = null): string;

    /**
     * @param string   $tos
     * @param int|null $idx
     */
    public function setPrivacyPolicy(string $tos, ?int $idx = null): void;

    /**
     * @return bool
     */
    public function hasMoreInfo(): bool;

    /**
     * @return int
     */
    public function getCurrentLanguageID(): int;

    /**
     * @param int $currentLanguageID
     */
    public function setCurrentLanguageID(int $currentLanguageID): void;

    /**
     * @return string
     */
    public function getItemID(): string;

    /**
     * @param string $itemID
     */
    public function setItemID(string $itemID): void;

    /**
     * @return int
     */
    public function getPluginID(): int;

    /**
     * @param int $pluginID
     */
    public function setPluginID(int $pluginID): void;

    /**
     * @return bool
     */
    public function isActive(): bool;

    /**
     * @param bool $active
     */
    public function setActive(bool $active): void;
}
