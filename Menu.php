<?php

declare(strict_types=1);

namespace JTL\Backend;

use JTL\DB\DbInterface;
use JTL\L10n\GetText;
use JTL\Plugin\Helper as PluginHelper;
use JTL\Plugin\State;
use JTL\Router\Route;
use JTL\Shop;
use Psr\Http\Message\ServerRequestInterface;
use stdClass;

/**
 * Class Menu
 * @package JTL\Backend
 */
readonly class Menu
{
    private string $adminURL;

    public function __construct(private DbInterface $db, private AdminAccount $account, private GetText $getText)
    {
        $getText->loadAdminLocale('menu');
        $this->adminURL = Shop::getAdminURL() . '/';
    }

    /**
     * @param ServerRequestInterface $request
     * @return array<int, object{cName: string, icon: string,
     *      oLink_arr: array<int, stdClass>, oLinkGruppe_arr: array<int, stdClass>, key: string, active: bool}>
     */
    public function build(ServerRequestInterface $request): array
    {
        $params         = $request->getQueryParams();
        $path           = $request->getUri()->getPath();
        $requestedGroup = $params['group'] ?? null;
        $requestedPath  = \parse_url($request->getUri()->getPath(), \PHP_URL_PATH) ?: '';
        if ($requestedGroup !== null) {
            $requestedPath .= '?' . $requestedGroup;
        }
        $mainGroups = [];
        $adminMenu  = $this->getStructure();
        $sections   = [];
        $rootKey    = 0;
        foreach ($adminMenu as $menuName => $menu) {
            foreach ($menu->items as $subMenuName => $subMenu) {
                if (!\is_array($subMenu)) {
                    continue;
                }
                /** @var stdClass $item */
                foreach ($subMenu as $itemName => $item) {
                    if (!isset($item->section)) {
                        continue;
                    }
                    if (!isset($sections[$item->section])) {
                        $sections[$item->section] = [];
                    }

                    $groupName = $item->group ?? 'all';

                    $sections[$item->section][$groupName] = (object)[
                        'path'           => $menuName . ' -&gt; ' . $subMenuName . ' -&gt; ' . $itemName,
                        'url'            => $item->link,
                        'specialSetting' => $item->specialSetting ?? false,
                        'settingsAnchor' => $item->settingsAnchor ?? '',
                    ];
                }
            }
        }
        /** @var stdClass $rootEntry */
        foreach ($adminMenu as $rootName => $rootEntry) {
            $rootKey   = (string)$rootKey;
            $mainGroup = (object)[
                'cName'           => $rootName,
                'icon'            => $rootEntry->icon,
                'oLink_arr'       => [],
                'oLinkGruppe_arr' => [],
                'key'             => $rootKey,
                'active'          => false
            ];

            $secondKey = 0;
            /**
             * @var string                                  $secondName
             * @var string|stdClass|array<string, stdClass> $secondEntry
             */
            foreach ($rootEntry->items as $secondName => $secondEntry) {
                $linkGruppe = (object)[
                    'cName'     => $secondName,
                    'oLink_arr' => [],
                    'key'       => $rootKey . $secondKey,
                    'active'    => false
                ];

                if ($secondEntry === 'DYNAMIC_PLUGINS') {
                    if (\SAFE_MODE === true) {
                        continue;
                    }
                    $mayViewAll  = $this->account->permission(Permissions::PLUGIN_DETAIL_VIEW_ALL);
                    $pluginLinks = $this->db->getObjects(
                        'SELECT DISTINCT p.kPlugin, p.cName, p.nPrio
                            FROM tplugin AS p INNER JOIN tpluginadminmenu AS pam
                                ON p.kPlugin = pam.kPlugin
                            WHERE p.nStatus = :state
                            ORDER BY p.nPrio, p.cName',
                        ['state' => State::ACTIVATED]
                    );

                    foreach ($pluginLinks as $pluginLink) {
                        $pluginID   = (int)$pluginLink->kPlugin;
                        $permission = Permissions::PLUGIN_DETAIL_VIEW_ID . $pluginID;
                        if (!$mayViewAll && !$this->account->permission($permission)) {
                            continue;
                        }
                        $this->getText->loadPluginLocale(
                            'base',
                            PluginHelper::getLoaderByPluginID($pluginID)->init($pluginID)
                        );

                        $link = (object)[
                            'cLinkname' => \__($pluginLink->cName),
                            'cURL'      => $this->adminURL . Route::PLUGIN . '/' . $pluginID,
                            'cRecht'    => $permission,
                            'key'       => $rootKey . $secondKey . $pluginID,
                            'active'    => false
                        ];

                        $linkGruppe->oLink_arr[] = $link;
                        if (\str_ends_with($requestedPath, Route::PLUGIN . '/' . $pluginID)) {
                            $mainGroup->active  = true;
                            $linkGruppe->active = true;
                            $link->active       = true;
                        }
                    }
                } else {
                    $thirdKey = 0;

                    if (\is_object($secondEntry)) {
                        if (
                            ($secondEntry->disabled ?? false) === true
                            || (
                                isset($secondEntry->permissions)
                                && !$this->account->permission($secondEntry->permissions)
                            )
                        ) {
                            continue;
                        }
                        $linkGruppe->oLink_arr = (object)[
                            'cLinkname' => $secondName,
                            'cURL'      => $secondEntry->link,
                            'cRecht'    => $secondEntry->permissions ?? null,
                            'target'    => $secondEntry->target ?? null,
                            'active'    => false
                        ];
                        if (\str_ends_with($linkGruppe->oLink_arr->cURL, $requestedPath)) {
                            $mainGroup->active  = true;
                            $linkGruppe->active = true;
                        }
                    } else {
                        /**
                         * @var array<string, stdClass[]> $secondEntry
                         * @var string                    $thirdName
                         * @var stdClass|null             $thirdEntry
                         */
                        foreach ($secondEntry as $thirdName => $thirdEntry) {
                            if (\is_object($thirdEntry) && ($thirdEntry->disabled ?? false) === false) {
                                $link = (object)[
                                    'cLinkname' => $thirdName,
                                    'cURL'      => $thirdEntry->link,
                                    'cRecht'    => $thirdEntry->permissions,
                                    'key'       => $rootKey . $secondKey . $thirdKey,
                                    'active'    => false
                                ];
                            } else {
                                continue;
                            }
                            if (!$this->account->permission($link->cRecht)) {
                                continue;
                            }
                            $urlPath = \parse_url($link->cURL, \PHP_URL_PATH) ?? '';
                            if ($requestedGroup !== null && \str_contains($link->cURL, '?group')) {
                                if (\str_ends_with(\parse_url($link->cURL, \PHP_URL_QUERY) ?: '', $requestedGroup)) {
                                    $mainGroup->active  = true;
                                    $linkGruppe->active = true;
                                    $link->active       = true;
                                }
                            } elseif ($requestedPath === $urlPath) {
                                $hash = \mb_strpos($link->cURL, '#');
                                $url  = $link->cURL;
                                if ($hash !== false) {
                                    $url = \mb_substr($link->cURL, 0, $hash);
                                }
                                $linkPath = \str_replace(Shop::getURL(), '', $url);
                                if (\str_contains($path, $linkPath)) {
                                    $mainGroup->active  = true;
                                    $linkGruppe->active = true;
                                    $link->active       = true;
                                }
                            }
                            $linkGruppe->oLink_arr[] = $link;
                            $thirdKey++;
                        }
                    }
                }
                if (\is_object($linkGruppe->oLink_arr) || \count($linkGruppe->oLink_arr) > 0) {
                    $mainGroup->oLinkGruppe_arr[] = $linkGruppe;
                }
                $secondKey++;
            }

            if (\count($mainGroup->oLinkGruppe_arr) > 0) {
                $mainGroups[] = $mainGroup;
            }
            $rootKey++;
        }

        return $mainGroups;
    }

    /**
     * @return array<string, object{icon: string,
     *     items: array<string, string|stdClass|array<string, stdClass>>}&stdClass>
     */
    public function getStructure(): array
    {
        return [
            \__('Marketing')      => (object)[
                'icon'  => 'marketing',
                'items' => [
                    \__('Orders')     => (object)[
                        'link'        => $this->adminURL . Route::ORDERS,
                        'permissions' => Permissions::ORDER_VIEW,
                    ],
                    \__('Promotions') => [
                        \__('Newsletter') => (object)[
                            'link'           => $this->adminURL . Route::NEWSLETTER,
                            'permissions'    => 'MODULE_NEWSLETTER_VIEW',
                            'section'        => \CONF_NEWSLETTER,
                            'specialSetting' => true,
                            'settingsAnchor' => '#einstellungen',
                        ],
                        \__('Blog posts') => (object)[
                            'link'           => $this->adminURL . Route::NEWS,
                            'permissions'    => Permissions::CONTENT_NEWS_SYSTEM_VIEW,
                            'section'        => \CONF_NEWS,
                            'specialSetting' => true,
                            'settingsAnchor' => '#einstellungen',
                        ],
                        \__('Coupons')    => (object)[
                            'link'        => $this->adminURL . Route::COUPONS,
                            'permissions' => Permissions::ORDER_COUPON_VIEW,
                        ],
                        \__('Free gifts') => (object)[
                            'link'           => $this->adminURL . Route::GIFTS,
                            'permissions'    => Permissions::MODULE_GIFT_VIEW,
                            'section'        => \CONF_SONSTIGES,
                            'specialSetting' => true,
                            'settingsAnchor' => '#einstellungen',
                        ],
                    ],
                    \__('Statistics') => [
                        \__('Sales')             => (object)[
                            'link'        => $this->adminURL . Route::STATS . '/4',
                            'permissions' => Permissions::STATS_EXCHANGE_VIEW,
                        ],
                        \__('Campaigns')         => (object)[
                            'link'        => $this->adminURL . Route::CAMPAIGN . '#globalestats',
                            'permissions' => Permissions::STATS_CAMPAIGN_VIEW,
                        ],
                        \__('Baskets')           => (object)[
                            'link'        => $this->adminURL . Route::PERSISTENT_CART,
                            'permissions' => Permissions::MODULE_SAVED_BASKETS_VIEW,
                        ],
                        \__('Coupon statistics') => (object)[
                            'link'        => $this->adminURL . Route::COUPON_STATS,
                            'permissions' => Permissions::STATS_COUPON_VIEW,
                        ],
                        \__('Visitors')          => (object)[
                            'link'        => $this->adminURL . Route::STATS . '/1',
                            'permissions' => Permissions::STATS_VISITOR_VIEW,
                        ],
                        \__('Referrer pages')    => (object)[
                            'link'        => $this->adminURL . Route::STATS . '/2',
                            'permissions' => Permissions::STATS_VISITOR_LOCATION_VIEW,
                        ],
                        \__('Entry pages')       => (object)[
                            'link'        => $this->adminURL . Route::STATS . '/5',
                            'permissions' => Permissions::STATS_LANDINGPAGES_VIEW,
                        ],
                        \__('Search engines')    => (object)[
                            'link'        => $this->adminURL . Route::STATS . '/3',
                            'permissions' => Permissions::STATS_CRAWLER_VIEW,
                        ],
                        \__('Search queries')    => (object)[
                            'link'        => $this->adminURL . Route::LIVESEARCH,
                            'permissions' => Permissions::MODULE_LIVESEARCH_VIEW,
                        ],
                        \__('Consent manager')   => (object)[
                            'link'        => $this->adminURL . Route::STATS . '/6',
                            'permissions' => Permissions::STATS_CONSENT_VIEW,
                        ],
                    ],
                    \__('Reports')    => (object)[
                        'link'        => $this->adminURL . Route::STATUSMAIL,
                        'permissions' => Permissions::EMAIL_REPORTS_VIEW,
                    ],
                ]
            ],
            \__('Appearance')     => (object)[
                'icon'  => 'styling',
                'items' => [
                    \__('OnPage Composer')  => (object)[
                        'link'        => $this->adminURL . Route::OPCCC,
                        'permissions' => Permissions::OPC_VIEW,
                    ],
                    \__('Default views')    => [
                        \__('Home page')        => (object)[
                            'link'        => $this->adminURL . Route::CONFIG . '/' . \CONF_STARTSEITE,
                            'permissions' => Permissions::SETTINGS_STARTPAGE_VIEW,
                            'section'     => \CONF_STARTSEITE,
                        ],
                        \__('Item overview')    => (object)[
                            'link'           => $this->adminURL . Route::NAVFILTER,
                            'permissions'    => Permissions::SETTINGS_NAVIGATION_FILTER_VIEW,
                            'section'        => \CONF_NAVIGATIONSFILTER,
                            'specialSetting' => true,
                        ],
                        \__('Item detail page') => (object)[
                            'link'        => $this->adminURL . Route::CONFIG . '/' . \CONF_ARTIKELDETAILS,
                            'permissions' => Permissions::SETTINGS_ARTICLEDETAILS_VIEW,
                            'section'     => \CONF_ARTIKELDETAILS,
                        ],
                        \__('Checkout')         => (object)[
                            'link'        => $this->adminURL . Route::CONFIG . '/' . \CONF_KAUFABWICKLUNG,
                            'permissions' => Permissions::SETTINGS_BASKET_VIEW,
                            'section'     => \CONF_KAUFABWICKLUNG,
                        ],
                        \__('Comparison list')  => (object)[
                            'link'           => $this->adminURL . Route::COMPARELIST,
                            'permissions'    => Permissions::MODULE_COMPARELIST_VIEW,
                            'section'        => \CONF_VERGLEICHSLISTE,
                            'specialSetting' => true,
                            'settingsAnchor' => '#einstellungen',
                        ],
                        \__('Wish list')        => (object)[
                            'link'        => $this->adminURL . Route::WISHLIST,
                            'permissions' => Permissions::MODULE_WISHLIST_VIEW,
                        ],
                        \__('Contact form')     => (object)[
                            'link'           => $this->adminURL . Route::CONTACT_FORMS,
                            'permissions'    => Permissions::SETTINGS_CONTACTFORM_VIEW,
                            'section'        => \CONF_KONTAKTFORMULAR,
                            'specialSetting' => true,
                            'settingsAnchor' => '#config',
                        ],
                        \__('Registration')     => (object)[
                            'link'        => $this->adminURL . Route::CONFIG . '/' . \CONF_KUNDEN,
                            'permissions' => Permissions::SETTINGS_CUSTOMERFORM_VIEW,
                            'section'     => \CONF_KUNDEN,
                        ],
                    ],
                    \__('Default elements') => [
                        \__('Shop logo')                  => (object)[
                            'link'        => $this->adminURL . Route::LOGO,
                            'permissions' => Permissions::DISPLAY_OWN_LOGO_VIEW,
                        ],
                        \__('Search')                     => (object)[
                            'link'           => $this->adminURL . Route::SEARCHCONFIG,
                            'permissions'    => Permissions::SETTINGS_ARTICLEOVERVIEW_VIEW,
                            'section'        => \CONF_ARTIKELUEBERSICHT,
                            'specialSetting' => true,
                        ],
                        \__('Price history')              => (object)[
                            'link'           => $this->adminURL . Route::PRICEHISTORY,
                            'permissions'    => Permissions::MODULE_PRICECHART_VIEW,
                            'section'        => \CONF_PREISVERLAUF,
                            'specialSetting' => true,
                        ],
                        \__('Question on item')           => (object)[
                            'link'                  => $this->adminURL . Route::CONFIG . '/' . \CONF_ARTIKELDETAILS .
                                '?group=configgroup_5_product_question',
                            'permissions'           => Permissions::SETTINGS_ARTICLEDETAILS_VIEW,
                            'excludeFromAccessView' => true,
                            'section'               => \CONF_ARTIKELDETAILS,
                            'group'                 => 'configgroup_5_product_question',
                        ],
                        \__('Availability notifications') => (object)[
                            'link'                  => $this->adminURL . Route::CONFIG . '/' . \CONF_ARTIKELDETAILS .
                                '?group=configgroup_5_product_available',
                            'permissions'           => Permissions::SETTINGS_ARTICLEDETAILS_VIEW,
                            'excludeFromAccessView' => true,
                            'section'               => \CONF_ARTIKELDETAILS,
                            'group'                 => 'configgroup_5_product_available',
                        ],
                        \__('Item badges')                => (object)[
                            'link'        => $this->adminURL . Route::SEARCHSPECIALOVERLAYS,
                            'permissions' => Permissions::DISPLAY_ARTICLEOVERLAYS_VIEW,
                        ],
                        \__('Footer / Boxes')             => (object)[
                            'link'        => $this->adminURL . Route::BOXES,
                            'permissions' => Permissions::BOXES_VIEW,
                        ],
                        \__('Selection wizard')           => (object)[
                            'link'           => $this->adminURL . Route::SELECTION_WIZARD,
                            'permissions'    => Permissions::EXTENSION_SELECTIONWIZARD_VIEW,
                            'section'        => \CONF_AUSWAHLASSISTENT,
                            'specialSetting' => true,
                            'settingsAnchor' => '#config',
                        ],
                        \__('Warehouse display')          => (object)[
                            'link'        => $this->adminURL . Route::WAREHOUSES,
                            'permissions' => Permissions::WAREHOUSE_VIEW,
                        ],
                        \__('Reviews')                    => (object)[
                            'link'           => $this->adminURL . Route::REVIEWS,
                            'permissions'    => Permissions::MODULE_VOTESYSTEM_VIEW,
                            'section'        => \CONF_BEWERTUNG,
                            'specialSetting' => true,
                            'settingsAnchor' => '#einstellungen',
                        ],
                        \__('Consent manager')            => (object)[
                            'link'           => $this->adminURL . Route::CONSENT,
                            'permissions'    => Permissions::CONSENT_MANAGER,
                            'section'        => \CONF_CONSENTMANAGER,
                            'specialSetting' => true,
                            'settingsAnchor' => '#config',
                        ],
                    ],
                    \__('Custom contents')  => [
                        \__('Pages')                  => (object)[
                            'link'        => $this->adminURL . Route::LINKS,
                            'permissions' => Permissions::CONTENT_PAGE_VIEW,
                        ],
                        \__('Terms / Withdrawal')     => (object)[
                            'link'        => $this->adminURL . Route::TAC,
                            'permissions' => Permissions::ORDER_AGB_WRB_VIEW,
                        ],
                        \__('Extended customer data') => (object)[
                            'link'           => $this->adminURL . Route::CUSTOMERFIELDS,
                            'permissions'    => Permissions::ORDER_CUSTOMERFIELDS_VIEW,
                            'section'        => \CONF_KUNDENFELD,
                            'specialSetting' => true,
                            'settingsAnchor' => '#config',
                        ],
                        \__('Check boxes')            => (object)[
                            'link'        => $this->adminURL . Route::CHECKBOX,
                            'permissions' => Permissions::CHECKBOXES_VIEW,
                        ],
                        \__('Banners')                => (object)[
                            'link'        => $this->adminURL . Route::BANNER,
                            'permissions' => Permissions::DISPLAY_BANNER_VIEW,
                        ],
                        \__('Sliders')                => (object)[
                            'link'        => $this->adminURL . Route::SLIDERS,
                            'permissions' => Permissions::SLIDER_VIEW,
                        ],
                    ],
                    \__('Settings')         => [
                        \__('Global')         => (object)[
                            'link'        => $this->adminURL . Route::CONFIG . '/' . \CONF_GLOBAL,
                            'permissions' => Permissions::SETTINGS_GLOBAL_VIEW,
                            'section'     => \CONF_GLOBAL,
                        ],
                        \__('Templates')      => (object)[
                            'link'        => $this->adminURL . Route::TEMPLATE,
                            'permissions' => Permissions::DISPLAY_TEMPLATE_VIEW,
                        ],
                        \__('Images')         => (object)[
                            'link'           => $this->adminURL . Route::IMAGES,
                            'permissions'    => Permissions::SETTINGS_IMAGES_VIEW,
                            'section'        => \CONF_BILDER,
                            'specialSetting' => true,
                        ],
                        \__('Watermark')      => (object)[
                            'link'        => $this->adminURL . Route::BRANDING,
                            'permissions' => Permissions::DISPLAY_BRANDING_VIEW,
                        ],
                        \__('Number formats') => (object)[
                            'link'        => $this->adminURL . Route::SEPARATOR,
                            'permissions' => Permissions::SETTINGS_SEPARATOR_VIEW,
                        ],
                    ]
                ]
            ],
            \__('Plug-ins')       => (object)[
                'icon'  => 'plugins',
                'items' => [
                    \__('Plug-in manager')     => (object)[
                        'link'        => $this->adminURL . Route::PLUGIN_MANAGER,
                        'permissions' => Permissions::PLUGIN_ADMIN_VIEW,
                    ],
                    \__('JTL-Extension Store') => (object)[
                        'link'                  => 'https://jtl-url.de/exs',
                        'target'                => '_blank',
                        'excludeFromAccessView' => true,
                        'permissions'           => Permissions::LICENSE_MANAGER
                    ],
                    \__('My purchases')        => (object)[
                        'link'        => $this->adminURL . Route::LICENSE,
                        'permissions' => Permissions::LICENSE_MANAGER,
                    ],
                    \__('Installed plug-ins')  => 'DYNAMIC_PLUGINS',
                ],
            ],
            \__('Administration') => (object)[
                'icon'  => 'administration',
                'items' => [
                    \__('Approvals')       => (object)[
                        'link'        => $this->adminURL . Route::ACTIVATE,
                        'permissions' => Permissions::UNLOCK_CENTRAL_VIEW,
                    ],
                    \__('Import')          => [
                        \__('Newsletters')  => (object)[
                            'link'        => $this->adminURL . Route::NEWSLETTER_IMPORT,
                            'permissions' => Permissions::IMPORT_NEWSLETTER_RECEIVER_VIEW,
                        ],
                        \__('Customers')    => (object)[
                            'link'        => $this->adminURL . Route::CUSTOMER_IMPORT,
                            'permissions' => Permissions::IMPORT_CUSTOMER_VIEW,
                        ],
                        \__('Postal codes') => (object)[
                            'link'        => $this->adminURL . Route::ZIP_IMPORT,
                            'permissions' => Permissions::PLZ_ORT_IMPORT_VIEW,
                        ],
                    ],
                    \__('Export')          => [
                        \__('Site map')       => (object)[
                            'link'           => $this->adminURL . Route::SITEMAP_EXPORT,
                            'permissions'    => Permissions::EXPORT_SITEMAP_VIEW,
                            'section'        => \CONF_SITEMAP,
                            'specialSetting' => true,
                            'settingsAnchor' => '#einstellungen',
                        ],
                        \__('RSS feed')       => (object)[
                            'link'           => $this->adminURL . Route::RSS,
                            'permissions'    => Permissions::EXPORT_RSSFEED_VIEW,
                            'section'        => \CONF_RSS,
                            'specialSetting' => true,
                        ],
                        \__('Other formats')  => (object)[
                            'link'        => $this->adminURL . Route::EXPORT,
                            'permissions' => Permissions::EXPORT_FORMATS_VIEW,
                        ],
                        \__('Export manager') => (object)[
                            'link'        => $this->adminURL . Route::EXPORT_QUEUE,
                            'permissions' => Permissions::EXPORT_SCHEDULE_VIEW,
                        ],
                    ],
                    \__('Payment methods') => (object)[
                        'link'        => $this->adminURL . Route::PAYMENT_METHODS,
                        'permissions' => Permissions::ORDER_PAYMENT_VIEW,
                    ],
                    \__('Shipments')       => [
                        \__('Shipping methods')     => (object)[
                            'link'        => $this->adminURL . Route::SHIPPING_METHODS,
                            'permissions' => Permissions::ORDER_SHIPMENT_VIEW,
                        ],
                        \__('Additional packaging') => (object)[
                            'link'        => $this->adminURL . Route::PACKAGINGS,
                            'permissions' => Permissions::ORDER_PACKAGE_VIEW,
                        ],
                        \__('Country manager')      => (object)[
                            'link'        => $this->adminURL . Route::COUNTRIES,
                            'permissions' => Permissions::COUNTRY_VIEW,
                        ],
                    ],
                    \__('Email')           => [
                        \__('Server')          => (object)[
                            'link'        => $this->adminURL . Route::CONFIG . '/' . \CONF_EMAILS,
                            'permissions' => Permissions::SETTINGS_EMAILS_VIEW,
                            'section'     => \CONF_EMAILS,
                        ],
                        \__('Email templates') => (object)[
                            'link'        => $this->adminURL . Route::EMAILTEMPLATES,
                            'permissions' => Permissions::CONTENT_EMAIL_TEMPLATE_VIEW,
                        ],
                        \__('Blacklist')       => (object)[
                            'link'           => $this->adminURL . Route::EMAILBLOCKLIST,
                            'permissions'    => Permissions::SETTINGS_EMAIL_BLACKLIST_VIEW,
                            'section'        => \CONF_EMAILBLACKLIST,
                            'specialSetting' => true,
                        ],
                        \__('Log')             => (object)[
                            'link'        => $this->adminURL . Route::EMAILHISTORY,
                            'permissions' => Permissions::EMAILHISTORY_VIEW,
                        ],
                    ],
                    \__('SEO')             => [
                        \__('Meta data')  => (object)[
                            'link'           => $this->adminURL . Route::META,
                            'permissions'    => Permissions::SETTINGS_GLOBAL_META_VIEW,
                            'section'        => \CONF_METAANGABEN,
                            'specialSetting' => true,
                            'settingsAnchor' => '#einstellungen',
                        ],
                        \__('Forwarding') => (object)[
                            'link'        => $this->adminURL . Route::REDIRECT,
                            'permissions' => Permissions::REDIRECT_VIEW,
                        ],
                        \__('Site map')   => (object)[
                            'link'        => $this->adminURL . Route::SITEMAP,
                            'permissions' => Permissions::SETTINGS_SITEMAP_VIEW,
                        ],
                        \__('SEO path')   => (object)[
                            'link'           => $this->adminURL . Route::SEARCHSPECIAL,
                            'permissions'    => Permissions::SETTINGS_SPECIALPRODUCTS_VIEW,
                            'section'        => \CONF_SUCHSPECIAL,
                            'specialSetting' => true,
                            'settingsAnchor' => '#einstellungen',
                        ],
                    ],
                    \__('Languages')       => (object)[
                        'link'        => $this->adminURL . Route::LANGUAGE,
                        'permissions' => Permissions::LANGUAGE_VIEW
                    ],
                    \__('Accounts')        => [
                        \__('Users')                    => (object)[
                            'link'        => $this->adminURL . Route::USERS,
                            'permissions' => Permissions::ACCOUNT_VIEW,
                        ],
                        \__('JTL-Wawi synchronisation') => (object)[
                            'link'        => $this->adminURL . Route::SYNC,
                            'permissions' => Permissions::WAWI_SYNC_VIEW,
                        ],
                    ],
                    \__('Troubleshooting') => [
                        \__('System diagnostics') => (object)[
                            'link'        => $this->adminURL . Route::STATUS,
                            'permissions' => Permissions::DIAGNOSTIC_VIEW,
                        ],
                        \__('Log')                => (object)[
                            'link'        => $this->adminURL . Route::SYSTEMLOG,
                            'permissions' => Permissions::SYSTEMLOG_VIEW,
                        ],
                        \__('Item images')        => (object)[
                            'link'        => $this->adminURL . Route::IMAGE_MANAGEMENT,
                            'permissions' => Permissions::DISPLAY_IMAGES_VIEW,
                        ],
                        \__('Profiler')           => (object)[
                            'link'        => $this->adminURL . Route::PROFILER,
                            'permissions' => Permissions::PROFILER_VIEW,
                        ],
                        \__('Service report')     => (object)[
                            'link'        => $this->adminURL . Route::REPORT,
                            'permissions' => Permissions::REPORT_VIEW,
                        ],

                    ],
                    \__('System')          => [
                        \__('Cache')          => (object)[
                            'link'           => $this->adminURL . Route::CACHE,
                            'permissions'    => Permissions::OBJECTCACHE_VIEW,
                            'section'        => \CONF_CACHING,
                            'specialSetting' => true,
                            'settingsAnchor' => '#settings',
                        ],
                        \__('Cron')           => (object)[
                            'link'           => $this->adminURL . Route::CRON,
                            'permissions'    => Permissions::CRON_VIEW,
                            'section'        => \CONF_CRON,
                            'specialSetting' => true,
                            'settingsAnchor' => '#config',
                        ],
                        \__('Filesystem')     => (object)[
                            'link'           => $this->adminURL . Route::FILESYSTEM,
                            'permissions'    => Permissions::FILESYSTEM_VIEW,
                            'section'        => \CONF_FS,
                            'specialSetting' => true,
                        ],
                        \__('Update')         => (object)[
                            'link'        => $this->adminURL . Route::DBUPDATER,
                            'permissions' => Permissions::SHOP_UPDATE_VIEW,
                        ],
                        \__('System upgrade') => (object)[
                            'link'        => $this->adminURL . Route::UPGRADE,
                            'permissions' => Permissions::UPGRADE,
                            'disabled'    => !\SHOW_UPGRADER
                        ],
                        \__('Reset')          => (object)[
                            'link'        => $this->adminURL . Route::RESET,
                            'permissions' => Permissions::RESET_SHOP_VIEW,
                        ],
                        \__('Set up')         => (object)[
                            'link'        => $this->adminURL . Route::WIZARD,
                            'permissions' => Permissions::WIZARD_VIEW,
                        ],
                        \__('API Keys')       => (object)[
                            'link'        => $this->adminURL . Route::API_KEY,
                            'permissions' => Permissions::API_KEYS_VIEW,
                        ],
                    ],
                ]
            ],
        ];
    }
}
