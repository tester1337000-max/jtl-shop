<?php

declare(strict_types=1);

namespace JTL\Link;

use JTL\Language\LanguageHelper;
use JTL\MagicCompatibilityTrait;

/**
 * Class AbstractLink
 * @package JTL\Link
 */
abstract class AbstractLink implements LinkInterface
{
    use MagicCompatibilityTrait;

    /**
     * @var array<string, string>
     */
    protected static array $mapping = [
        'cNoFollow'          => 'NoFollowCompat',
        'cURL'               => 'URL',
        'cURLFull'           => 'URL',
        'cURLFullSSL'        => 'URL',
        'cLocalizedName'     => 'NamesCompat',
        'cLocalizedTitle'    => 'Title',
        'kLink'              => 'ID',
        'kSprache'           => 'LanguageID',
        'cName'              => 'Name',
        'kPlugin'            => 'PluginID',
        'kVaterLink'         => 'Parent',
        'kLinkgruppe'        => 'LinkGroupID',
        'cKundengruppen'     => 'CustomerGroupsCompat',
        'cSichtbarNachLogin' => 'VisibleLoggedInOnlyCompat',
        'nSort'              => 'Sort',
        'bSSL'               => 'SSL',
        'bIsFluid'           => 'IsFluid',
        'cIdentifier'        => 'Identifier',
        'bIsActive'          => 'IsActive',
        'aktiv'              => 'IsActive',
        'cISO'               => 'LanguageCode',
        'cLocalizedSeo'      => 'URL',
        'cSeo'               => 'URL',
        'nHTTPRedirectCode'  => 'RedirectCode',
        'nPluginStatus'      => 'PluginEnabled',
        'Sprache'            => 'LangCompat',
        'cContent'           => 'Content',
        'cTitle'             => 'Title',
        'cMetaTitle'         => 'MetaTitle',
        'cMetaKeywords'      => 'MetaKeyword',
        'cMetaDescription'   => 'MetaDescription',
        'cDruckButton'       => 'PrintButtonCompat',
        'nLinkart'           => 'LinkType',
        'level'              => 'Level',
    ];

    /**
     * @param mixed|string $ssk
     * @return int[]
     */
    protected static function parseSSKAdvanced(mixed $ssk): array
    {
        return \is_string($ssk) && \mb_convert_case($ssk, \MB_CASE_LOWER) !== 'null'
            ? \array_map('\intval', \array_map('\trim', \array_filter(\explode(';', $ssk))))
            : [];
    }

    public function getLangCompat(): LinkInterface
    {
        return $this;
    }

    public function getCustomerGroupsCompat(): ?string
    {
        $groups = $this->getCustomerGroups();

        return \count($groups) > 0
            ? \implode(';', $groups) . ';'
            : null;
    }

    /**
     * @param numeric-string[]|string $value
     */
    public function setCustomerGroupsCompat(array|string $value): void
    {
        $this->setCustomerGroups(\is_array($value) ? \array_map('\intval', $value) : self::parseSSKAdvanced($value));
    }

    public function getPrintButtonCompat(): string
    {
        return $this->hasPrintButton() === true ? 'Y' : 'N';
    }

    public function setPrintButtonCompat(bool|string $value): void
    {
        $this->setPrintButton($value === 'Y' || $value === true);
    }

    /**
     * @return 'Y'|'N'
     */
    public function getNoFollowCompat(): string
    {
        return $this->getNoFollow() === true ? 'Y' : 'N';
    }

    public function setNoFollowCompat(bool|string $value): void
    {
        $this->setNoFollow($value === 'Y' || $value === true);
    }

    /**
     * @return 'Y'|'N'
     */
    public function getVisibleLoggedInOnlyCompat(): string
    {
        return $this->getVisibleLoggedInOnly() === true ? 'Y' : 'N';
    }

    public function setVisibleLoggedInOnlyCompat(bool|string $value): void
    {
        $this->setVisibleLoggedInOnly($value === 'Y' || $value === true);
    }

    /**
     * @return array<string, string>
     */
    public function getNamesCompat(): array
    {
        $byCode    = [];
        $languages = LanguageHelper::getAllLanguages(1);
        foreach ($this->getNames() as $langID => $name) {
            $byCode[$languages[$langID]->getCode()] = $name;
        }

        return $byCode;
    }
}
