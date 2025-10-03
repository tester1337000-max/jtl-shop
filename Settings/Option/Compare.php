<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum Compare: string implements OptionInterface
{
    case DO_USE                 = 'vergleichsliste_anzeigen';
    case QTY                    = 'vergleichsliste_anzahl';
    case AVAILABILITY_SHOW      = 'vergleichsliste_verfuegbarkeit';
    case DELIVERY_TIME_SHOW     = 'vergleichsliste_lieferzeit';
    case SKU_SHOW               = 'vergleichsliste_artikelnummer';
    case MANUFACTURER_SHOW      = 'vergleichsliste_hersteller';
    case SHORT_DESCRIPTION_SHOW = 'vergleichsliste_kurzbeschreibung';
    case DESCRIPTION_SHOW       = 'vergleichsliste_beschreibung';
    case PRODUCT_WEIGHT_SHOW    = 'vergleichsliste_artikelgewicht';
    case SHIPPING_WEIGTH_SHOW   = 'vergleichsliste_versandgewicht';
    case VARIATIONS_SHOW        = 'vergleichsliste_variationen';
    case CHARACTERISTICS_SHOW   = 'vergleichsliste_merkmale';
    case COLUMN_WIDTH           = 'vergleichsliste_spaltengroesse';
    case TARGET                 = 'vergleichsliste_target';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::COMPARE;
    }
}
