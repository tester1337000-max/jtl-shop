<?php

declare(strict_types=1);

namespace JTL\Mapper;

/**
 * Class PageTypeToPageName
 * @package JTL\Mapper
 */
class PageTypeToPageName
{
    public function map(int $type): string
    {
        return match ($type) {
            \PAGE_STARTSEITE,
            \PAGE_VERSAND,
            \PAGE_WRB,
            \PAGE_AGB,
            \PAGE_LIVESUCHE,
            \PAGE_DATENSCHUTZ,
            \PAGE_HERSTELLER,
            \PAGE_SITEMAP,
            \PAGE_GRATISGESCHENK,
            \PAGE_AUSWAHLASSISTENT,
            \PAGE_EIGENE            => 'SEITE',
            \PAGE_MEINKONTO,
            \PAGE_LOGIN             => 'MEIN KONTO',
            \PAGE_REGISTRIERUNG     => 'REGISTRIEREN',
            \PAGE_WARENKORB         => 'Warenkorb',
            \PAGE_PASSWORTVERGESSEN => 'PASSWORT VERGESSEN',
            \PAGE_KONTAKT           => 'KONTAKT',
            \PAGE_NEWSLETTER,
            \PAGE_NEWSLETTERARCHIV  => 'NEWSLETTER',
            \PAGE_NEWS              => 'News',
            \PAGE_NEWSMONAT         => 'NEWSMONAT',
            \PAGE_NEWSKATEGORIE     => 'NEWSKATEGORIE',
            \PAGE_NEWSDETAIL        => 'NEWSDETAIL',
            \PAGE_PLUGIN            => 'PLUGIN',
            \PAGE_404               => '404',
            \PAGE_BESTELLVORGANG,
            \PAGE_BESTELLABSCHLUSS  => 'BESTELLVORGANG',
            \PAGE_WUNSCHLISTE       => 'Wunschliste',
            \PAGE_VERGLEICHSLISTE   => 'VERGLEICHSLISTE',
            \PAGE_ARTIKEL,
            \PAGE_ARTIKELLISTE      => 'Artikel',
            default                 => '',
        };
    }
}
