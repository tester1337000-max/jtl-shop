<?php

declare(strict_types=1);

namespace JTL\Reset;

enum ResetContentType: string
{
    case PRODUCTS              = 'artikel';
    case TAXES                 = 'steuern';
    case REVISIONS             = 'revisions';
    case NEWS                  = 'news';
    case BESTSELLER            = 'bestseller';
    case STATS_VISITOR         = 'besucherstatistiken';
    case STATS_PRICES          = 'preisverlaeufe';
    case MESSAGES_AVAILABILITY = 'verfuegbarkeitsbenachrichtigungen';
    case SEARCH_REQUESTS       = 'suchanfragen';
    case RATINGS               = 'bewertungen';
    case WISHLIST              = 'wishlist';
    case COMPARELIST           = 'comparelist';
    case CUSTOMERS             = 'shopkunden';
    case ORDERS                = 'bestellungen';
    case COUPONS               = 'kupons';
    case SETTINGS              = 'shopeinstellungen';
}
