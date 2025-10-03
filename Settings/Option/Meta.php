<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum Meta: string implements OptionInterface
{
    case PUBLISHER             = 'global_meta_publisher';
    case COPYRIGHT             = 'global_meta_copyright';
    case TITLE_APPEND          = 'global_meta_title_anhaengen';
    case TITLE_PRICE           = 'global_meta_title_preis';
    case TITLE_MAX_CHARS       = 'global_meta_maxlaenge_title';
    case KEYWORDS_MIN_CHARS    = 'global_meta_keywords_laenge';
    case DESCRIPTION_MAX_CHARS = 'global_meta_maxlaenge_description';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::META;
    }
}
