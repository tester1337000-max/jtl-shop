<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum Product: string implements OptionInterface
{
    case AVAILABILITY_REQ_SHOW           = 'benachrichtigung_nutzen';
    case AVAILABILITY_REQ_FIRSTNAME      = 'benachrichtigung_abfragen_vorname';
    case AVAILABILITY_REQ_LASTNAME       = 'benachrichtigung_abfragen_nachname';
    case AVAILABILITY_REQ_LOCK_TIME      = 'benachrichtigung_sperre_minuten';
    case AVAILABILITY_REQ_MIN_QTY        = 'benachrichtigung_min_lagernd';
    case AVAILABILITY_REQ_CAPTCHA        = 'benachrichtigung_abfragen_captcha';
    case PRODUCT_QUESTIONS_SHOW          = 'artikeldetails_fragezumprodukt_anzeigen';
    case PRODUCT_QUESTIONS_EMAIL         = 'artikeldetails_fragezumprodukt_email';
    case PRODUCT_QUESTIONS_SALUTATION    = 'produktfrage_abfragen_anrede';
    case PRODUCT_QUESTIONS_FIRSTNAM      = 'produktfrage_abfragen_vorname';
    case PRODUCT_QUESTIONS_LASTNAME      = 'produktfrage_abfragen_nachname';
    case PRODUCT_QUESTIONS_COMPANY       = 'produktfrage_abfragen_firma';
    case PRODUCT_QUESTIONS_TEL           = 'produktfrage_abfragen_tel';
    case PRODUCT_QUESTIONS_FAX           = 'produktfrage_abfragen_fax';
    case PRODUCT_QUESTIONS_MOBILE        = 'produktfrage_abfragen_mobil';
    case PRODUCT_QUESTIONS_COPY          = 'produktfrage_kopiekunde';
    case PRODUCT_QUESTIONS_LOCK_TIME     = 'produktfrage_sperre_minuten';
    case PRODUCT_QUESTIONS_CAPTCHA       = 'produktfrage_abfragen_captcha';
    case XSELLING_DEFAULT_SHOW           = 'artikeldetails_xselling_standard_anzeigen';
    case XSELLING_OTHERS_BOUGHT_SHOW     = 'artikeldetails_xselling_kauf_anzeigen';
    case XSELLING_QTY                    = 'artikeldetails_xselling_kauf_anzahl';
    case XSELLING_PARENT_SHOW            = 'artikeldetails_xselling_kauf_parent';
    case COMPARELIST_SHOW                = 'artikeldetails_vergleichsliste_anzeigen';
    case MEDIAFILES_SHOW                 = 'mediendatei_anzeigen';
    case SIMILAR_ITEMS_QTY               = 'artikeldetails_aehnlicheartikel_anzahl';
    case BOM_SHOW                        = 'artikeldetails_stueckliste_anzeigen';
    case BUNDE_SHOW                      = 'artikeldetails_produktbundle_nutzen';
    case CHARACTERISTICS_SHOW            = 'merkmale_anzeigen';
    case WEIGHT_SHOW                     = 'artikeldetails_gewicht_anzeigen';
    case CONTENT_SHOW                    = 'artikeldetails_inhalt_anzeigen';
    case PRODUCT_WEIGHT_SHOW             = 'artikeldetails_artikelgewicht_anzeigen';
    case PRODUCT_DIMENSIONS_SHOW         = 'artikeldetails_abmessungen_anzeigen';
    case PRODUCT_ATTRIBUTES_APPEND       = 'artikeldetails_attribute_anhaengen';
    case EXTRA_CHARGES_DISPLAY           = 'artikel_variationspreisanzeige';
    case STOCK_LVL_DISPLAY               = 'artikel_lagerbestandsanzeige';
    case SUPPLIER_STOCK_SHOW             = 'artikeldetails_lieferantenbestand_anzeigen';
    case SHORT_DESCRIPTION_SHOW          = 'artikeldetails_kurzbeschreibung_anzeigen';
    case RRP_SHOW                        = 'artikeldetails_uvp_anzeigen';
    case MANUFACTURER_SHOW               = 'artikeldetails_hersteller_anzeigen';
    case DELIVERY_STATUS_SHOW            = 'artikeldetails_lieferstatus_anzeigen';
    case PERMISSIBLE_ORDER_QTY_SHOW      = 'artikeldetails_artikelintervall_anzeigen';
    case SAME_MANUFACTURER_ITEMS_SHOW    = 'artikel_weitere_artikel_hersteller_anzeigen';
    case DISCOUNTS_VIEW                  = 'artikeldetails_rabattanzeige';
    case SPECIAL_PRICES_VIEW             = 'artikeldetails_sonderpreisanzeige';
    case CATEGORY_SHOW                   = 'artikeldetails_kategorie_anzeigen';
    case USE_TABS                        = 'artikeldetails_tabs_nutzen';
    case NAVIGATION_SHOW                 = 'artikeldetails_navi_blaettern';
    case SAVINGS_SHOW                    = 'sie_sparen_x_anzeigen';
    case CANONICAL_URL_CHILD             = 'artikeldetails_canonicalurl_varkombikind';
    case CART_MATRIX_SHOW                = 'artikeldetails_warenkorbmatrix_anzeige';
    case CART_MATRIX_VIEW                = 'artikeldetails_warenkorbmatrix_anzeigeformat';
    case CART_MATRIX_HIDE_UNAVAILABLE    = 'artikeldetails_warenkorbmatrix_lagerbeachten';
    case SHGEL_LIFE_EXPIRATION_DATE_SHOW = 'show_shelf_life_expiration_date';
    case ISBN_SHOW                       = 'isbn_display';
    case HAZARD_SIGN_SHOW                = 'adr_hazard_display';
    case GTIN_SHOW                       = 'gtin_display';
    case FILE_UPLOAD_PER_HOUR            = 'upload_modul_limit';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::PRODUCT;
    }
}
