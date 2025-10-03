<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum Misc: string implements OptionInterface
{
    case LIVESEARCH_TOP_COUNT          = 'sonstiges_livesuche_all_top_count';
    case LIVESEARCH_RECENT_QUERIES     = 'sonstiges_livesuche_all_last_count';
    case FREE_GIFTS_USE                = 'sonstiges_gratisgeschenk_nutzen';
    case FREE_GIFTS_QTY                = 'sonstiges_gratisgeschenk_anzahl';
    case FREE_GIFTS_SORT               = 'sonstiges_gratisgeschenk_sortierung';
    case FREE_GIFTS_SHOW_HINT          = 'sonstiges_gratisgeschenk_wk_hinweis_anzeigen';
    case FREE_GIFTS_SHOW_NOT_AVAILABLE = 'sonstiges_gratisgeschenk_noch_nicht_verfuegbar_anzeigen';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::MISC;
    }
}
