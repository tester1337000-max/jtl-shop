<?php

declare(strict_types=1);

namespace JTL\DB\Migration;

use InvalidArgumentException;
use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Session\Backend;
use JTLShop\SemVer\Parser;
use stdClass;
use Systemcheck\Platform\DBServerInfo;

use function Functional\first;

/**
 * @phpstan-type DBStructoreObject object{TABLE_NAME: string, ENGINE: string, TABLE_COLLATION: string,
 *      TABLE_ROWS: string, TABLE_COMMENT: string, ROW_FORMAT: string, DATA_SIZE: string, TEXT_FIELDS: string,
 *      TINY_FIELDS: string, FIELD_COLLATIONS: string, FIELD_SHORTLENGTH: string,
 *      Columns: array<string, object{COLUMN_NAME: string, DATA_TYPE: string, COLUMN_TYPE: string,
 *      CHARACTER_SET_NAME: string|null, COLLATION_NAME: string|null}>, Migration: int, Locked: int}&stdClass
 */
readonly class Structure
{
    public function __construct(private DbInterface $db, private JTLCacheInterface $cache, private Info $info)
    {
    }

    /**
     * @return ($extended is true ? array<string, DBStructoreObject>: array<string, array<int, string>>)
     */
    public function getDBStruct(bool $extended = false, bool $clearCache = false): array
    {
        static $dbStruct = [
            'normal'   => null,
            'extended' => null,
        ];

        $dbLocked = null;
        $database = $this->db->getConfig()['database'];
        $cacheID  = $extended ? 'getDBStruct_extended' : 'getDBStruct_normal';
        if ($clearCache) {
            $this->cache->flushTags([\CACHING_GROUP_CORE . '_getDBStruct']);
            Backend::set('getDBStruct_extended', false);
            Backend::set('getDBStruct_normal', false);
            $dbStruct['extended'] = null;
            $dbStruct['normal']   = null;
        }

        if ($extended) {
            $dbStruct['extended'] ??= $this->cache->get($cacheID) ?: Backend::get($cacheID, false);

            $dbStructure =& $dbStruct['extended'];

            $dbLocked = $this->getLockedTables($database);
        } else {
            $dbStruct['normal'] = $dbStruct['normal'] ?? $this->cache->get($cacheID) ?: Backend::get($cacheID);

            $dbStructure =& $dbStruct['normal'];
        }

        if ($dbStructure === false) {
            $dbStructure = $this->build($extended, $dbLocked, $cacheID);
        } elseif ($extended) {
            foreach (\array_keys($dbStructure) as $table) {
                $dbStructure[$table]->Locked = $dbLocked[$table] ?? 0;
            }
        }

        return $dbStructure;
    }

    /**
     * @return array<string, string[]>
     */
    public function getDBFileStruct(): array
    {
        $version    = Parser::parse(\APPLICATION_VERSION);
        $versionStr = $version->getMajor() . '-' . $version->getMinor() . '-' . $version->getPatch();
        if ($version->hasPreRelease()) {
            $preRelease = $version->getPreRelease();
            $versionStr .= '-' . $preRelease->getGreek();
            if ($preRelease->getReleaseNumber() > 0) {
                $versionStr .= '-' . $preRelease->getReleaseNumber();
            }
        }

        $fileList = \PFAD_ROOT . \PFAD_ADMIN . \PFAD_INCLUDES . \PFAD_SHOPMD5 . 'dbstruct_' . $versionStr . '.json';
        if (!\file_exists($fileList)) {
            throw new InvalidArgumentException(\sprintf(\__('errorReadStructureFile'), $fileList));
        }
        try {
            $struct = \json_decode(\file_get_contents($fileList) ?: '', false, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $struct = null;
        }

        return \is_object($struct) ? \get_object_vars($struct) : [];
    }

    /**
     * @return object{errMsg: string, isEngineError: bool}&stdClass
     * @since 5.2.0
     */
    public function createDBStructError(string $msg, bool $engineError = false): stdClass
    {
        return (object)[
            'errMsg'        => $msg,
            'isEngineError' => $engineError,
        ];
    }

    /**
     * @param array<string, array<int, string>> $dbFileStruct
     * @param array<string, DBStructoreObject>  $dbStruct
     * @return array<string, object{errMsg: string, isEngineError: bool}&stdClass>
     * @since 5.2.0
     */
    public function compareDBStruct(array $dbFileStruct, array $dbStruct): array
    {
        $errors = [];
        foreach ($dbFileStruct as $table => $columns) {
            if (!\array_key_exists($table, $dbStruct)) {
                $errors[$table] = $this->createDBStructError(\__('errorNoTable'));
                continue;
            }
            $struct = $dbStruct[$table];
            if ($struct->Migration > Check::MIGRATE_NONE) {
                $errors[$table] = $this->createDBStructError(
                    $this->getStructErrorText($struct),
                    true
                );
                continue;
            }

            foreach ($columns as $column) {
                if (!\array_key_exists($column, $struct->Columns)) {
                    $errors[$table] = $this->createDBStructError(\__('errorRowMissing', $column, $table));
                    break;
                }
            }
        }

        return $errors;
    }

    public function getStructErrorText(stdClass $tableData): string
    {
        $check = new Check($this->db);
        if (($tableData->Migration & Check::MIGRATE_TABLE) > Check::MIGRATE_NONE) {
            $tableMigration = $check->getFirstMigration($tableData->Migration);

            return \__('errorMigrationTable_' . $tableMigration, $tableData->TABLE_NAME);
        }
        if (($tableData->Migration & Check::MIGRATE_COLUMN) > Check::MIGRATE_NONE) {
            $tableMigration = $check->getFirstMigration($tableData->Migration);
            $column         = first($check->getColumnsNeedingMigrationsForTable($tableData->TABLE_NAME));

            return \__('errorMigrationTable_' . $tableMigration, $column->COLUMN_NAME);
        }

        return '';
    }

    /**
     * @param bool                    $extended
     * @param array<string, int>|null $dbLocked
     * @param string                  $cacheID
     * @return ($extended is true ? array<string, DBStructoreObject>: array<string, array<int, string>>)
     */
    public function build(bool $extended, ?array $dbLocked, string $cacheID): array
    {
        $database    = $this->db->getConfig()['database'];
        $check       = new Check($this->db);
        $dbStructure = [];
        $dbData      = $check->getTableStructure();
        /** @var DBStructoreObject $data */
        foreach ($dbData as $data) {
            /** @var string $table */
            $table   = $data->TABLE_NAME;
            $columns = $this->db->getObjects(
                'SELECT `COLUMN_NAME`, `DATA_TYPE`, `COLUMN_TYPE`, `CHARACTER_SET_NAME`, `COLLATION_NAME`
                        FROM information_schema.COLUMNS
                        WHERE `TABLE_SCHEMA` = :schema
                            AND `TABLE_NAME` = :table
                        ORDER BY `ORDINAL_POSITION`',
                [
                    'schema' => $database,
                    'table'  => $table
                ]
            );
            if ($extended) {
                $data->Columns   = [];
                $data->Migration = Check::MIGRATE_NONE;
                if ($dbLocked === null) {
                    $data->Locked = \str_contains($data->TABLE_COMMENT, ':Migrating') ? 1 : 0;
                } else {
                    $data->Locked = $dbLocked[$table] ?? 0;
                }
                foreach ($columns as $column) {
                    $data->Columns[$column->COLUMN_NAME] = $column;
                }
                $data->Migration = $check->getNeededMigrationsForTable($data);

                $dbStructure[$table] = $data;
            } else {
                $dbStructure[$table] = \array_map(static fn(stdClass $column) => $column->COLUMN_NAME, $columns);
            }
        }
        if ($this->cache->isActive()) {
            $this->cache->set(
                $cacheID,
                $dbStructure,
                [\CACHING_GROUP_CORE, \CACHING_GROUP_CORE . '_getDBStruct']
            );
        } else {
            Backend::set($cacheID, $dbStructure);
        }

        return $dbStructure;
    }

    /**
     * @return array<string, int>|null
     */
    private function getLockedTables(string $database): ?array
    {
        $versionInfo = $this->info->getDBServerInfo();
        if ($versionInfo->isSupportedVersion() < DBServerInfo::SUPPORTED) {
            return null;
        }
        $dbLocked = [];
        $dbStatus = $this->db->getObjects(
            'SHOW OPEN TABLES
                WHERE `Database` LIKE :schema',
            ['schema' => $database]
        );
        foreach ($dbStatus as $item) {
            if ((int)$item->In_use > 0) {
                $dbLocked[$item->Table] = 1;
            }
        }

        return $dbLocked;
    }
}
