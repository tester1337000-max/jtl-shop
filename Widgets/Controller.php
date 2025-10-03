<?php

declare(strict_types=1);

namespace JTL\Widgets;

use JsonException;
use JTL\Backend\AdminAccount;
use JTL\Backend\Permissions;
use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Helpers\Request;
use JTL\IO\IOResponse;
use JTL\L10n\GetText;
use JTL\Plugin\Helper;
use JTL\Plugin\State;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use SmartyException;
use stdClass;

/**
 * Class Controller
 * @package JTL\Widgets
 */
class Controller
{
    public function __construct(
        private readonly DbInterface $db,
        private readonly JTLCacheInterface $cache,
        private readonly GetText $getText,
        private readonly JTLSmarty $smarty,
        private readonly AdminAccount $account
    ) {
    }

    /**
     * @return stdClass[]
     */
    public function getWidgets(bool $active = true, bool $getAll = false): array
    {
        if (!$getAll || !$this->account->permission(Permissions::DASHBOARD_VIEW)) {
            return [];
        }
        $loaderLegacy = Helper::getLoader(false, $this->db, $this->cache);
        $loaderExt    = Helper::getLoader(true, $this->db, $this->cache);
        $plugins      = [];

        $widgets = $this->db->getObjects(
            'SELECT tadminwidgets.*, tplugin.cPluginID, tplugin.bExtension
                FROM tadminwidgets
                LEFT JOIN tplugin 
                    ON tplugin.kPlugin = tadminwidgets.kPlugin
                WHERE bActive = :active
                    AND (tplugin.nStatus IS NULL OR tplugin.nStatus = :activated)
                ORDER BY eContainer ASC, nPos ASC',
            ['active' => (int)$active, 'activated' => State::ACTIVATED]
        );

        foreach ($widgets as $widget) {
            $widget->kWidget    = (int)$widget->kWidget;
            $widget->kPlugin    = (int)$widget->kPlugin;
            $widget->nPos       = (int)$widget->nPos;
            $widget->bExpanded  = (int)$widget->bExpanded;
            $widget->bActive    = (int)$widget->bActive;
            $widget->bExtension = (int)$widget->bExtension;
            $widget->plugin     = null;
            if ($widget->cPluginID !== null && \SAFE_MODE === false) {
                if (\array_key_exists($widget->cPluginID, $plugins)) {
                    $widget->plugin = $plugins[$widget->cPluginID];
                } else {
                    if ($widget->bExtension === 1) {
                        $widget->plugin = $loaderExt->init($widget->kPlugin);
                    } else {
                        $widget->plugin = $loaderLegacy->init($widget->kPlugin);
                    }

                    $plugins[$widget->cPluginID] = $widget->plugin;
                }

                if ($widget->bExtension) {
                    $this->getText->loadPluginLocale('widgets/' . $widget->cClass, $widget->plugin);
                }
            } else {
                $this->getText->loadAdminLocale('widgets/' . $widget->cClass);
            }
            $msgid  = $widget->cClass . '_title';
            $msgstr = \__($msgid);
            if ($msgid !== $msgstr) {
                $widget->cTitle = $msgstr;
            }
            $msgid  = $widget->cClass . '_desc';
            $msgstr = \__($msgid);
            if ($msgid !== $msgstr) {
                $widget->cDescription = $msgstr;
            }
        }
        if (!$active) {
            return $widgets;
        }

        return $this->getActivatedWidgets($widgets);
    }

    public function setWidgetPosition(int $id, string $container, int $pos): void
    {
        $upd             = new stdClass();
        $upd->eContainer = $container;
        $upd->nPos       = $pos;

        $current = $this->db->select('tadminwidgets', 'kWidget', $id);
        if ($current === null) {
            return;
        }
        if ($current->eContainer === $container) {
            if ($current->nPos < $pos) {
                $this->db->queryPrepared(
                    'UPDATE tadminwidgets
                    SET nPos = nPos - 1
                    WHERE eContainer = :currentContainer
                      AND nPos > :currentPos
                      AND nPos <= :newPos',
                    [
                        'currentPos'       => $current->nPos,
                        'newPos'           => $pos,
                        'currentContainer' => $current->eContainer
                    ]
                );
            } else {
                $this->db->queryPrepared(
                    'UPDATE tadminwidgets
                        SET nPos = nPos + 1
                        WHERE eContainer = :currentContainer
                          AND nPos < :currentPos
                          AND nPos >= :newPos',
                    [
                        'currentPos'       => $current->nPos,
                        'newPos'           => $pos,
                        'currentContainer' => $current->eContainer
                    ]
                );
            }
        } else {
            $this->db->queryPrepared(
                'UPDATE tadminwidgets
                    SET nPos = nPos - 1
                    WHERE eContainer = :currentContainer
                      AND nPos > :currentPos',
                [
                    'currentPos'       => $current->nPos,
                    'currentContainer' => $current->eContainer
                ]
            );
            $this->db->queryPrepared(
                'UPDATE tadminwidgets
                    SET nPos = nPos + 1
                    WHERE eContainer = :newContainer
                      AND nPos >= :newPos',
                [
                    'newPos'       => $pos,
                    'newContainer' => $container
                ]
            );
        }

        $this->db->update('tadminwidgets', 'kWidget', $id, $upd);
    }

    public function closeWidget(int $id): void
    {
        $this->db->update('tadminwidgets', 'kWidget', $id, (object)['bActive' => 0]);
    }

    public function addWidget(int $id): void
    {
        $this->db->update('tadminwidgets', 'kWidget', $id, (object)['bActive' => 1]);
    }

    public function expandWidget(int $id, int $expand): void
    {
        $this->db->update('tadminwidgets', 'kWidget', $id, (object)['bExpanded' => $expand]);
    }

    /**
     * @param string|array<mixed>|null $post
     * @throws SmartyException
     */
    public function getRemoteDataIO(
        string $url,
        string $dataName,
        string $tpl,
        string $wrapperID,
        string|array|null $post = null
    ): IOResponse {
        $this->getText->loadAdminLocale('widgets');
        $urlsToCache = ['oNews_arr', 'oMarketplace_arr', 'oMarketplaceUpdates_arr', 'oPatch_arr', 'oHelp_arr'];
        if (\in_array($dataName, $urlsToCache, true)) {
            $cacheID = \str_replace('/', '_', $dataName . '_' . $tpl . '_' . \md5($wrapperID . $url));
            /** @var string|false $remoteData */
            $remoteData = $this->cache->get($cacheID);
            if ($remoteData === false) {
                /** @var string $remoteData */
                $remoteData = Request::http_get_contents($url, 15, $post);
                $this->cache->set($cacheID, $remoteData, [\CACHING_GROUP_OBJECT], 3600);
            }
        } else {
            /** @var string $remoteData */
            $remoteData = Request::http_get_contents($url, 15, $post);
        }

        if (\str_starts_with($remoteData, '<?xml')) {
            $data = \simplexml_load_string($remoteData);
        } else {
            try {
                $data = \json_decode($remoteData, false, 512, \JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $data = null;
            }
        }
        $wrapper  = $this->smarty->assign($dataName, $data)->fetch('tpl_inc/' . $tpl);
        $response = new IOResponse();
        $response->assignDom($wrapperID, 'innerHTML', $wrapper);

        return $response;
    }

    /**
     * @throws SmartyException
     */
    public function getShopInfoIO(string $tpl, string $wrapperID): IOResponse
    {
        $this->getText->loadAdminLocale('widgets');
        $api           = Shop::Container()->getJTLAPI();
        $latestVersion = $api->getLatestVersion();
        $wrapper       = $this->smarty->assign('oSubscription', $api->getSubscription())
            ->assign('oVersion', $latestVersion)
            ->assign('strLatestVersion', $latestVersion->getOriginalVersion())
            ->assign('bUpdateAvailable', $api->hasNewerVersion())
            ->fetch('tpl_inc/' . $tpl);

        return (new IOResponse())->assignDom($wrapperID, 'innerHTML', $wrapper);
    }

    /**
     * @throws SmartyException
     */
    public function getAvailableWidgetsIO(): IOResponse
    {
        $wrapper = $this->smarty->assign('oAvailableWidget_arr', $this->getWidgets(false))
            ->fetch('tpl_inc/widget_selector.tpl');

        return (new IOResponse())->assignDom('available-widgets', 'innerHTML', $wrapper);
    }

    /**
     * @param stdClass[] $widgets
     * @return stdClass[]
     */
    private function getActivatedWidgets(array $widgets): array
    {
        foreach ($widgets as $key => $widget) {
            $widget->cContent = '';
            $className        = '\JTL\Widgets\\' . $widget->cClass;
            $classPath        = null;
            if ($widget->plugin !== null) {
                $hit = $widget->plugin->getWidgets()->getWidgetByID($widget->kWidget);
                if ($hit !== null) {
                    $className = $hit->className;
                    $classPath = $hit->classFile;
                    if (\file_exists($classPath)) {
                        require_once $classPath;
                    }
                }
            }
            /** @var class-string<AbstractWidget> $className */
            if (!\class_exists($className)) {
                continue;
            }
            $instance = new $className($this->smarty, $this->db, $widget->plugin);
            if (
                \in_array($instance->getPermission(), ['DASHBOARD_ALL', Permissions::DASHBOARD_VIEW, ''], true)
                || $this->account->permission($instance->getPermission())
            ) {
                $widget->cContent = $instance->getContent();
                $widget->hasBody  = $instance->hasBody;
            } else {
                unset($widgets[$key]);
            }
        }

        return $widgets;
    }
}
