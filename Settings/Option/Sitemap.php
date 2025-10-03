<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum Sitemap: string implements OptionInterface
{
    case PAGES_SHOW                 = 'sitemap_seiten_anzeigen';
    case CATEGORIES_SHOW            = 'sitemap_kategorien_anzeigen';
    case MANUFACTURERS_SHOW         = 'sitemap_hersteller_anzeigen';
    case NEWS_SHOW                  = 'sitemap_news_anzeigen';
    case NEWSCATEGORIES_SHOW        = 'sitemap_newskategorien_anzeigen';
    case LIVESEARCH_SHOW            = 'sitemap_livesuche_anzeigen';
    case PRODUCT_IMAGES_SHOW        = 'sitemap_googleimage_anzeigen';
    case CATEGORY_IMAGES_SHOW       = 'sitemap_images_categories';
    case MANUFACTURER_IMAGES_SHOW   = 'sitemap_images_manufacturers';
    case NEWS_CATEGORY_IMAGES_SHOW  = 'sitemap_images_newscategory_items';
    case BLOG_IMAGES_SHOW           = 'sitemap_images_news_items';
    case CHARACTERISTIC_IMAGES_SHOW = 'sitemap_images_attributes';
    case CREATE_ON_SYNC             = 'sitemap_wawiabgleich';
    case CHILD_ITEMS_EXPORT         = 'sitemap_varkombi_children_export';
    case CHANGEFREQ_SHOW            = 'sitemap_insert_changefreq';
    case PRIORITY_SHOW              = 'sitemap_insert_priority';
    case LASTMOD_SHOW               = 'sitemap_insert_lastmod';
    case PING_GOOGLE                = 'sitemap_google_ping';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::SITEMAP;
    }
}
