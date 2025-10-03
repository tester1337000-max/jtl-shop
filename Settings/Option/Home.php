<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum Home: string implements OptionInterface
{
    case NEW_PRODUCTS_QTY    = 'startseite_neuimsortiment_anzahl';
    case NEW_PRODUCTS_SORT   = 'startseite_neuimsortiment_sortnr';
    case TOP_PRODUCTS_QTY    = 'startseite_topangebote_anzahl';
    case TOP_PRODUCTS_SORT   = 'startseite_topangebote_sortnr';
    case SPECIAL_OFFERS_QTY  = 'startseite_sonderangebote_anzahl';
    case SPECIAL_OFFERS_SORT = 'startseite_sonderangebote_sortnr';
    case BESTSELLERS_QTY     = 'startseite_bestseller_anzahl';
    case BESTSELLERS_SORT    = 'startseite_bestseller_sortnr';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::HOME;
    }
}
