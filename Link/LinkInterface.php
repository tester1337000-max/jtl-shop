<?php

declare(strict_types=1);

namespace JTL\Link;

use Illuminate\Support\Collection;

/**
 * Interface LinkInterface
 * @package JTL\Link
 */
interface LinkInterface
{
    /**
     * @param int $id
     * @return LinkInterface
     * @throws \InvalidArgumentException
     */
    public function load(int $id): LinkInterface;

    /**
     * @param \stdClass[] $localizedLinks
     * @return $this
     */
    public function map(array $localizedLinks): LinkInterface;

    /**
     * @param int $customerGroupID
     * @param int $customerID
     * @return bool
     */
    public function checkVisibility(int $customerGroupID, int $customerID = 0): bool;

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
    public function getParent(): int;

    /**
     * @param int $parent
     */
    public function setParent(int $parent): void;

    /**
     * @return int[]
     */
    public function getLinkGroups(): array;

    /**
     * @param int[] $linkGroups
     */
    public function setLinkGroups(array $linkGroups): void;

    /**
     * @return int
     */
    public function getLinkGroupID(): int;

    /**
     * @param int $linkGroupID
     */
    public function setLinkGroupID(int $linkGroupID): void;

    /**
     * @return int
     */
    public function getPluginID(): int;

    /**
     * @param int $pluginID
     */
    public function setPluginID(int $pluginID): void;

    /**
     * @return int
     */
    public function getLinkType(): int;

    /**
     * @param int $linkType
     */
    public function setLinkType(int $linkType): void;

    /**
     * @param int|null $idx
     * @return string
     */
    public function getName(?int $idx = null): string;

    /**
     * @return array<int, string>
     */
    public function getNames(): array;

    /**
     * @param string   $name
     * @param int|null $idx
     */
    public function setName(string $name, ?int $idx = null): void;

    /**
     * @param array<int, string> $names
     */
    public function setNames(array $names): void;

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
     * @return array<int, string>
     */
    public function getURLs(): array;

    /**
     * @param string   $url
     * @param int|null $idx
     */
    public function setURL(string $url, ?int $idx = null): void;

    /**
     * @param array<int, string> $urls
     */
    public function setURLs(array $urls): void;

    /**
     * @param int|null $idx
     * @return string
     */
    public function getTitle(?int $idx = null): string;

    /**
     * @return array<int, string>
     */
    public function getTitles(): array;

    /**
     * @param string   $title
     * @param int|null $idx
     */
    public function setTitle(string $title, ?int $idx = null): void;

    /**
     * @param array<int, string> $title
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
     * @return array<int, string>
     */
    public function getLanguageCodes(): array;

    /**
     * @param string   $languageCode
     * @param int|null $idx
     */
    public function setLanguageCode(string $languageCode, ?int $idx = null): void;

    /**
     * @param array<int, string> $languageCodes
     */
    public function setLanguageCodes(array $languageCodes): void;

    /**
     * @return int
     */
    public function getReference(): int;

    /**
     * @param int $reference
     */
    public function setReference(int $reference): void;

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
    public function getSSL(): bool;

    /**
     * @param bool $ssl
     */
    public function setSSL(bool $ssl): void;

    /**
     * @return bool
     */
    public function getNoFollow(): bool;

    /**
     * @param bool $noFollow
     */
    public function setNoFollow(bool $noFollow): void;

    /**
     * @return bool
     */
    public function hasPrintButton(): bool;

    /**
     * @param bool $printButton
     */
    public function setPrintButton(bool $printButton): void;

    /**
     * @return bool
     */
    public function getIsActive(): bool;

    /**
     * @param bool $isActive
     */
    public function setIsActive(bool $isActive): void;

    /**
     * @return bool
     */
    public function getIsEnabled(): bool;

    /**
     * @param bool $enabled
     */
    public function setIsEnabled(bool $enabled): void;

    /**
     * @return bool
     */
    public function getIsFluid(): bool;

    /**
     * @param bool $isFluid
     */
    public function setIsFluid(bool $isFluid): void;

    /**
     * @param int|null $idx
     * @return int
     */
    public function getLanguageID(?int $idx = null): int;

    /**
     * @param int $languageID
     */
    public function setLanguageID(int $languageID): void;

    /**
     * @return int
     */
    public function getRedirectCode(): int;

    /**
     * @param int $redirectCode
     */
    public function setRedirectCode(int $redirectCode): void;

    /**
     * @return bool
     */
    public function getVisibleLoggedInOnly(): bool;

    /**
     * @param bool $visibleLoggedInOnly
     */
    public function setVisibleLoggedInOnly(bool $visibleLoggedInOnly): void;

    /**
     * @return string
     */
    public function getIdentifier(): string;

    /**
     * @param string $identifier
     */
    public function setIdentifier(string $identifier): void;

    /**
     * @return bool
     */
    public function getPluginEnabled(): bool;

    /**
     * @param bool $pluginEnabled
     */
    public function setPluginEnabled(bool $pluginEnabled): void;

    /**
     * @return Collection<int, LinkInterface>
     */
    public function getChildLinks(): Collection;

    /**
     * @param LinkInterface[]|Collection<int, LinkInterface> $links
     */
    public function setChildLinks(array|Collection $links): void;

    /**
     * @param LinkInterface $link
     */
    public function addChildLink(LinkInterface $link): void;

    /**
     * @return string
     */
    public function getFileName(): string;

    /**
     * @param string $fileName
     */
    public function setFileName(string $fileName): void;

    /**
     * @return string
     */
    public function getContent(): string;

    /**
     * @return array<int, string>
     */
    public function getContents(): array;

    /**
     * @param string   $content
     * @param int|null $idx
     */
    public function setContent(string $content, ?int $idx = null): void;

    /**
     * @param array<int, string> $contents
     */
    public function setContents(array $contents): void;

    /**
     * @return string
     */
    public function getMetaTitle(): string;

    /**
     * @return array<int, string>
     */
    public function getMetaTitles(): array;

    /**
     * @param string   $metaTitle
     * @param int|null $idx
     */
    public function setMetaTitle(string $metaTitle, ?int $idx = null): void;

    /**
     * @param array<int, string> $metaTitles
     */
    public function setMetaTitles(array $metaTitles): void;

    /**
     * @return string
     */
    public function getMetaKeyword(): string;

    /**
     * @return array<int, string>
     */
    public function getMetaKeywords(): array;

    /**
     * @param string   $metaKeyword
     * @param int|null $idx
     */
    public function setMetaKeyword(string $metaKeyword, ?int $idx = null): void;

    /**
     * @param array<int, string> $metaKeywords
     */
    public function setMetaKeywords(array $metaKeywords): void;

    /**
     * @return string
     */
    public function getMetaDescription(): string;

    /**
     * @return array<int, string>
     */
    public function getMetaDescriptions(): array;

    /**
     * @param string   $metaDescription
     * @param int|null $idx
     */
    public function setMetaDescription(string $metaDescription, ?int $idx = null): void;

    /**
     * @param array<int, string> $metaDescriptions
     */
    public function setMetaDescriptions(array $metaDescriptions): void;

    /**
     * @return bool
     */
    public function isVisible(): bool;

    /**
     * @param bool $isVisible
     */
    public function setVisibility(bool $isVisible): void;

    /**
     * @return int
     */
    public function getLevel(): int;

    /**
     * @param int $level
     */
    public function setLevel(int $level): void;

    /**
     * @return string
     */
    public function getDisplayName(): string;

    /**
     * @param string $displayName
     */
    public function setDisplayName(string $displayName): void;

    /**
     * @return string
     */
    public function getHandler(): string;

    /**
     * @param string $handler
     */
    public function setHandler(string $handler): void;

    /**
     * @return string
     */
    public function getTemplate(): string;

    /**
     * @param string $template
     */
    public function setTemplate(string $template): void;

    /**
     * @return LinkInterface[]
     */
    public function buildChildLinks(): array;

    /**
     * @return bool
     */
    public function hasDuplicateSpecialLink(): bool;
}
