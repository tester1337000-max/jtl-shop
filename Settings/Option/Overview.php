<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum Overview: string implements OptionInterface
{
    case SEARCH_FULLTEXT               = 'suche_fulltext';
    case PRIO_NAME                     = 'suche_prio_name';
    case PRIO_SEARCH_TERMS             = 'suche_prio_suchbegriffe';
    case PRIO_PRODUCT_SKU              = 'suche_prio_artikelnummer';
    case PRIO_SHORT_DESCRIPTION        = 'suche_prio_kurzbeschreibung';
    case PRIO_DESCRIPTION              = 'suche_prio_beschreibung';
    case PRIO_EAN                      = 'suche_prio_ean';
    case PRIO_ISBN                     = 'suche_prio_isbn';
    case PRIO_HAN                      = 'suche_prio_han';
    case PRIO_NOT                      = 'suche_prio_anmerkung';
    case SEARCH_MIN_CHARS              = 'suche_min_zeichen';
    case PRIO_SORT_NAME                = 'suche_sortierprio_name';
    case PRIO_SORT_DESC                = 'suche_sortierprio_name_ab';
    case PRIO_SORT_PRICE               = 'suche_sortierprio_preis';
    case PRIO_SORT_PRICE_DESC          = 'suche_sortierprio_preis_ab';
    case PRIO_SORT_EAN                 = 'suche_sortierprio_ean';
    case PRIO_SORT_DATE                = 'suche_sortierprio_erstelldatum';
    case PRIO_SORT_SKU                 = 'suche_sortierprio_artikelnummer';
    case PRIO_SORT_WEIGHT              = 'suche_sortierprio_gewicht';
    case PRIO_SORT_RELEASE             = 'suche_sortierprio_erscheinungsdatum';
    case PRIO_SORT_BESTSELLER          = 'suche_sortierprio_bestseller';
    case PRIO_SORT_RATING              = 'suche_sortierprio_bewertung';
    case SEARCH_MAX_HITS               = 'suche_max_treffer';
    case SEARCH_MAX_SUGGESTIONS        = 'suche_ajax_anzahl';
    case STOCK_LVL_DISPLAY             = 'artikeluebersicht_lagerbestandsanzeige';
    case SHOW_SHORT_DESCRIPTION        = 'artikeluebersicht_kurzbeschreibung_anzeigen';
    case SHOW_SUPPLIER_STOCK           = 'artikeluebersicht_lagerbestandanzeige_anzeigen';
    case SHOW_MANUFACTURER             = 'artikeluebersicht_hersteller_anzeigen';
    case PRODUCTS_PER_PAGE             = 'artikeluebersicht_artikelproseite';
    case DEFAULT_SORTING               = 'artikeluebersicht_artikelsortierung';
    case MAX_PAGES                     = 'artikeluebersicht_max_seitenzahl';
    case DISCOUNT_VIEW                 = 'artikeluebersicht_rabattanzeige';
    case SPECIAL_PRICES_VIEW           = 'artikeluebersicht_sonderpreisanzeige';
    case PRICE_GAP                     = 'articleoverview_pricerange_width';
    case TOP_ITEMS_SHOW                = 'topbest_anzeigen';
    case TOP_ITEMS_QTY                 = 'artikelubersicht_topbest_anzahl';
    case GROUP_BESTSELLERS             = 'artikelubersicht_bestseller_gruppieren';
    case BESTSELLERS_QTY_OVERVIEW      = 'artikeluebersicht_bestseller_anzahl';
    case SHOW_SHIPPING_WEIGHT          = 'artikeluebersicht_gewicht_anzeigen';
    case SHOW_ITEM_WEIGHT              = 'artikeluebersicht_artikelgewicht_anzeigen';
    case SHOW_COMPARE                  = 'artikeluebersicht_vergleichsliste_anzeigen';
    case SHOW_WISHLIST                 = 'artikeluebersicht_wunschzettel_anzeigen';
    case SHOW_PERMISSIBLE_ORDER_QTY    = 'artikeluebersicht_artikelintervall_anzeigen';
    case MAX_SEARCH_QUERIES_PER_IP     = 'livesuche_max_ip_count';
    case SHOW_SEARCH_FILERS_QTY        = 'suchfilter_anzeigen_ab';
    case EXTENDED_VIEW                 = 'artikeluebersicht_erw_darstellung';
    case EXTENDED_VIEW_DEFAULT         = 'artikeluebersicht_erw_darstellung_stdansicht';
    case ITEM_QTY_LIST_VIEW_OPTIONS    = 'products_per_page_list';
    case ITEM_QTY_GALLERY_VIEW_OPTIONS = 'products_per_page_gallery';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::OVERVIEW;
    }
}
