<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum Box: string implements OptionInterface
{
    case TOP_RATED_BASE_NUMBER            = 'boxen_topbewertet_basisanzahl';
    case COMING_SOON_QTY                  = 'box_erscheinende_anzahl_anzeige';
    case LAST_VIEWED_QTY                  = 'box_zuletztangesehen_anzahl';
    case TOP_OFFERS_QTY                   = 'box_topangebot_anzahl_anzeige';
    case NEW_IN_PRODUCT_RANGE_DAYS        = 'box_neuimsortiment_alter_tage';
    case NEW_IN_PRODUCT_RANGE_QTY         = 'box_neuimsortiment_anzahl_anzeige';
    case SPECIAL_OFFERS_QTY               = 'box_sonderangebote_anzahl_anzeige';
    case BESTSELLER_BASE_NUMBER           = 'box_bestseller_anzahl_basis';
    case BESTSELLER_QTY                   = 'box_bestseller_anzahl_anzeige';
    case TOP_RATED_MIN_STARS              = 'boxen_topbewertet_minsterne';
    case TOP_RATED_QTY                    = 'boxen_topbewertet_anzahl';
    case COMING_SOON_BASE_NUMBER          = 'box_erscheinende_anzahl_basis';
    case SPECIAL_OFFERS_BASE_NUMBER       = 'box_sonderangebote_anzahl_basis';
    case NEW_IN_PROUDCT_RANGE_BASE_NUMBER = 'box_neuimsortiment_anzahl_basis';
    case TOP_OFFERS_BASE_NUMBER           = 'box_topangebot_anzahl_basis';
    case MANUFACTURERS_QTY                = 'box_hersteller_anzahl_anzeige';
    case LIVESEARCH_SHOW                  = 'boxen_livesuche_anzeigen';
    case LIVESEARCH_QTY                   = 'boxen_livesuche_count';
    case COMPARE_SHOW                     = 'boxen_vergleichsliste_anzeigen';
    case WISHLIST_QTY                     = 'boxen_wunschzettel_anzahl';
    case WISHLIST_IMAGES_SHOW             = 'boxen_wunschzettel_bilder';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::BOX;
    }
}
