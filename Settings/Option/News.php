<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum News: string implements OptionInterface
{
    case DO_USE                       = 'news_benutzen';
    case COMMENTS_USE                 = 'news_kommentare_nutzen';
    case OVERVIEW_QTY                 = 'news_anzahl_uebersicht';
    case CONTENT_QTY                  = 'news_anzahl_content';
    case BOX_QTY                      = 'news_anzahl_box';
    case COMMENTS_ACTIVATE            = 'news_kommentare_freischalten';
    case COMMENTS_PER_PAGE            = 'news_kommentare_anzahlproseite';
    case COMMENTS_PER_USER            = 'news_kommentare_anzahlprobesucher';
    case ATEGORIES_SHOW               = 'news_kategorie_unternewsanzeigen';
    case COMMENTS_RESPONSE_COUNT_SHOW = 'news_kommentare_anzahl_antwort_kommentare_anzeigen';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::NEWS;
    }
}
