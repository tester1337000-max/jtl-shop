<?php

declare(strict_types=1);

namespace JTL\Update;

use Exception;
use Ifsnop\Mysqldump\Mysqldump;
use JTL\DB\DbInterface;
use JTL\Minify\MinifyService;
use JTL\Shop;
use JTL\Smarty\ContextType;
use JTL\Smarty\JTLSmarty;
use JTL\Template\Config;
use JTL\Template\XMLReader;
use JTLShop\SemVer\Version;
use JTLShop\SemVer\VersionCollection;
use PDOException;
use stdClass;

use function Functional\first;

/**
 * Class Updater
 * @package JTL\Update
 */
class Updater
{
    protected static bool $isVerified = false;

    public function __construct(protected DbInterface $db)
    {
        $this->verify();
    }

    /**
     * Check database integrity
     *
     * @throws Exception
     */
    public function verify(): void
    {
        if (static::$isVerified === true) {
            return;
        }
        MigrationHelper::verifyIntegrity();
        $dbVersion      = $this->getCurrentDatabaseVersion();
        $dbVersionShort = (int)\sprintf('%d%02d', $dbVersion->getMajor(), $dbVersion->getMinor());
        if ($dbVersionShort < 404) {
            $this->db->query('ALTER TABLE `tversion` CHANGE `nTyp` `nTyp` INT(4) UNSIGNED NOT NULL');
        }

        static::$isVerified = true;
    }

    /**
     * @throws Exception
     */
    public function hasPendingUpdates(bool $force = false): bool
    {
        static $pending = null;
        if ($force || $pending === null) {
            $fileVersion = $this->getCurrentFileVersion();
            $dbVersion   = $this->getCurrentDatabaseVersion();
            if (Version::parse($fileVersion)->greaterThan($dbVersion)) {
                return true;
            }
            $pending = \count((new MigrationManager($this->db))->getPendingMigrations($force)) > 0;
        }

        return $pending;
    }

    /**
     * @param array<string, bool|string> $config
     * @throws Exception
     */
    public function createSqlDump(string $file, bool $compress = true, array $config = []): void
    {
        if ($compress && \pathinfo($file, \PATHINFO_EXTENSION) !== 'gz') {
            $file .= '.gz';
        }
        if (\file_exists($file)) {
            @\unlink($file);
        }
        $config        = \array_merge(
            [
                'skip-comments'  => true,
                'skip-dump-date' => true,
                'compress'       => $compress === true
                    ? Mysqldump::GZIP
                    : Mysqldump::NONE
            ],
            $config
        );
        $connectionStr = \sprintf('mysql:host=%s;dbname=%s', \DB_HOST, \DB_NAME);
        (new Mysqldump($connectionStr, \DB_USER, \DB_PASS, $config))->start($file);
    }

    public function createSqlDumpFile(bool $compress = true): string
    {
        $random = Shop::Container()->getCryptoService()->randomString(12);
        $file   = \PFAD_ROOT . \PFAD_EXPORT_BACKUP . \date('YmdHis') . '_sql_' . $random . '_backup.sql';
        if ($compress) {
            $file .= '.gz';
        }

        return $file;
    }

    /**
     * @throws Exception
     */
    public function getVersion(): stdClass
    {
        $v = $this->db->getSingleObject('SELECT * FROM tversion');
        if ($v === null) {
            throw new Exception('Unable to identify application version');
        }

        return $v;
    }

    public function getCurrentFileVersion(): string
    {
        return \APPLICATION_VERSION;
    }

    public function getCurrentDatabaseVersion(): Version
    {
        $version = $this->getVersion()->nVersion;

        if ($version === '5' || $version === 5) {
            $version = '5.0.0';
        }

        return Version::parse($version);
    }

    public function getTargetVersion(Version $version): Version
    {
        $versionCollection = new VersionCollection();
        foreach ($this->getAvailableUpdates() as $availableUpdate) {
            /** @var Version $referenceVersion */
            $referenceVersion = $availableUpdate->referenceVersion;
            if (
                $availableUpdate->isPublic === 0
                && $referenceVersion->equals($this->getCurrentFileVersion()) === false
            ) {
                continue;
            }
            $versionCollection->append($availableUpdate->reference);
        }

        $targetVersion = $version->smallerThan(Version::parse($this->getCurrentFileVersion()))
            ? $versionCollection->getNextVersion($version)
            : $version;
        if (\is_string($targetVersion)) {
            $targetVersion = Version::parse($targetVersion);
        }
        // if target version is greater than file version: set file version as target version to avoid
        // mistakes with missing versions in the version list from the API (fallback)
        if ($targetVersion?->greaterThan($this->getCurrentFileVersion()) ?? false) {
            $targetVersion = Version::parse($this->getCurrentFileVersion());
        }

        return $targetVersion ?? Version::parse(\APPLICATION_VERSION);
    }

    /**
     * @deprecated since 5.6.0
     */
    public function getPreviousVersion(int $version): int
    {
        \trigger_error(__METHOD__ . ' is deprecated.', \E_USER_DEPRECATED);
        $majors = [300 => 219, 400 => 320];
        if (\array_key_exists($version, $majors)) {
            $previousVersion = $majors[$version];
        } else {
            $previousVersion = --$version;
        }

        return $previousVersion;
    }

    protected function getUpdateDir(int $targetVersion): string
    {
        return \sprintf('%s%d', \PFAD_ROOT . \PFAD_UPDATE, $targetVersion);
    }

    protected function getSqlUpdatePath(int $targetVersion): string
    {
        return \sprintf('%s/update1.sql', $this->getUpdateDir($targetVersion));
    }

    /**
     * @param Version $targetVersion
     * @return string[]
     * @throws Exception
     */
    protected function getSqlUpdates(Version $targetVersion): array
    {
        $sqlFilePathVersion = \sprintf('%d%02d', $targetVersion->getMajor(), $targetVersion->getMinor());
        $sqlFile            = $this->getSqlUpdatePath((int)$sqlFilePathVersion);
        if (!\file_exists($sqlFile)) {
            throw new Exception('SQL file in path "' . $sqlFile . '" not found');
        }
        /** @var string[] $lines */
        $lines = \file($sqlFile) ?: [];
        foreach ($lines as $i => $line) {
            $line = \trim($line);
            if (\str_starts_with($line, '--') || \str_starts_with($line, '#')) {
                unset($lines[$i]);
            }
        }

        return $lines;
    }

    /**
     * @throws Exception
     */
    public function update(): IMigration|Version
    {
        return $this->hasPendingUpdates()
            ? $this->updateToNextVersion()
            : Version::parse(\APPLICATION_VERSION);
    }

    public function finalize(): void
    {
        $this->updateTemplateConfig();
        $smarty = new JTLSmarty(true, ContextType::FRONTEND);
        $smarty->clearCompiledTemplate();
        Shop::Container()->getCache()->flushAll();
        $ms = new MinifyService();
        $ms->flushCache();
        Shop::Container()->getGetText()->flushCache();
        $this->db->query('UPDATE tglobals SET dLetzteAenderung = NOW()');
    }

    protected function updateTemplateConfig(): void
    {
        $parentFolder = null;
        $reader       = new XMLReader();
        $current      = Shop::Container()->getTemplateService()->getActiveTemplate();
        $tplXML       = $reader->getXML($current->getPaths()->getBaseDirName());
        if ($tplXML !== null && !empty($tplXML->Parent)) {
            $parentFolder = (string)$tplXML->Parent;
        }
        $config    = new Config($current->getPaths()->getBaseDirName(), $this->db);
        $oldConfig = $config->loadConfigFromDB();
        $updates   = 0;
        foreach ($config->getConfigXML($reader, $parentFolder) as $conf) {
            foreach ($conf->settings as $setting) {
                if ($setting->cType === 'upload') {
                    continue;
                }
                if (isset($oldConfig[$setting->section][$setting->key])) {
                    // not a new setting - no need to update
                    continue;
                }
                $value = $setting->value ?? null;
                if ($value === null) {
                    continue;
                }
                if (\is_array($value)) {
                    $value = first($value);
                }
                $config->updateConfigInDB($setting->section, $setting->key, $value);
                ++$updates;
            }
        }
        Shop::Container()->getLogService()->info(
            \sprintf(
                \__('%d config values were updated after database update.'),
                $updates
            )
        );
    }

    /**
     * @throws Exception
     */
    protected function updateToNextVersion(): IMigration|Version
    {
        $currentVersion = $this->getCurrentDatabaseVersion();
        $targetVersion  = $this->getTargetVersion($currentVersion);

        if ($targetVersion->smallerThan(Version::parse('4.03.0'))) {
            return $targetVersion <= $currentVersion
                ? $currentVersion
                : $this->updateBySqlFile($currentVersion, $targetVersion);
        }

        return $this->updateByMigration($targetVersion);
    }

    protected function updateBySqlFile(Version $currentVersion, Version $targetVersion): Version
    {
        $currentLine = 0;
        $sqls        = $this->getSqlUpdates($currentVersion);
        try {
            $this->db->beginTransaction();
            foreach ($sqls as $i => $sql) {
                $currentLine = $i;
                $this->db->query($sql);
            }
        } catch (PDOException $e) {
            /** @var array{0: string, 1: ?int, 2: ?string} $info */
            $info  = $e->errorInfo;
            $code  = (int)$info[1];
            $error = $this->db->escape($info[2]);
            if (!\in_array($code, [1062, 1060, 1267], true)) {
                $this->db->rollback();
                $errorCountForLine = 1;
                $version           = $this->getVersion();
                if ((int)$version->nZeileBis === $currentLine) {
                    $errorCountForLine = $version->nFehler + 1;
                }
                $this->db->queryPrepared(
                    'UPDATE tversion SET
                         nZeileVon = 1, 
                         nZeileBis = :rw, 
                         nFehler = :errcnt,
                         nTyp = :code, 
                         cFehlerSQL = :err, 
                         dAktualisiert = NOW(),
                         releaseType = :rtype',
                    [
                        'rw'     => $currentLine,
                        'errcnt' => $errorCountForLine,
                        'code'   => $code,
                        'err'    => $error,
                        'rtype'  => \RELEASE_TYPE
                    ]
                );
                throw $e;
            }
        }
        $this->setVersion($targetVersion);

        return $targetVersion;
    }

    /**
     * @throws Exception
     */
    protected function updateByMigration(Version $targetVersion): IMigration|Version
    {
        $manager           = new MigrationManager($this->db);
        $pendingMigrations = $manager->getPendingMigrations();
        if (\count($pendingMigrations) === 0) {
            $this->setVersion($targetVersion);

            return $targetVersion;
        }
        $id        = \reset($pendingMigrations);
        $migration = $manager->getMigrationById($id);

        $manager->executeMigration($migration);

        return $migration;
    }

    /**
     * @return array<int, stdClass>
     */
    private function getAvailableUpdates(): array
    {
        $availableUpdates = Shop::Container()->getJTLAPI()->getAvailableVersions(true) ?? [];
        foreach ($availableUpdates as $key => $availVersion) {
            try {
                $availVersion->referenceVersion = Version::parse($availVersion->reference);
            } catch (Exception) {
                unset($availableUpdates[$key]);
            }
        }
        \usort($availableUpdates, $this->sortVersions(...));

        return $availableUpdates;
    }

    private function sortVersions(stdClass $x, stdClass $y): int
    {
        /** @var Version $versionX */
        $versionX = $x->referenceVersion;
        /** @var Version $versionY */
        $versionY = $y->referenceVersion;
        if ($versionX->smallerThan($versionY)) {
            return -1;
        }
        if ($versionX->greaterThan($versionY)) {
            return 1;
        }

        return 0;
    }

    /**
     * @throws Exception
     */
    protected function executeMigrations(): void
    {
        (new MigrationManager($this->db))->migrate();
    }

    public function setVersion(Version $targetVersion): void
    {
        foreach ($this->db->getObjects('SHOW COLUMNS FROM `tversion`') as $column) {
            if ($column->Field !== 'nVersion') {
                continue;
            }
            if ($column->Type !== 'varchar(20)') {
                $newVersion = \sprintf('%d%02d', $targetVersion->getMajor(), $targetVersion->getMinor());
            } else {
                $newVersion = $targetVersion->getOriginalVersion();
            }
        }

        if (empty($newVersion)) {
            throw new Exception('New database version can\'t be set.');
        }
        $this->db->queryPrepared(
            "UPDATE tversion SET 
                nVersion = :ver, 
                nZeileVon = 1, 
                nZeileBis = 0, 
                nFehler = 0, 
                nTyp = 1, 
                cFehlerSQL = '',
                dAktualisiert = NOW(),
                releaseType = :type",
            ['ver' => $newVersion, 'type' => \RELEASE_TYPE]
        );
    }

    public function error(): ?stdClass
    {
        $version = $this->getVersion();

        return (int)$version->nFehler > 0
            ? (object)[
                'code'  => $version->nTyp,
                'error' => $version->cFehlerSQL,
                'sql'   => $version->nVersion < 402
                    ? $this->getErrorSqlByFile()
                    : null
            ]
            : null;
    }

    public function getErrorSqlByFile(): ?string
    {
        $version = $this->getVersion();
        $sqls    = $this->getSqlUpdates($version->nVersion);

        return ((int)$version->nFehler > 0 && \array_key_exists($version->nZeileBis, $sqls))
            ? \trim($sqls[$version->nZeileBis])
            : null;
    }

    /**
     * @return string[]
     */
    public function getUpdateDirs(): array
    {
        $directories = [];
        $dir         = \PFAD_ROOT . \PFAD_UPDATE;
        foreach (\scandir($dir, \SCANDIR_SORT_ASCENDING) ?: [] as $value) {
            if (
                \is_numeric($value)
                && (int)$value > 300
                && (int)$value < 500
                && \is_dir($dir . '/' . $value)
            ) {
                $directories[] = $value;
            }
        }

        return $directories;
    }

    public function hasMinUpdateVersion(): bool
    {
        return !Version::parse(\JTL_MIN_SHOP_UPDATE_VERSION)->greaterThan($this->getCurrentDatabaseVersion());
    }

    public function getMinUpdateVersionError(): string
    {
        return \sprintf(
            \__('errorMinShopVersionRequired'),
            \APPLICATION_VERSION,
            \JTL_MIN_SHOP_UPDATE_VERSION,
            \APPLICATION_VERSION,
            \__('dbupdaterURL')
        );
    }

    public function forceMaintenanceMode(): void
    {
        if (Shop::getSettingValue(\CONF_GLOBAL, 'wartungsmodus_aktiviert') !== 'Y') {
            $this->db->update('teinstellungen', 'cName', 'wartungsmodus_aktiviert', (object)['cWert' => 'Y']);
            Shop::Container()->getCache()->flushTags([\CACHING_GROUP_OPTION]);
            $_SESSION['maintenance_forced'] = true;
        }
    }

    public function disablePlugins(): int
    {
        return $this->db->getAffectedRows(
            'UPDATE tplugin
                SET nStatus = 1
                WHERE nStatus = 2'
        );
    }
}
