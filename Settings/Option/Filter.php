<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum Filter: string implements OptionInterface
{
    case ONE_RESULT_REDIRECT                   = 'allgemein_weiterleitung';
    case SEARCHSPECIAL_FILTER_USE              = 'allgemein_suchspecialfilter_benutzen';
    case SEARCHSPECIAL_FILTER_TYPE             = 'search_special_filter_type';
    case CATEGORY_IMAGE_SHOW                   = 'kategorie_bild_anzeigen';
    case CATEGORY_DESCRIPTION_SHOW             = 'kategorie_beschreibung_anzeigen';
    case SUB_CATEGORIES_SHOW                   = 'artikeluebersicht_bild_anzeigen';
    case SUB_CATEGORIES_LVL2_SHOW              = 'unterkategorien_lvl2_anzeigen';
    case SUB_CATEGORIES_DESCRIPTION_SHOW       = 'unterkategorien_beschreibung_anzeigen';
    case AVAILABILITY_FILTER_SHOW              = 'allgemein_availabilityfilter_benutzen';
    case MANUFACTURER_FILTER_SHOW              = 'allgemein_herstellerfilter_benutzen';
    case MANUFACTURER_FILTER_TYPE              = 'manufacturer_filter_type';
    case MANUFACTURER_FILTER_DISPLAY           = 'hersteller_anzeigen_als';
    case CATEGORY_FILTER_SHOW                  = 'allgemein_kategoriefilter_benutzen';
    case CATEGORY_FILTER_DISPLAY               = 'kategoriefilter_anzeigen_als';
    case CATEGORY_FILTER_TYPE                  = 'category_filter_type';
    case REVIEW_FILTER_SHOW                    = 'bewertungsfilter_benutzen';
    case SEARCH_FILTER_SHOW                    = 'suchtrefferfilter_nutzen';
    case SEARCH_FILTER_QTY                     = 'suchtrefferfilter_anzahl';
    case CHARACTERISTIC_FILTER_SHOW            = 'merkmalfilter_verwenden';
    case CHARACTERISTIC_FILTER_DISPLAY         = 'merkmal_anzeigen_als';
    case CHARACTERISTIC_FILTER_COUNT_SHOW      = 'merkmalfilter_trefferanzahl_anzeigen';
    case CHARACTERISTIC_FILTER_MAX_ITEMS       = 'merkmalfilter_maxmerkmale';
    case CHARACTERISTIC_FILTER_MAX_VALUES      = 'merkmalfilter_maxmerkmalwerte';
    case PRICERANGE_FILTER_SHOW                = 'preisspannenfilter_benutzen';
    case PRICERANGE_FILTER_HIDE_EMPTY          = 'preisspannenfilter_spannen_ausblenden';
    case PRICERANGE_FILTER_CALCULATION         = 'preisspannenfilter_anzeige_berechnung';
    case MANUFACTURER_IMAGE_SHOW               = 'hersteller_bild_anzeigen';
    case MANUFACTURER_DESCRIPTION_SHOW         = 'hersteller_beschreibung_anzeigen';
    case CHARACTERISTIC_VALUE_IMAGE_SHOW       = 'merkmalwert_bild_anzeigen';
    case CHARACTERISTIC_VALUE_DESCRIPTION_SHOW = 'merkmalwert_beschreibung_anzeigen';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::FILTER;
    }
}
