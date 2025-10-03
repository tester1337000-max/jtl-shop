<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum Globals: string implements OptionInterface
{
    case SYSLOG_LEVEL                           = 'systemlog_flag';
    case WIZARD_DONE                            = 'global_wizard_done';
    case PRODUCT_VIEW_FILTER                    = 'artikel_artikelanzeigefilter';
    case PRODUCT_VIEW_FILTER_SEO                = 'artikel_artikelanzeigefilter_seo';
    case STOCK_LVL_GREEN                        = 'artikel_lagerampel_gruen';
    case STOCK_LVL_RED                          = 'artikel_lagerampel_rot';
    case STOCK_LVL_GREEN_NEGATIVE               = 'artikel_ampel_lagernull_gruen';
    case STOCK_LVL_NO_INVENTORY                 = 'artikel_lagerampel_keinlager';
    case ACTION_PRICE_0                         = 'global_preis0';
    case ACTION_VARIATION_NOSTOCK               = 'artikeldetails_variationswertlager';
    case FREE_SHIPPING_SHOW                     = 'global_versandfrei_anzeigen';
    case CART_REDIRECT                          = 'global_warenkorb_weiterleitung';
    case DELAY_SHOW                             = 'global_lieferverzoegerung_anzeigen';
    case SHIPPING_CLASS_SHOW                    = 'global_versandklasse_anzeigen';
    case SHIPPING_FREE_COUNTRIES_SHOW           = 'global_versandkostenfrei_darstellung';
    case BESTSELLER_MIN_QTY                     = 'global_bestseller_minanzahl';
    case BESTSELLER_DAYS                        = 'global_bestseller_tage';
    case EMPTY_CATEGORY_FILTER                  = 'kategorien_anzeigefilter';
    case VAT_SHOW                               = 'global_ust_auszeichnung';
    case FOOTER_NOTS                            = 'global_fusszeilehinweis';
    case TAX_POSITIONS_SHOW                     = 'global_steuerpos_anzeigen';
    case CONSISTENT_GROSS_PRICES                = 'consistent_gross_prices';
    case ROUTING_SCHEME                         = 'routing_scheme';
    case ROUTING_DEFAULT_LANG                   = 'routing_default_language';
    case ROUTING_DUPLICATES                     = 'routing_duplicates';
    case WISHLIST_REDIRECT                      = 'global_wunschliste_weiterleitung';
    case WISHLIST_SHOW                          = 'global_wunschliste_anzeigen';
    case WISHLIST_SHARE                         = 'global_wunschliste_freunde_aktiv';
    case WISHLIST_MAX_RECIPIENTS                = 'global_wunschliste_max_email';
    case WISHLIST_DELETE_PURCHASED              = 'global_wunschliste_artikel_loeschen_nach_kauf';
    case MAINTENANCE_MODE_ACTIVE                = 'wartungsmodus_aktiviert';
    case SHIPPING_ESTIMATION_SHOW               = 'global_versandermittlung_anzeigen';
    case SHIPPING_ESTIMATION_DELIVERY_TIME_SHOW = 'global_versandermittlung_lieferdauer_anzeigen';
    case RECORD_FAILED_LOGINS                   = 'admin_login_logger_mode';
    case COOKIE_LIFETIME                        = 'global_cookie_lifetime';
    case COOKIE_PATH                            = 'global_cookie_path';
    case COOKIE_DOMAIN                          = 'global_cookie_domain';
    case COOKIE_SECURE                          = 'global_cookie_secure';
    case COOKIE_HTTPONLY                        = 'global_cookie_httponly';
    case COOKIE_SAMESITE                        = 'global_cookie_samesite';
    case SHOP_NAME                              = 'global_shopname';
    case VISIBILITY_GUESTS                      = 'global_sichtbarkeit';
    case ACCOUNT_ACTIVATION                     = 'global_kundenkonto_aktiv';
    case CHECKOUT_SSL                           = 'kaufabwicklung_ssl_nutzen';
    case ITEM_PRICE_SHIPPING                    = 'global_versandhinweis';
    case FUTURE_RELEASE                         = 'global_erscheinende_kaeuflich';
    case RETURN_DEADLINE                        = 'global_cancellation_time';
    case RMA_ENABLED                            = 'global_rma_enabled';
    case VISITOR_COUNTER                        = 'global_zaehler_anzeigen';
    case DECIMAL_SEPARATOR_SPLIT_QTY            = 'global_dezimaltrennzeichen_sonstigeangaben';
    case GARBAGE_COLLECTOR                      = 'garbagecollector_wawiabgleich';
    case CHARACTERISTIC_URL_INDEXING            = 'global_merkmalwert_url_indexierung';
    case REDIRECTS_404                          = 'redirect_save_404';
    case REDIRECTS_AUTOMATIC_PARAM_HANDLING     = 'redirect_automatic_param_handling';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::GLOBAL;
    }
}
