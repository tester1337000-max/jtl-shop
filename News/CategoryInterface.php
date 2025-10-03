<?php

declare(strict_types=1);

namespace JTL\News;

use DateTime;
use Illuminate\Support\Collection;

/**
 * Interface CategoryInterface
 * @package JTL\News
 */
interface CategoryInterface
{
    /**
     * @return Collection<int, CategoryInterface>
     */
    public function getItems(): Collection;

    /**
     * @param Collection<int, CategoryInterface> $items
     */
    public function setItems(Collection $items): void;

    /**
     * @param int|null $idx
     * @return string
     */
    public function getName(?int $idx = null): string;

    /**
     * @return string[]
     */
    public function getNames(): array;

    /**
     * @param string   $name
     * @param int|null $idx
     */
    public function setName(string $name, ?int $idx = null): void;

    /**
     * @param string[] $names
     */
    public function setNames(array $names): void;

    /**
     * @return string[]
     */
    public function getMetaTitles(): array;

    /**
     * @param int|null $idx
     * @return string
     */
    public function getMetaTitle(?int $idx = null): string;

    /**
     * @param string   $metaTitle
     * @param int|null $idx
     */
    public function setMetaTitle(string $metaTitle, ?int $idx = null): void;

    /**
     * @param string[] $metaTitles
     */
    public function setMetaTitles(array $metaTitles): void;

    /**
     * @param int|null $idx
     * @return string
     */
    public function getMetaKeyword(?int $idx = null): string;

    /**
     * @return string[]
     */
    public function getMetaKeywords(): array;

    /**
     * @param string   $metaKeyword
     * @param int|null $idx
     */
    public function setMetaKeyword(string $metaKeyword, ?int $idx = null): void;

    /**
     * @param string[] $metaKeywords
     */
    public function setMetaKeywords(array $metaKeywords): void;

    /**
     * @param int|null $idx
     * @return string
     */
    public function getMetaDescription(?int $idx = null): string;

    /**
     * @return string[]
     */
    public function getMetaDescriptions(): array;

    /**
     * @param string   $metaDescription
     * @param int|null $idx
     */
    public function setMetaDescription(string $metaDescription, ?int $idx = null): void;

    /**
     * @param string[] $metaDescriptions
     */
    public function setMetaDescriptions(array $metaDescriptions): void;

    /**
     * @param int|null $idx
     * @return string
     */
    public function getURL(?int $idx = null): string;

    /**
     * @return array<int, string>
     */
    public function getURLs(): array;

    /**
     * @param int|null $idx
     * @return string
     */
    public function getSEO(?int $idx = null): string;

    /**
     * @return array<int, string>
     */
    public function getSEOs(): array;

    /**
     * @param array<int, string> $seos
     */
    public function setSEOs(array $seos): void;

    /**
     * @param string   $seo
     * @param int|null $idx
     */
    public function setSEO(string $seo, ?int $idx = null): void;

    /**
     * @return int
     */
    public function getID(): int;

    /**
     * @param int $id
     */
    public function setID(int $id): void;

    /**
     * @return int
     */
    public function getParentID(): int;

    /**
     * @param int $parentID
     */
    public function setParentID(int $parentID): void;

    /**
     * @param int|null $idx
     * @return int
     */
    public function getLanguageID(?int $idx = null): int;

    /**
     * @return int[]
     */
    public function getLanguageIDs(): array;

    /**
     * @param int[] $languageIDs
     */
    public function setLanguageIDs(array $languageIDs): void;

    /**
     * @param int|null $idx
     * @return string
     */
    public function getLanguageCode(?int $idx = null): string;

    /**
     * @return string[]
     */
    public function getLanguageCodes(): array;

    /**
     * @param string[] $languageCodes
     */
    public function setLanguageCodes(array $languageCodes): void;

    /**
     * @param int|null $idx
     * @return string
     */
    public function getDescription(?int $idx = null): string;

    /**
     * @return string[]
     */
    public function getDescriptions(): array;

    /**
     * @param string   $description
     * @param int|null $idx
     */
    public function setDescription(string $description, ?int $idx = null): void;

    /**
     * @param string[] $descriptions
     */
    public function setDescriptions(array $descriptions): void;

    /**
     * @param int|null $idx
     * @return string
     */
    public function getPreviewImage(?int $idx = null): string;

    /**
     * @return string[]
     */
    public function getPreviewImages(): array;

    /**
     * @param string   $image
     * @param int|null $idx
     */
    public function setPreviewImage(string $image, ?int $idx = null): void;

    /**
     * @param string[] $previewImages
     */
    public function setPreviewImages(array $previewImages): void;

    /**
     * @return int
     */
    public function getSort(): int;

    /**
     * @param int $sort
     */
    public function setSort(int $sort): void;

    /**
     * @return bool
     */
    public function getIsActive(): bool;

    /**
     * @return bool
     */
    public function isActive(): bool;

    /**
     * @param bool $isActive
     */
    public function setIsActive(bool $isActive): void;

    /**
     * @return DateTime
     */
    public function getDateLastModified(): DateTime;

    /**
     * @param DateTime $dateLastModified
     */
    public function setDateLastModified(DateTime $dateLastModified): void;

    /**
     * @return int
     */
    public function getLevel(): int;

    /**
     * @param int $level
     */
    public function setLevel(int $level): void;

    /**
     * @return Collection<int, Category>
     */
    public function getChildren(): Collection;

    /**
     * @param Category $child
     */
    public function addChild(Category $child): void;

    /**
     * @param Collection<int, Category> $children
     */
    public function setChildren(Collection $children): void;

    /**
     * @return int
     */
    public function getLft(): int;

    /**
     * @param int $lft
     */
    public function setLft(int $lft): void;

    /**
     * @return int
     */
    public function getRght(): int;

    /**
     * @param int $rght
     */
    public function setRght(int $rght): void;

    /**
     * @param int  $id
     * @param bool $activeOnly
     * @return self
     */
    public function load(int $id, bool $activeOnly = true): self;
}
