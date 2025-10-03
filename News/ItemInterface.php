<?php

declare(strict_types=1);

namespace JTL\News;

use DateTime;

/**
 * Interface ItemInterface
 * @package JTL\News
 */
interface ItemInterface
{
    /**
     * @param int $id
     * @return ItemInterface
     */
    public function load(int $id): ItemInterface;

    /**
     * @param \stdClass[] $localizedItems
     * @return ItemInterface
     */
    public function map(array $localizedItems): ItemInterface;

    /**
     * @param int $customerGroupID
     * @return bool
     */
    public function checkVisibility(int $customerGroupID): bool;

    /**
     * @return int
     */
    public function getID(): int;

    /**
     * @param int $id
     */
    public function setID(int $id): void;

    /**
     * @return array<int, string>
     */
    public function getSEOs(): array;

    /**
     * @param int|null $idx
     * @return string
     */
    public function getSEO(?int $idx = null): string;

    /**
     * @param array<int, string> $seo
     */
    public function setSEOs(array $seo): void;

    /**
     * @param string   $url
     * @param int|null $idx
     */
    public function setSEO(string $url, ?int $idx = null): void;

    /**
     * @param int|null $idx
     * @return string
     */
    public function getURL(?int $idx = null): string;

    /**
     * @return string[]
     */
    public function getURLs(): array;

    /**
     * @param string   $url
     * @param int|null $idx
     */
    public function setURL(string $url, ?int $idx = null): void;

    /**
     * @param string[] $urls
     */
    public function setURLs(array $urls): void;

    /**
     * @param int|null $idx
     * @return string
     */
    public function getTitle(?int $idx = null): string;

    /**
     * @param int|null $idx
     * @return string
     */
    public function getTitleUppercase(?int $idx = null): string;

    /**
     * @return string[]
     */
    public function getTitles(): array;

    /**
     * @param string   $title
     * @param int|null $idx
     */
    public function setTitle(string $title, ?int $idx = null): void;

    /**
     * @param string[] $title
     */
    public function setTitles(array $title): void;

    /**
     * @return int[]
     */
    public function getCustomerGroups(): array;

    /**
     * @param int[] $customerGroups
     */
    public function setCustomerGroups(array $customerGroups): void;

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
     * @param string   $languageCode
     * @param int|null $idx
     */
    public function setLanguageCode(string $languageCode, ?int $idx = null): void;

    /**
     * @param string[] $languageCodes
     */
    public function setLanguageCodes(array $languageCodes): void;

    /**
     * @return bool
     */
    public function getIsActive(): bool;

    /**
     * @param bool $isActive
     */
    public function setIsActive(bool $isActive): void;

    /**
     * @param int|null $idx
     * @return int
     */
    public function getLanguageID(?int $idx = null): int;

    /**
     * @param int      $languageID
     * @param int|null $idx
     */
    public function setLanguageID(int $languageID, ?int $idx = null): void;

    /**
     * @return int[]
     */
    public function getLanguageIDs(): array;

    /**
     * @param int[] $ids
     */
    public function setLanguageIDs(array $ids): void;

    /**
     * @return string[]
     */
    public function getContents(): array;

    /**
     * @param int|null $idx
     * @return string
     */
    public function getContent(?int $idx = null): string;

    /**
     * @param string   $content
     * @param int|null $idx
     */
    public function setContent(string $content, ?int $idx = null): void;

    /**
     * @param string[] $contents
     */
    public function setContents(array $contents): void;

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
    public function getPreview(?int $idx = null): string;

    /**
     * @return string[]
     */
    public function getPreviews(): array;

    /**
     * @param string[] $previews
     */
    public function setPreviews(array $previews): void;

    /**
     * @param string   $preview
     * @param int|null $idx
     */
    public function setPreview(string $preview, ?int $idx = null): void;

    /**
     * @param int|null $idx
     * @return string
     */
    public function getPreviewImage(?int $idx = null): string;

    /**
     * @return string
     */
    public function getPreviewImageBaseName(): string;

    /**
     * @return string[]
     */
    public function getPreviewImages(): array;

    /**
     * @param string[] $previewImages
     */
    public function setPreviewImages(array $previewImages): void;

    /**
     * @param string   $previewImage
     * @param int|null $idx
     */
    public function setPreviewImage(string $previewImage, ?int $idx = null): void;

    /**
     * @return DateTime
     */
    public function getDateCreated(): DateTime;

    /**
     * @param DateTime $dateCreated
     */
    public function setDateCreated(DateTime $dateCreated): void;

    /**
     * @return DateTime
     */
    public function getDateValidFrom(): DateTime;

    /**
     * @return int
     */
    public function getDateValidFromNumeric(): int;

    /**
     * @param DateTime $dateValidFrom
     */
    public function setDateValidFrom(DateTime $dateValidFrom): void;

    /**
     * @return DateTime
     */
    public function getDate(): DateTime;

    /**
     * @param DateTime $date
     */
    public function setDate(DateTime $date): void;

    /**
     * @return bool
     */
    public function isVisible(): bool;

    /**
     * @param bool $isVisible
     */
    public function setIsVisible(bool $isVisible): void;

    /**
     * @return CommentList
     */
    public function getComments(): CommentList;

    /**
     * @param CommentList $comments
     */
    public function setComments(CommentList $comments): void;

    /**
     * @return int
     */
    public function getCommentCount(): int;

    /**
     * @param int $commentCount
     */
    public function setCommentCount(int $commentCount): void;
}
