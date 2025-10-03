<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum RSS: string implements OptionInterface
{
    case DO_USE         = 'rss_nutzen';
    case TITLE          = 'rss_titel';
    case DESCRIPTION    = 'rss_description';
    case COPYRIGHT      = 'rss_copyright';
    case LOGO_URL       = 'rss_logoURL';
    case DAYS           = 'rss_alterTage';
    case PRODUCTS_SHOW  = 'rss_artikel_beachten';
    case NEWS_SHOW      = 'rss_news_beachten';
    case REVIEWS_SHOW   = 'rss_bewertungen_beachten';
    case CREATE_ON_SYNC = 'rss_wawiabgleich';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::RSS;
    }
}
