<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum Template: string implements OptionInterface
{
    case USE_MINIFY                               = 'use_minify';
    case IS_NOVA                                  = 'is_nova';
    case THEME_DEFAULT                            = 'theme_default';
    case BUTTON_ANIMATED                          = 'button_animated';
    case WISH_COMPARE_ANIMATION                   = 'wish_compare_animation';
    case BUTTON_SCROLL_TOP                        = 'button_scroll_top';
    case SLIDER_FULL_WIDTH                        = 'slider_full_width';
    case BANNER_FULL_WIDTH                        = 'banner_full_width';
    case LEFT_SIDEBAR                             = 'left_sidebar';
    case MENU_TEMPLATE                            = 'menu_template';
    case MENU_SINGLE_ROW                          = 'menu_single_row';
    case MENU_MULTIPLE_ROWS                       = 'menu_multiple_rows';
    case MENU_CENTER                              = 'menu_center';
    case MENU_SCROLL                              = 'menu_scroll';
    case MENU_LOGO_HEIGHT                         = 'menu_logoheight';
    case MENU_LOGO_CENTERED                       = 'menu_logo_centered';
    case MENU_SHOW_TOPBAR                         = 'menu_show_topbar';
    case MENU_SEARCH_WIDTH                        = 'menu_search_width';
    case MENU_SEARCH_POSITION                     = 'menu_search_position';
    case HEADER_FULL_WIDTH                        = 'header_full_width';
    case MOBILE_SEARCH_TYPE                       = 'mobile_search_type';
    case SHOW_CATEGORIES                          = 'show_categories';
    case MOBILE_START_CATEGORY                    = 'mobile_start_category';
    case SHOW_CATEGORY_IMAGES                     = 'show_category_images';
    case SHOW_SUBCATEGORIES                       = 'show_subcategories';
    case SHOW_PAGES                               = 'show_pages';
    case SHOW_MANUFACTURERS                       = 'show_manufacturers';
    case SHOW_MANUFACTURER_IMAGES                 = 'show_manufacturer_images';
    case FILTER_PLACEMENT                         = 'filter_placement';
    case FILTER_SEARCH_COUNT                      = 'filter_search_count';
    case FILTER_ITEMS_ALWAYS_VISIBLE              = 'filter_items_always_visible';
    case FILTER_MAX_OPTIONS                       = 'filter_max_options';
    case BUY_PRODUCTLIST                          = 'buy_productlist';
    case VARIATION_SELECT_PRODUCTLIST_LIST        = 'variation_select_productlist_list';
    case VARIATION_MAX_VALUE_PRODUCTLIST_LIST     = 'variation_max_werte_productlist_list';
    case VARIATION_SELECT_PRODUCTLIST_GALLERY     = 'variation_select_productlist_gallery';
    case VARIATION_MAX_VALUES_PRODUCTLIST_GALLERY = 'variation_max_werte_productlist_gallery';
    case ALWAYS_SHOW_PRICE_RANGE                  = 'always_show_price_range';
    case SWATCH_SLIDER                            = 'swatch_slider';
    case CONFIG_POSITION                          = 'config_position';
    case CONFIG_LAYOUT                            = 'config_layout';
    case PRIMARY                                  = 'primary';
    case SECONDARY                                = 'secondary';
    case HEADER_BG_COLOR                          = 'header-bg-color';
    case HEADER_COLOR                             = 'header-color';
    case HEADER_BG_COLOR_SECONDARY                = 'header-bg-color-secondary';
    case HEADER_COLOR_SECONDARY                   = 'header-color-secondary';
    case FOOTER_BG_COLOR                          = 'footer-bg-color';
    case FOOTER_COLOR                             = 'footer-color';
    case COPYRIGHT_BG_COLOR                       = 'copyright-bg-color';
    case CUSTOM_VARIABLES                         = 'customVariables';
    case CUSTOM_CONTENT                           = 'customContent';
    case NEWSLETTER_FOOTER                        = 'newsletter_footer';
    case SOCIALMEDIA_FOOTER                       = 'socialmedia_footer';
    case FACEBOOK                                 = 'facebook';
    case TWITTER                                  = 'twitter';
    case YOUTUBE                                  = 'youtube';
    case XING                                     = 'xing';
    case LINKEDIN                                 = 'linkedin';
    case VIMEO                                    = 'vimeo';
    case INSTAGRAM                                = 'instagram';
    case PINTEREST                                = 'pinterest';
    case SKYPE                                    = 'skype';
    case TIKTOK                                   = 'tiktok';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::TEMPLATE;
    }
}
