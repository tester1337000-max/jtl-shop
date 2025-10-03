<?php

declare(strict_types=1);

namespace JTL\Backend;

/**
 * Class Permissions
 * @package JTL\Backend
 */
class Permissions
{
    public const ACCOUNT_VIEW                    = 'ACCOUNT_VIEW';
    public const API_KEYS_VIEW                   = 'API_KEYS_VIEW';
    public const BOXES_VIEW                      = 'BOXES_VIEW';
    public const CHECKBOXES_VIEW                 = 'CHECKBOXES_VIEW';
    public const CONSENT_MANAGER                 = 'CONSENT_MANAGER';
    public const CONTENT_EMAIL_TEMPLATE_VIEW     = 'CONTENT_EMAIL_TEMPLATE_VIEW';
    public const CONTENT_NEWS_SYSTEM_VIEW        = 'CONTENT_NEWS_SYSTEM_VIEW';
    public const CONTENT_PAGE_VIEW               = 'CONTENT_PAGE_VIEW';
    public const COUNTRY_VIEW                    = 'COUNTRY_VIEW';
    public const CRON_VIEW                       = 'CRON_VIEW';
    public const DASHBOARD_VIEW                  = 'DASHBOARD_VIEW';
    public const DBCHECK_VIEW                    = 'DBCHECK_VIEW';
    public const DIAGNOSTIC_VIEW                 = 'DIAGNOSTIC_VIEW';
    public const DISPLAY_ARTICLEOVERLAYS_VIEW    = 'DISPLAY_ARTICLEOVERLAYS_VIEW';
    public const DISPLAY_BANNER_VIEW             = 'DISPLAY_BANNER_VIEW';
    public const DISPLAY_BRANDING_VIEW           = 'DISPLAY_BRANDING_VIEW';
    public const DISPLAY_IMAGES_VIEW             = 'DISPLAY_IMAGES_VIEW';
    public const DISPLAY_OWN_LOGO_VIEW           = 'DISPLAY_OWN_LOGO_VIEW';
    public const DISPLAY_TEMPLATE_VIEW           = 'DISPLAY_TEMPLATE_VIEW';
    public const EMAIL_REPORTS_VIEW              = 'EMAIL_REPORTS_VIEW';
    public const EMAILHISTORY_VIEW               = 'EMAILHISTORY_VIEW';
    public const EXPORT_FORMATS_VIEW             = 'EXPORT_FORMATS_VIEW';
    public const EXPORT_RSSFEED_VIEW             = 'EXPORT_RSSFEED_VIEW';
    public const EXPORT_SCHEDULE_VIEW            = 'EXPORT_SCHEDULE_VIEW';
    public const EXPORT_SITEMAP_VIEW             = 'EXPORT_SITEMAP_VIEW';
    public const EXTENSION_SELECTIONWIZARD_VIEW  = 'EXTENSION_SELECTIONWIZARD_VIEW';
    public const FILECHECK_VIEW                  = 'FILECHECK_VIEW';
    public const FILESYSTEM_VIEW                 = 'FILESYSTEM_VIEW';
    public const IMAGE_UPLOAD                    = 'IMAGE_UPLOAD';
    public const IMPORT_CUSTOMER_VIEW            = 'IMPORT_CUSTOMER_VIEW';
    public const IMPORT_NEWSLETTER_RECEIVER_VIEW = 'IMPORT_NEWSLETTER_RECEIVER_VIEW';
    public const LANGUAGE_VIEW                   = 'LANGUAGE_VIEW';
    public const LICENSE_MANAGER                 = 'LICENSE_MANAGER';
    public const MODULE_COMPARELIST_VIEW         = 'MODULE_COMPARELIST_VIEW';
    public const MODULE_GIFT_VIEW                = 'MODULE_GIFT_VIEW';
    public const MODULE_LIVESEARCH_VIEW          = 'MODULE_LIVESEARCH_VIEW';
    public const MODULE_NEWSLETTER_VIEW          = 'MODULE_NEWSLETTER_VIEW';
    public const MODULE_PRICECHART_VIEW          = 'MODULE_PRICECHART_VIEW';
    public const MODULE_SAVED_BASKETS_VIEW       = 'MODULE_SAVED_BASKETS_VIEW';
    public const MODULE_VOTESYSTEM_VIEW          = 'MODULE_VOTESYSTEM_VIEW';
    public const MODULE_WISHLIST_VIEW            = 'MODULE_WISHLIST_VIEW';
    public const OBJECTCACHE_VIEW                = 'OBJECTCACHE_VIEW';
    public const OPC_VIEW                        = 'OPC_VIEW';
    public const ORDER_AGB_WRB_VIEW              = 'ORDER_AGB_WRB_VIEW';
    public const ORDER_COUPON_VIEW               = 'ORDER_COUPON_VIEW';
    public const ORDER_CUSTOMERFIELDS_VIEW       = 'ORDER_CUSTOMERFIELDS_VIEW';
    public const ORDER_PACKAGE_VIEW              = 'ORDER_PACKAGE_VIEW';
    public const ORDER_PAYMENT_VIEW              = 'ORDER_PAYMENT_VIEW';
    public const ORDER_SHIPMENT_VIEW             = 'ORDER_SHIPMENT_VIEW';
    public const ORDER_VIEW                      = 'ORDER_VIEW';
    public const PERMISSIONCHECK_VIEW            = 'PERMISSIONCHECK_VIEW';
    public const PLUGIN_ADMIN_VIEW               = 'PLUGIN_ADMIN_VIEW';
    public const PLUGIN_DETAIL_VIEW_ALL          = 'PLUGIN_DETAIL_VIEW_ALL';
    public const PLUGIN_DETAIL_VIEW_ID           = 'PLUGIN_DETAIL_VIEW_';
    public const PLZ_ORT_IMPORT_VIEW             = 'PLZ_ORT_IMPORT_VIEW';
    public const PROFILER_VIEW                   = 'PROFILER_VIEW';
    public const REDIRECT_VIEW                   = 'REDIRECT_VIEW';
    public const REPORT_VIEW                     = 'REPORT_VIEW';
    public const RESET_SHOP_VIEW                 = 'RESET_SHOP_VIEW';
    public const SETTINGS_ARTICLEDETAILS_VIEW    = 'SETTINGS_ARTICLEDETAILS_VIEW';
    public const SETTINGS_ARTICLEOVERVIEW_VIEW   = 'SETTINGS_ARTICLEOVERVIEW_VIEW';
    public const SETTINGS_BASKET_VIEW            = 'SETTINGS_BASKET_VIEW';
    public const SETTINGS_BOXES_VIEW             = 'SETTINGS_BOXES_VIEW';
    public const SETTINGS_CONTACTFORM_VIEW       = 'SETTINGS_CONTACTFORM_VIEW';
    public const SETTINGS_CUSTOMERFORM_VIEW      = 'SETTINGS_CUSTOMERFORM_VIEW';
    public const SETTINGS_EMAIL_BLACKLIST_VIEW   = 'SETTINGS_EMAIL_BLACKLIST_VIEW';
    public const SETTINGS_EMAILS_VIEW            = 'SETTINGS_EMAILS_VIEW';
    public const SETTINGS_GLOBAL_META_VIEW       = 'SETTINGS_GLOBAL_META_VIEW';
    public const SETTINGS_GLOBAL_VIEW            = 'SETTINGS_GLOBAL_VIEW';
    public const SETTINGS_IMAGES_VIEW            = 'SETTINGS_IMAGES_VIEW';
    public const SETTINGS_NAVIGATION_FILTER_VIEW = 'SETTINGS_NAVIGATION_FILTER_VIEW';
    public const SETTINGS_SEARCH_VIEW            = 'SETTINGS_SEARCH_VIEW';
    public const SETTINGS_SEPARATOR_VIEW         = 'SETTINGS_SEPARATOR_VIEW';
    public const SETTINGS_SITEMAP_VIEW           = 'SETTINGS_SITEMAP_VIEW';
    public const SETTINGS_SPECIALPRODUCTS_VIEW   = 'SETTINGS_SPECIALPRODUCTS_VIEW';
    public const SETTINGS_STARTPAGE_VIEW         = 'SETTINGS_STARTPAGE_VIEW';
    public const SHOP_UPDATE_VIEW                = 'SHOP_UPDATE_VIEW';
    public const SLIDER_VIEW                     = 'SLIDER_VIEW';
    public const STATS_CAMPAIGN_VIEW             = 'STATS_CAMPAIGN_VIEW';
    public const STATS_COUPON_VIEW               = 'STATS_COUPON_VIEW';
    public const STATS_CRAWLER_VIEW              = 'STATS_CRAWLER_VIEW';
    public const STATS_EXCHANGE_VIEW             = 'STATS_EXCHANGE_VIEW';
    public const STATS_LANDINGPAGES_VIEW         = 'STATS_LANDINGPAGES_VIEW';
    public const STATS_VISITOR_LOCATION_VIEW     = 'STATS_VISITOR_LOCATION_VIEW';
    public const STATS_VISITOR_VIEW              = 'STATS_VISITOR_VIEW';
    public const STATS_CONSENT_VIEW              = 'STATS_CONSENT_VIEW';
    public const SYSTEMLOG_VIEW                  = 'SYSTEMLOG_VIEW';
    public const UNLOCK_CENTRAL_VIEW             = 'UNLOCK_CENTRAL_VIEW';
    public const UPGRADE                         = 'UPGRADE';
    public const WAREHOUSE_VIEW                  = 'WAREHOUSE_VIEW';
    public const WAWI_SYNC_VIEW                  = 'WAWI_SYNC_VIEW';
    public const WIZARD_VIEW                     = 'WIZARD_VIEW';
}
