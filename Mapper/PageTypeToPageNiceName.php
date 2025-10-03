<?php

declare(strict_types=1);

namespace JTL\Mapper;

/**
 * Class PageTypeToPageNiceName
 * @package JTL\Mapper
 */
class PageTypeToPageNiceName
{
    public function mapPageTypeToPageNiceName(int $type): string
    {
        return match ($type) {
            \PAGE_STARTSEITE        => \__('Startseite'),
            \PAGE_VERSAND           => \__('Informationen zum Versand'),
            \PAGE_WRB               => \__('WRB'),
            \PAGE_AGB               => \__('AGB'),
            \PAGE_LIVESUCHE         => \__('Livesuche'),
            \PAGE_DATENSCHUTZ       => \__('Datenschutz'),
            \PAGE_HERSTELLER        => \__('Hersteller Übersicht'),
            \PAGE_SITEMAP           => \__('Sitemap'),
            \PAGE_GRATISGESCHENK    => \__('Gratis Geschenk'),
            \PAGE_AUSWAHLASSISTENT  => \__('Auswahlassistent'),
            \PAGE_EIGENE            => \__('pageCustom'),
            \PAGE_MEINKONTO         => \__('pageAccount'),
            \PAGE_LOGIN             => \__('Login'),
            \PAGE_REGISTRIERUNG     => \__('Registrieren'),
            \PAGE_WARENKORB         => \__('Warenkorb'),
            \PAGE_PASSWORTVERGESSEN => \__('Passwort vergessen'),
            \PAGE_KONTAKT           => \__('Kontakt'),
            \PAGE_NEWSLETTER        => \__('Newsletter'),
            \PAGE_NEWSLETTERARCHIV  => \__('Newsletterarchiv'),
            \PAGE_NEWS              => \__('News'),
            \PAGE_NEWSMONAT         => \__('pageNewsMonth'),
            \PAGE_NEWSKATEGORIE     => \__('pageNewsCategory'),
            \PAGE_NEWSDETAIL        => \__('pageNewsDetail'),
            \PAGE_PLUGIN            => \__('pagePlugin'),
            \PAGE_404               => \__('404'),
            \PAGE_BESTELLVORGANG    => \__('Bestellvorgang'),
            \PAGE_BESTELLABSCHLUSS  => \__('Bestellabschluss'),
            \PAGE_WUNSCHLISTE       => \__('Wunschliste'),
            \PAGE_VERGLEICHSLISTE   => \__('Vergleichsliste'),
            \PAGE_ARTIKEL           => \__('pageProduct'),
            \PAGE_ARTIKELLISTE      => \__('pageProductList'),
            \PAGE_BEWERTUNG         => \__('pageRating'),
            \PAGE_WARTUNG           => \__('pageMaintenance'),
            \PAGE_BESTELLSTATUS     => \__('pageOrderStatus'),
            \PAGE_UNBEKANNT         => \__('pageUnknown'),
            default                 => '',
        };
    }
}
