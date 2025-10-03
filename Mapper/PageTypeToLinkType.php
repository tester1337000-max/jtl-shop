<?php

declare(strict_types=1);

namespace JTL\Mapper;

/**
 * Class PageTypeToLinkType
 * @package JTL\Mapper
 */
class PageTypeToLinkType
{
    public function map(int $type): int
    {
        return match ($type) {
            \PAGE_EIGENE                 => \LINKTYP_EIGENER_CONTENT,
            \PAGE_STARTSEITE             => \LINKTYP_STARTSEITE,
            \PAGE_VERSAND                => \LINKTYP_VERSAND,
            \PAGE_LOGIN, \PAGE_MEINKONTO => \LINKTYP_LOGIN,
            \PAGE_REGISTRIERUNG          => \LINKTYP_REGISTRIEREN,
            \PAGE_WARENKORB              => \LINKTYP_WARENKORB,
            \PAGE_PASSWORTVERGESSEN      => \LINKTYP_PASSWORD_VERGESSEN,
            \PAGE_AGB                    => \LINKTYP_AGB,
            \PAGE_DATENSCHUTZ            => \LINKTYP_DATENSCHUTZ,
            \PAGE_KONTAKT                => \LINKTYP_KONTAKT,
            \PAGE_LIVESUCHE              => \LINKTYP_LIVESUCHE,
            \PAGE_HERSTELLER             => \LINKTYP_HERSTELLER,
            \PAGE_NEWSLETTER             => \LINKTYP_NEWSLETTER,
            \PAGE_NEWSLETTERARCHIV       => \LINKTYP_NEWSLETTERARCHIV,
            \PAGE_NEWS, \PAGE_NEWSDETAIL,
            \PAGE_NEWSKATEGORIE,
            \PAGE_NEWSMONAT              => \LINKTYP_NEWS,
            \PAGE_SITEMAP                => \LINKTYP_SITEMAP,
            \PAGE_GRATISGESCHENK         => \LINKTYP_GRATISGESCHENK,
            \PAGE_WRB                    => \LINKTYP_WRB,
            \PAGE_PLUGIN                 => \LINKTYP_PLUGIN,
            \PAGE_AUSWAHLASSISTENT       => \LINKTYP_AUSWAHLASSISTENT,
            \PAGE_404                    => \LINKTYP_404,
            \PAGE_BESTELLVORGANG         => \LINKTYP_BESTELLVORGANG,
            \PAGE_BESTELLABSCHLUSS       => \LINKTYP_BESTELLABSCHLUSS,
            \PAGE_WUNSCHLISTE            => \LINKTYP_WUNSCHLISTE,
            \PAGE_VERGLEICHSLISTE        => \LINKTYP_VERGLEICHSLISTE,
            \PAGE_WARTUNG                => \LINKTYP_WARTUNG,
            \PAGE_BESTELLSTATUS          => \LINKTYP_BESTELLSTATUS,
            \PAGE_BEWERTUNG              => \LINKTYP_BEWERTUNG,
            default                      => 0,
        };
    }
}
