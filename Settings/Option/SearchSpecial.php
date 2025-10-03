<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum SearchSpecial: string implements OptionInterface
{
    case BESTSELLERS_SORT          = 'suchspecials_sortierung_bestseller';
    case SPECIAL_OFFERS_SORT       = 'suchspecials_sortierung_sonderangebote';
    case NEW_IN_PRODUCT_RANGE_SORT = 'suchspecials_sortierung_neuimsortiment';
    case TOP_OFFERS_SORT           = 'suchspecials_sortierung_topangebote';
    case AVAILABLE_SOON_SORT       = 'suchspecials_sortierung_inkuerzeverfuegbar';
    case TOP_RATED_SORT            = 'suchspecials_sortierung_topbewertet';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::SEARCHSPECIAL;
    }
}
