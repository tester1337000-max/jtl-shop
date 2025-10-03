<?php

declare(strict_types=1);

namespace JTL\Update;

use Exception;
use JTL\DB\DbInterface;
use JTL\IO\IOError;
use JTL\IO\IOFile;
use JTL\L10n\GetText;
use JTL\Plugin\Admin\Installation\MigrationManager as PluginMigrationManager;
use JTL\Plugin\PluginLoader;
use JTL\Router\Route;
use JTL\Shop;
use JTL\Smarty\ContextType;
use JTL\Smarty\JTLSmarty;
use JTLShop\SemVer\Version;
use SmartyException;

/**
 * Class UpdateIO
 * @package JTL\Update
 */
readonly class UpdateIO
{
    public function __construct(private DbInterface $db, GetText $getText)
    {
        $getText->loadAdminLocale('pages/dbupdater');
    }

    /**
     * @return array{result: string, currentVersion: Version, updatedVersion: Version, availableUpdate: bool}|IOError
     * @throws Exception
     */
    public function update(): IOError|array
    {
        $updater = new Updater($this->db);
        try {
            $isMigration     = false;
            $disabledPlugins = 0;
            $dbVersion       = $updater->getCurrentDatabaseVersion();
            if ($dbVersion->getMajor() === 4) {
                $disabledPlugins = $updater->disablePlugins();
                $updater->forceMaintenanceMode();
                if ($disabledPlugins > 0) {
                    $_SESSION['disabledPlugins'] = $disabledPlugins;
                }
            }
            $updateResult    = $updater->update();
            $availableUpdate = $updater->hasPendingUpdates();
            if ($updateResult instanceof IMigration) {
                $isMigration  = true;
                $updateResult = \sprintf('Migration: %s', $updateResult->getDescription());
            } elseif ($updateResult instanceof Version) {
                $updateResult = \sprintf('Version: %s', $updateResult->__toString());
            }
            if ($availableUpdate === false) {
                $updater->finalize();
            }

            return [
                'result'          => $updateResult,
                'isMigration'     => $isMigration,
                'currentVersion'  => $dbVersion,
                'updatedVersion'  => $dbVersion,
                'availableUpdate' => $availableUpdate,
                'action'          => 'update',
                'disabledPlugins' => $disabledPlugins
            ];
        } catch (Exception $e) {
            return new IOError($e->getMessage());
        }
    }

    /**
     * @return array{url: string, file: string, type: string}|IOError
     * @throws Exception
     */
    public function backup(): IOError|array
    {
        $updater = new Updater($this->db);
        try {
            $file = $updater->createSqlDumpFile();
            $updater->createSqlDump($file);
            $file   = \basename($file);
            $params = \http_build_query(['action' => 'download', 'file' => $file], '', '&');
            $url    = Shop::getAdminURL() . '/' . Route::DBUPDATER . '?' . $params;

            return [
                'url'  => $url,
                'file' => $file,
                'type' => 'backup'
            ];
        } catch (Exception $e) {
            return new IOError($e->getMessage());
        }
    }

    public function download(string $file): IOFile|IOError
    {
        if (!\preg_match('/^([\d_a-z]+).sql.gz$/', $file)) {
            return new IOError(\__('Invalid download request'));
        }
        $filePath = \PFAD_ROOT . \PFAD_EXPORT_BACKUP . $file;

        return \file_exists($filePath)
            ? new IOFile($filePath, 'application/x-gzip')
            : new IOError(\__('Download file does not exist'));
    }

    /**
     * @return array{tpl: string, type: string}
     * @throws SmartyException
     * @throws Exception
     */
    public function getStatus(?int $pluginID = null): array
    {
        $smarty                 = JTLSmarty::getInstance(false, ContextType::BACKEND);
        $updater                = new Updater($this->db);
        $template               = Shop::Container()->getTemplateService()->getActiveTemplate();
        $manager                = null;
        $currentFileVersion     = $updater->getCurrentFileVersion();
        $currentDatabaseVersion = $updater->getCurrentDatabaseVersion();
        $version                = $updater->getVersion();
        $updatesAvailable       = $updater->hasPendingUpdates();
        $updateError            = $updater->error();
        if (ADMIN_MIGRATION === true) {
            if ($pluginID !== null) {
                $loader           = new PluginLoader($this->db, Shop::Container()->getCache());
                $plugin           = $loader->init($pluginID);
                $manager          = new PluginMigrationManager(
                    $this->db,
                    $plugin->getPaths()->getBasePath() . \PFAD_PLUGIN_MIGRATIONS,
                    $plugin->getPluginID(),
                    $plugin->getMeta()->getSemVer()
                );
                $updatesAvailable = \count($manager->getPendingMigrations()) > 0;
                $smarty->assign(
                    'migrationURL',
                    Shop::getAdminURL() . '/' . Route::PLUGIN . '/' . $pluginID
                )->assign('pluginID', $pluginID);
            } else {
                $manager = new MigrationManager($this->db);
            }
        }

        $smarty->assign('updatesAvailable', $updatesAvailable)
            ->assign('currentFileVersion', $currentFileVersion)
            ->assign('currentDatabaseVersion', $currentDatabaseVersion)
            ->assign('manager', $manager)
            ->assign(
                'hasDifferentVersions',
                !Version::parse($currentDatabaseVersion)->equals(Version::parse($currentFileVersion))
            )
            ->assign('version', $version)
            ->assign('updateError', $updateError)
            ->assign('route', Route::DBUPDATER)
            ->assign('currentTemplateFileVersion', $template->getFileVersion() ?? '1.0.0')
            ->assign('currentTemplateDatabaseVersion', $template->getVersion());

        return [
            'tpl'  => $smarty->fetch('tpl_inc/dbupdater_status.tpl'),
            'type' => 'status_tpl'
        ];
    }

    /**
     * @return array{id: int, type: string, result: string, hasMore: bool, forceReload: bool}|IOError
     */
    public function executeMigration(int $id, ?string $dir = null, ?int $pluginID = null): IOError|array
    {
        try {
            $updater = new Updater($this->db);
            if (!$updater->hasMinUpdateVersion()) {
                throw new Exception($updater->getMinUpdateVersionError());
            }
            $hasAlready = $updater->hasPendingUpdates();
            if ($pluginID !== null) {
                $loader  = new PluginLoader($this->db, Shop::Container()->getCache());
                $plugin  = $loader->init($pluginID);
                $manager = new PluginMigrationManager(
                    $this->db,
                    $plugin->getPaths()->getBasePath() . \PFAD_PLUGIN_MIGRATIONS,
                    $plugin->getPluginID(),
                    $plugin->getMeta()->getSemVer()
                );
            } else {
                $manager = new MigrationManager($this->db);
            }
            if (\in_array($dir, [IMigration::UP, IMigration::DOWN], true)) {
                $manager->executeMigrationById($id, $dir);
            }

            $migration    = $manager->getMigrationById($id);
            $updateResult = \sprintf('Migration: %s', $migration->getDescription());
            $hasMore      = $updater->hasPendingUpdates(true);
            $result       = [
                'id'          => $id,
                'type'        => 'migration',
                'result'      => $updateResult,
                'hasMore'     => $hasMore,
                'forceReload' => $hasMore === false || ($hasMore !== $hasAlready),
            ];
        } catch (Exception $e) {
            $result = new IOError($e->getMessage());
        }

        return $result;
    }
}
