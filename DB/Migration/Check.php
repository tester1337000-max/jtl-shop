<?php

declare(strict_types=1);

namespace JTL\DB\Migration;

use JTL\DB\DbInterface;
use stdClass;

class Check
{
    public const MIGRATE_NONE      = 0x0000;
    public const MIGRATE_INNODB    = 0x0001;
    public const MIGRATE_UTF8      = 0x0002;
    public const MIGRATE_ROWFORMAT = 0x0004;
    public const MIGRATE_C_UTF8    = 0x0010;
    public const MIGRATE_C_TEXT    = 0x0020;
    public const MIGRATE_C_TINYINT = 0x0040;
    public const MIGRATE_C_LENGTH  = 0x0080;
    public const MIGRATE_TABLE     = self::MIGRATE_INNODB
    | self::MIGRATE_UTF8
    | self::MIGRATE_ROWFORMAT;
    public const MIGRATE_COLUMN    = self::MIGRATE_C_UTF8
    | self::MIGRATE_C_TEXT
    | self::MIGRATE_C_TINYINT
    | self::MIGRATE_C_LENGTH;
    public const ALL_MIGRATIONS    = [
        self::MIGRATE_INNODB,
        self::MIGRATE_UTF8,
        self::MIGRATE_C_TEXT,
        self::MIGRATE_C_UTF8,
        self::MIGRATE_C_TINYINT,
        self::MIGRATE_ROWFORMAT,
        self::MIGRATE_C_LENGTH,
    ];

    public function __construct(private readonly DbInterface $db)
    {
    }

    public function getNeededMigrationsForTable(stdClass $table): int
    {
        $result = self::MIGRATE_NONE;
        if ($table->ENGINE !== 'InnoDB') {
            $result |= self::MIGRATE_INNODB;
        }
        if ($table->ROW_FORMAT !== 'Dynamic') {
            $result |= self::MIGRATE_ROWFORMAT;
        }
        if ($table->TABLE_COLLATION !== 'utf8mb4_unicode_ci') {
            $result |= self::MIGRATE_UTF8;
        }
        if (isset($table->TEXT_FIELDS) && (int)$table->TEXT_FIELDS > 0) {
            $result |= self::MIGRATE_C_TEXT;
        }
        if (isset($table->TINY_FIELDS) && (int)$table->TINY_FIELDS > 0) {
            $result |= self::MIGRATE_C_TINYINT;
        }
        if (isset($table->FIELD_COLLATIONS) && (int)$table->FIELD_COLLATIONS > 0) {
            $result |= self::MIGRATE_C_UTF8;
        }
        if (isset($table->FIELD_SHORTLENGTH) && (int)$table->FIELD_SHORTLENGTH > 0) {
            $result |= self::MIGRATE_C_LENGTH;
        }

        return $result;
    }

    public function getFirstMigration(int $migrations): int
    {
        foreach (self::ALL_MIGRATIONS as $migration) {
            if (($migrations & $migration) === $migration) {
                return $migration;
            }
        }

        return 0;
    }

    /**
     * @return stdClass[]
     */
    public function getColumnsNeedingMigrationsForTable(string $tableName): array
    {
        return $this->db->getObjects(
            "SELECT `COLUMN_NAME`, `DATA_TYPE`, `COLUMN_TYPE`, `COLUMN_DEFAULT`, `IS_NULLABLE`, `EXTRA`
                , `CHARACTER_SET_NAME`, `COLLATION_NAME`
                , IF(`DATA_TYPE` = 'text', 1, 0) TEXT_FIELDS
                , IF(`DATA_TYPE` = 'tinyint' AND SUBSTRING(COLUMN_NAME, 1, 1) = 'k', 1, 0) TINY_FIELDS
                , IF((`CHARACTER_SET_NAME` IS NOT NULL AND `CHARACTER_SET_NAME` != 'utf8mb4')
                  OR (`COLLATION_NAME` IS NOT NULL AND `COLLATION_NAME` != 'utf8mb4_unicode_ci'), 1, 0) FIELD_COLLATIONS
                , IF(`COLUMN_NAME` = 'cIP' AND `CHARACTER_MAXIMUM_LENGTH` < 255, 1, 0) FIELD_SHORTLENGTH
                FROM information_schema.COLUMNS
                WHERE `TABLE_SCHEMA` = :schema
                    AND `TABLE_NAME` = :table
                    AND ((`CHARACTER_SET_NAME` IS NOT NULL AND `CHARACTER_SET_NAME` != 'utf8mb4')
                        OR (`COLLATION_NAME` IS NOT NULL AND `COLLATION_NAME` != 'utf8mb4_unicode_ci')
                        OR `DATA_TYPE` = 'text'
                        OR (`DATA_TYPE` = 'tinyint' AND SUBSTRING(`COLUMN_NAME`, 1, 1) = 'k')
                        OR (`COLUMN_NAME` = 'cIP' AND `CHARACTER_MAXIMUM_LENGTH` < 255)
                    )
                ORDER BY `ORDINAL_POSITION`",
            ['schema' => $this->db->getConfig()['database'], 'table' => $tableName]
        );
    }

    /**
     * @param string[] $excludeTables
     */
    private function getStructureSQL(
        bool $needingMigration = false,
        array $excludeTables = [],
        bool $justOne = false,
        bool $singleTable = false,
        bool $excludePlugins = true
    ): string {
        return "SELECT t.`TABLE_NAME`, t.`ENGINE`, t.`TABLE_COLLATION`, t.`TABLE_ROWS`, t.`TABLE_COMMENT`
                , t.`ROW_FORMAT`, t.`DATA_LENGTH` + t.`INDEX_LENGTH` AS DATA_SIZE
                , COUNT(IF(c.`DATA_TYPE` = 'text', 1, NULL)) TEXT_FIELDS
                , COUNT(IF(c.`DATA_TYPE` = 'tinyint'
                    AND SUBSTRING(c.`COLUMN_NAME`, 1, 1) = 'k', 1, NULL)) TINY_FIELDS
                , COUNT(
                    IF((`CHARACTER_SET_NAME` IS NOT NULL AND `CHARACTER_SET_NAME` != 'utf8mb4')
                    OR (`COLLATION_NAME` IS NOT NULL AND `COLLATION_NAME` != 'utf8mb4_unicode_ci'), 1, NULL)
                  ) FIELD_COLLATIONS
                , COUNT(IF(c.`COLUMN_NAME` = 'cIP'
                               AND c.`CHARACTER_MAXIMUM_LENGTH` < 255, 1, NULL)) FIELD_SHORTLENGTH
                FROM information_schema.TABLES t
                LEFT JOIN information_schema.COLUMNS c 
                    ON c.`TABLE_NAME` = t.`TABLE_NAME`
                    AND c.`TABLE_SCHEMA` = t.`TABLE_SCHEMA`
                WHERE t.`TABLE_SCHEMA` = :schema
                    " . ($singleTable ? 'AND t.`TABLE_NAME` = :table' : '') . '
                    ' . ($excludePlugins ? "AND t.`TABLE_NAME` NOT LIKE 'xplugin_%'" : '') . '
                    ' . (\count($excludeTables) > 0
                ? "AND t.`TABLE_NAME` NOT IN ('" . \implode("','", $excludeTables) . "')"
                : '') . '
                    ' . ($needingMigration ? "
                    AND (t.`ENGINE` != 'InnoDB' 
                           OR t.`TABLE_COLLATION` != 'utf8mb4_unicode_ci'
                           OR (`CHARACTER_SET_NAME` IS NOT NULL AND `CHARACTER_SET_NAME` != 'utf8mb4')
                           OR (`COLLATION_NAME` IS NOT NULL AND `COLLATION_NAME` != 'utf8mb4_unicode_ci')
                           OR c.`DATA_TYPE` = 'text'
                           OR (c.`DATA_TYPE` = 'tinyint' AND SUBSTRING(c.`COLUMN_NAME`, 1, 1) = 'k')
                           OR (c.`COLUMN_NAME` = 'cIP' AND c.`CHARACTER_MAXIMUM_LENGTH` < 255)
                    )" : '') . '
                GROUP BY t.`TABLE_NAME`, t.`ENGINE`, t.`TABLE_COLLATION`, t.`TABLE_COMMENT`
                ORDER BY t.`TABLE_NAME`' . ($justOne ? ' LIMIT 1' : '');
    }

    /**
     * @return stdClass[]
     */
    public function getTablesNeedingMigration(): array
    {
        return $this->db->getObjects(
            $this->getStructureSQL(true),
            ['schema' => $this->db->getConfig()['database']]
        );
    }

    /**
     * @param string[] $excludeTables
     * @return stdClass|null
     */
    public function getNextTableNeedingMigration(array $excludeTables = []): ?stdClass
    {
        return $this->db->getSingleObject(
            $this->getStructureSQL(true, $excludeTables, true),
            ['schema' => $this->db->getConfig()['database']]
        );
    }

    public function getTableData(string $table): ?stdClass
    {
        return $this->db->getSingleObject(
            $this->getStructureSQL(false, [], true, true, false),
            ['schema' => $this->db->getConfig()['database'], 'table' => $table]
        );
    }

    /**
     * @return stdClass[]
     */
    public function getTableStructure(): array
    {
        return $this->db->getObjects(
            $this->getStructureSQL(),
            ['schema' => $this->db->getConfig()['database']]
        );
    }
}
