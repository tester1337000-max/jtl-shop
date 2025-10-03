<?php

declare(strict_types=1);

namespace JTL\Settings;

enum Section: string
{
    case GLOBAL          = 'global';
    case HOME            = 'startseite';
    case EMAIL           = 'emails';
    case PRODUCT         = 'artikeldetails';
    case OVERVIEW        = 'artikeluebersicht';
    case CUSTOMER        = 'kunden';
    case LOGO            = 'logo';
    case CHECKOUT        = 'kaufabwicklung';
    case BOX             = 'boxen';
    case IMAGE           = 'bilder';
    case MISC            = 'sonstiges';
    case PAYMENT         = 'zahlungsarten';
    case CONTACT         = 'kontakt';
    case SHOPINFO        = 'shopinfo';
    case RSS             = 'rss';
    case COMPARE         = 'vergleichsliste';
    case PRICEHISTORY    = 'preisverlauf';
    case REVIEW          = 'bewertung';
    case NEWSLETTER      = 'newsletter';
    case CUSTOMERFIELD   = 'kundenfeld';
    case FILTER          = 'navigationsfilter';
    case BLOCKLIST       = 'emailblacklist';
    case META            = 'metaangaben';
    case NEWS            = 'news';
    case SITEMAP         = 'sitemap';
    case SEARCHSPECIAL   = 'suchspecials';
    case SELECTIONWIZARD = 'auswahlassistent';
    case CRON            = 'cron';
    case FILESYSTEM      = 'fs';
    case CACHE           = 'caching';
    case CONSENT         = 'consentmanager';
    case TEMPLATE        = 'template';
    case BRANDING        = 'branding';
}
