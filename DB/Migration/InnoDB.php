<?php

declare(strict_types=1);

namespace JTL\DB\Migration;

use Exception;
use JTL\Backend\DirManager;
use JTL\DB\DbInterface;
use JTL\Exceptions\CircularReferenceException;
use JTL\Exceptions\ServiceNotFoundException;
use JTL\L10n\GetText;
use JTL\Shop;
use stdClass;
use Systemcheck\Platform\DBServerInfo;

class InnoDB
{
    public const IN_USE  = 'in_use';
    public const SUCCESS = 'success';
    public const FAILURE = 'failure';

    public function __construct(
        private readonly DbInterface $db,
        private readonly Info $info,
        private readonly Check $check,
        private readonly Structure $structure,
        GetText $getText
    ) {
        $getText->loadAdminLocale('pages/dbcheck');
    }

    /**
     * @param string[] $exclude
     * @since 5.2.0
     */
    public function doMigrateToInnoDBUTF8(
        string $status = 'start',
        string $tableName = '',
        int $step = 1,
        array $exclude = []
    ): stdClass {
        $versionInfo = $this->info->getDBServerInfo();
        $result      = new stdClass();
        $doSingle    = false;

        switch (\mb_convert_case($status, \MB_CASE_LOWER)) {
            case 'stop':
                $result->nextTable = '';
                $result->status    = 'all done';
                break;
            case 'start':
                $shopTables = \array_keys($this->structure->getDBFileStruct());
                $tableData  = $this->check->getNextTableNeedingMigration($exclude);
                if ($tableData !== null) {
                    if (!\in_array($tableData->TABLE_NAME, $shopTables, true)) {
                        $exclude[] = $tableData->TABLE_NAME;
                        $result    = $this->doMigrateToInnoDBUTF8('start', '', 1, $exclude);
                    } else {
                        $result->nextTable = $tableData->TABLE_NAME;
                        $result->nextStep  = 1;
                        $result->status    = 'migrate';
                    }
                } else {
                    $result = $this->doMigrateToInnoDBUTF8('stop');
                }
                break;
            case 'migrate_single':
                $doSingle = true;
            // no break
            case 'migrate':
                if (!empty($tableName) && $step === 1) {
                    // Migration Step 1...
                    $tableData = $this->check->getTableData($tableName);
                    if ($tableData === null) {
                        throw new Exception('Cannot get table data for ' . $tableName);
                    }
                    $migration = $this->check->getNeededMigrationsForTable($tableData);
                    if ($migration !== Check::MIGRATE_NONE && !\in_array($tableData->TABLE_NAME, $exclude, true)) {
                        if (!$this->info->isTableInUse($tableData)) {
                            if ($versionInfo->isSupportedVersion() < DBServerInfo::SUPPORTED) {
                                // If MySQL version is lower than 5.6 use alternative lock method
                                // and delete all fulltext indexes because these are not supported
                                $this->db->executeExQuery($this->info->addTableLockInfo($tableData));
                                $fulltextIndizes = $this->info->getFulltextIndizes($tableData->TABLE_NAME);
                                if ($fulltextIndizes) {
                                    foreach ($fulltextIndizes as $fulltextIndex) {
                                        $this->db->executeExQuery(
                                            'ALTER TABLE `' . $tableData->TABLE_NAME . '`
                                            DROP KEY `' . $fulltextIndex->INDEX_NAME . '`'
                                        );
                                    }
                                }
                            }
                            if (($migration & Check::MIGRATE_TABLE) !== 0) {
                                $fkSQLs = $this->info->getForeignKeyStatements($tableData->TABLE_NAME);
                                foreach ($fkSQLs->dropFK as $fkSQL) {
                                    $this->db->executeExQuery($fkSQL);
                                }
                                $migrate = $this->db->executeExQuery($this->sqlMoveToInnoDB($tableData));
                                foreach ($fkSQLs->createFK as $fkSQL) {
                                    $this->db->executeExQuery($fkSQL);
                                }
                            } else {
                                $migrate = true;
                            }
                            if ($migrate) {
                                $result->nextTable = $tableName;
                                $result->nextStep  = 2;
                                $result->status    = 'migrate';
                            } else {
                                $result->status = 'failure';
                            }
                            if ($versionInfo->isSupportedVersion() < DBServerInfo::SUPPORTED) {
                                $this->db->executeExQuery($this->info->clearTableLockInfo($tableData));
                            }
                        } else {
                            $result->status = 'in_use';
                        }
                    } else {
                        // Get next table for migration...
                        $exclude[] = $tableName;
                        $result    = $doSingle
                            ? $this->doMigrateToInnoDBUTF8('stop')
                            : $this->doMigrateToInnoDBUTF8('start', '', 1, $exclude);
                    }
                } elseif (!empty($tableName) && $step === 2) {
                    // Migration Step 2...
                    $tableData = $this->check->getTableData($tableName);
                    if ($tableData === null) {
                        throw new Exception('Cannot get table data for ' . $tableName);
                    }
                    if (!$this->info->isTableInUse($tableData)) {
                        $sql = $this->sqlConvertUTF8($tableData);
                        if (!empty($sql)) {
                            if ($this->db->executeExQuery($sql)) {
                                // Get next table for migration...
                                $result = $doSingle
                                    ? $this->doMigrateToInnoDBUTF8('stop')
                                    : $this->doMigrateToInnoDBUTF8('start', '', 1, $exclude);
                            } else {
                                $result->status = 'failure';
                            }
                        } else {
                            // Get next table for migration...
                            $result = $doSingle
                                ? $this->doMigrateToInnoDBUTF8('stop')
                                : $this->doMigrateToInnoDBUTF8('start', '', 1, $exclude);
                        }
                        $result->table = $this->check->getTableData($tableName);
                        if ($result->table === null) {
                            throw new Exception('Cannot get table data for ' . $tableName);
                        }
                        $result->table->Migration = $this->check->getNeededMigrationsForTable($result->table);
                        $result->table->Status    = $this->getStructErrorText($result->table);
                    } else {
                        $result->status = 'in_use';
                    }
                }

                break;
            case 'clear cache':
                // Objektcache leeren
                try {
                    $cache = Shop::Container()->getCache();
                    $cache->setJtlCacheConfig(
                        $this->db->selectAll(
                            'teinstellungen',
                            'kEinstellungenSektion',
                            \CONF_CACHING
                        )
                    );
                    $cache->flushAll();
                } catch (Exception $e) {
                    try {
                        Shop::Container()->getLogService()->error(\sprintf(\__('errorEmptyCache'), $e->getMessage()));
                    } catch (CircularReferenceException | ServiceNotFoundException) {
                    }
                }
                $callback = static function (array $pParameters): void {
                    if (\str_starts_with($pParameters['filename'], '.')) {
                        return;
                    }
                    if (!$pParameters['isdir']) {
                        @\unlink($pParameters['path'] . $pParameters['filename']);
                    } else {
                        @\rmdir($pParameters['path'] . $pParameters['filename']);
                    }
                };
                $dirMan   = new DirManager();
                try {
                    $templateDir = Shop::Container()->getTemplateService()->getActiveTemplate()->getDir();
                    $dirMan->getData(\PFAD_ROOT . \PFAD_COMPILEDIR . $templateDir, $callback);
                } catch (Exception) {
                }
                $dirMan->getData(\PFAD_ROOT . \PFAD_ADMIN . \PFAD_COMPILEDIR, $callback);
                // Reset Fulltext search if version is lower than 5.6
                if ($versionInfo->isSupportedVersion() < DBServerInfo::SUPPORTED) {
                    $this->db->update('teinstellungen', 'cName', 'suche_fulltext', (object)['cWert' => 'N']);
                }
                $result->nextTable = '';
                $result->status    = 'finished';
                break;
        }
        $result->exclude = \array_unique(\array_merge($result->exclude ?? [], $exclude));

        return $result;
    }

    public function getStructErrorText(stdClass $tableData): string
    {
        return $this->structure->getStructErrorText($tableData);
    }

    public function sqlMoveToInnoDB(stdClass $tableData): string
    {
        $serverInfo = $this->info->getDBServerInfo();
        if (!isset($tableData->Migration)) {
            $tableData->Migration = $this->check->getNeededMigrationsForTable($tableData);
        }
        if ($tableData->Migration === Check::MIGRATE_NONE) {
            return '';
        }

        $migrations = [];
        if (($tableData->Migration & Check::MIGRATE_INNODB) === Check::MIGRATE_INNODB) {
            $migrations[] = 'ENGINE=InnoDB';
        }
        if (($tableData->Migration & Check::MIGRATE_ROWFORMAT) === Check::MIGRATE_ROWFORMAT) {
            $migrations[] = 'ROW_FORMAT Dynamic';
        }
        if (($tableData->Migration & Check::MIGRATE_UTF8) === Check::MIGRATE_UTF8) {
            $migrations[] = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }
        $sql = 'ALTER TABLE `' . $tableData->TABLE_NAME . '` ' . \implode(' ', $migrations);

        return $serverInfo->isSupportedVersion() < DBServerInfo::SUPPORTED
            ? $sql
            : $sql . ', LOCK EXCLUSIVE';
    }

    public function sqlConvertUTF8(stdClass $tableData, string $lineBreak = ''): string
    {
        $serverInfo = $this->info->getDBServerInfo();
        $columns    = $this->check->getColumnsNeedingMigrationsForTable($tableData->TABLE_NAME);
        $sql        = '';
        if (\count($columns) === 0) {
            return $sql;
        }
        $sql = 'ALTER TABLE `' . $tableData->TABLE_NAME . '`' . $lineBreak;

        $columnChange = [];
        foreach ($columns as $col) {
            /* Workaround for quoted values in MariaDB >= 10.2.7 Fix: SHOP-2593 */
            if ($col->COLUMN_DEFAULT === 'NULL' || $col->COLUMN_DEFAULT === "'NULL'") {
                $col->COLUMN_DEFAULT = null;
            }
            if ($col->COLUMN_DEFAULT !== null) {
                $col->COLUMN_DEFAULT = \trim((string)$col->COLUMN_DEFAULT, '\'');
            }

            $characterSet = '';
            if ((int)$col->FIELD_COLLATIONS > 0) {
                $characterSet = "CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'";
            }
            if ((int)$col->TEXT_FIELDS > 0) {
                $col->COLUMN_TYPE = 'MEDIUMTEXT';
            }
            if ((int)$col->TINY_FIELDS > 0) {
                $col->COLUMN_TYPE = 'INT UNSIGNED';
            }
            if ((int)$col->FIELD_SHORTLENGTH > 0) {
                $col->COLUMN_TYPE = $col->DATA_TYPE . '(255)';
            }

            $columnChange[] = '    CHANGE COLUMN `' . $col->COLUMN_NAME . '` `' . $col->COLUMN_NAME . '` '
                . $col->COLUMN_TYPE . ' ' . $characterSet
                . ($col->IS_NULLABLE === 'YES' ? ' NULL' : ' NOT NULL')
                . ($col->IS_NULLABLE === 'NO' && $col->COLUMN_DEFAULT === null ? '' : ' DEFAULT '
                    . ($col->COLUMN_DEFAULT === null ? 'NULL' : "'" . $col->COLUMN_DEFAULT . "'"))
                . (!empty($col->EXTRA) ? ' ' . $col->EXTRA : '');
        }

        $sql .= \implode(', ' . $lineBreak, $columnChange);

        return $serverInfo->isSupportedVersion() < DBServerInfo::SUPPORTED
            ? $sql
            : $sql . ', LOCK EXCLUSIVE';
    }

    /**
     * @return self::SUCCESS|self::FAILURE|self::IN_USE
     */
    public function migrateToInnoDButf8(string $tableName): string
    {
        $tableData = $this->check->getTableData($tableName);
        if ($tableData === null) {
            return self::FAILURE;
        }
        if ($this->info->isTableInUse($tableData)) {
            return self::IN_USE;
        }

        $migration = $this->check->getNeededMigrationsForTable($tableData);
        if ($migration === Check::MIGRATE_NONE) {
            return self::SUCCESS;
        }

        $res = true;
        if (($migration & Check::MIGRATE_TABLE) !== Check::MIGRATE_NONE) {
            $sql = $this->sqlMoveToInnoDB($tableData);
            if (!empty($sql)) {
                $fkSQLs = $this->info->getForeignKeyStatements($tableName);
                foreach ($fkSQLs->dropFK as $fkSQL) {
                    $this->db->executeExQuery($fkSQL);
                }
                $res = $this->db->executeExQuery($sql);
                foreach ($fkSQLs->createFK as $fkSQL) {
                    $this->db->executeExQuery($fkSQL);
                }
            }
        }
        if ($res && ($migration & Check::MIGRATE_COLUMN) !== Check::MIGRATE_NONE) {
            $sql = $this->sqlConvertUTF8($tableData);
            if (!empty($sql)) {
                $res = $this->db->executeExQuery($sql);
            }
        }

        return $res ? self::SUCCESS : self::FAILURE;
    }
}
