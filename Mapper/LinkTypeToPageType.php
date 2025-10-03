<?php

declare(strict_types=1);

namespace JTL\Mapper;

/**
 * Class LinkTypeToPageType
 * @package JTL\Mapper
 */
class LinkTypeToPageType
{
    public function map(int $type): int
    {
        return match ($type) {
            \LINKTYP_EIGENER_CONTENT,
            \LINKTYP_IMPRESSUM,
            \LINKTYP_BATTERIEGESETZ_HINWEISE    => \PAGE_EIGENE,
            \LINKTYP_STARTSEITE                 => \PAGE_STARTSEITE,
            \LINKTYP_VERSAND                    => \PAGE_VERSAND,
            \LINKTYP_LOGIN                      => \PAGE_LOGIN,
            \LINKTYP_REGISTRIEREN               => \PAGE_REGISTRIERUNG,
            \LINKTYP_WARENKORB                  => \PAGE_WARENKORB,
            \LINKTYP_PASSWORD_VERGESSEN         => \PAGE_PASSWORTVERGESSEN,
            \LINKTYP_AGB                        => \PAGE_AGB,
            \LINKTYP_DATENSCHUTZ                => \PAGE_DATENSCHUTZ,
            \LINKTYP_KONTAKT                    => \PAGE_KONTAKT,
            \LINKTYP_LIVESUCHE                  => \PAGE_LIVESUCHE,
            \LINKTYP_HERSTELLER                 => \PAGE_HERSTELLER,
            \LINKTYP_NEWSLETTER                 => \PAGE_NEWSLETTER,
            \LINKTYP_NEWSLETTERARCHIV           => \PAGE_NEWSLETTERARCHIV,
            \LINKTYP_NEWS                       => \PAGE_NEWS,
            \LINKTYP_SITEMAP                    => \PAGE_SITEMAP,
            \LINKTYP_GRATISGESCHENK             => \PAGE_GRATISGESCHENK,
            \LINKTYP_WRB, \LINKTYP_WRB_FORMULAR => \PAGE_WRB,
            \LINKTYP_PLUGIN                     => \PAGE_PLUGIN,
            \LINKTYP_AUSWAHLASSISTENT           => \PAGE_AUSWAHLASSISTENT,
            \LINKTYP_404                        => \PAGE_404,
            \LINKTYP_BESTELLVORGANG             => \PAGE_BESTELLVORGANG,
            \LINKTYP_BESTELLABSCHLUSS           => \PAGE_BESTELLABSCHLUSS,
            \LINKTYP_BESTELLSTATUS              => \PAGE_BESTELLSTATUS,
            \LINKTYP_WUNSCHLISTE                => \PAGE_WUNSCHLISTE,
            \LINKTYP_VERGLEICHSLISTE            => \PAGE_VERGLEICHSLISTE,
            \LINKTYP_WARTUNG                    => \PAGE_WARTUNG,
            \LINKTYP_BEWERTUNG                  => \PAGE_BEWERTUNG,
            default                             => \PAGE_UNBEKANNT,
        };
    }
}
