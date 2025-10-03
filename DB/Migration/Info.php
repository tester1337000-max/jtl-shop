<?php

declare(strict_types=1);

namespace JTL\DB\Migration;

use JTL\DB\DbInterface;
use stdClass;
use Systemcheck\Platform\DBServerInfo;

class Info
{
    private static ?DBServerInfo $dbServerInfo = null;

    public function __construct(private readonly DbInterface $db)
    {
    }

    public function getDBServerInfo(): DBServerInfo
    {
        return self::$dbServerInfo ?? (self::$dbServerInfo = new DBServerInfo($this->db->getPDO()));
    }

    /**
     * @return stdClass[]
     */
    public function getFulltextIndizes(?string $table = null): array
    {
        $params = ['schema' => $this->db->getConfig()['database']];
        $filter = "AND `INDEX_NAME` NOT IN ('idx_tartikel_fulltext', 'idx_tartikelsprache_fulltext')";

        if (!empty($table)) {
            $params['table'] = $table;
            $filter          = 'AND `TABLE_NAME` = :table';
        }

        return $this->db->getObjects(
            'SELECT DISTINCT `TABLE_NAME`, `INDEX_NAME`
                FROM information_schema.STATISTICS
                WHERE `TABLE_SCHEMA` = :schema
                    ' . $filter . "
                    AND `INDEX_TYPE` = 'FULLTEXT'",
            $params
        );
    }

    public function isTableInUse(?stdClass $tableData): bool
    {
        if ($tableData === null) {
            return false;
        }
        if ($this->getDBServerInfo()->isSupportedVersion() < DBServerInfo::SUPPORTED) {
            return \str_contains($tableData->TABLE_COMMENT ?? '', ':Migrating');
        }

        $tableStatus = $this->db->getSingleObject(
            'SHOW OPEN TABLES
                WHERE `Database` LIKE :schema
                    AND `Table` LIKE :table',
            ['schema' => $this->db->getConfig()['database'], 'table' => $tableData->TABLE_NAME,]
        );

        return $tableStatus !== null && (int)$tableStatus->In_use > 0;
    }

    /**
     * @return stdClass[]
     */
    public function getForeignKeyDefinitions(string $tableName, bool $single = false): array
    {
        return $this->db->getObjects(
            'SELECT rc.`CONSTRAINT_NAME`, rc.`TABLE_NAME`, rc.`UPDATE_RULE`, rc.`DELETE_RULE`,
                    rk.`COLUMN_NAME`, rk.`REFERENCED_TABLE_NAME`, rk.`REFERENCED_COLUMN_NAME`
                FROM information_schema.REFERENTIAL_CONSTRAINTS rc
                INNER JOIN information_schema.KEY_COLUMN_USAGE rk
                    ON rk.`CONSTRAINT_SCHEMA` = rc.`CONSTRAINT_SCHEMA`
                        AND rk.`CONSTRAINT_NAME` = rc.`CONSTRAINT_NAME`
                WHERE rc.`CONSTRAINT_SCHEMA` = :schema
                    '
            . ($single
                ? 'AND :table = rc.`REFERENCED_TABLE_NAME`'
                : 'AND :table IN (rc.`TABLE_NAME`, rc.`REFERENCED_TABLE_NAME`)'),
            ['schema' => $this->db->getConfig()['database'], 'table' => $tableName]
        );
    }

    public function addTableLockInfo(stdClass $table): string
    {
        return $this->getDBServerInfo()->isSupportedVersion() < DBServerInfo::SUPPORTED
            ? 'ALTER TABLE `' . $table->TABLE_NAME . "` COMMENT = '" . $table->TABLE_COMMENT . ":Migrating'"
            : '';
    }

    public function clearTableLockInfo(stdClass $table): string
    {
        return $this->getDBServerInfo()->isSupportedVersion() < DBServerInfo::SUPPORTED
            ? 'ALTER TABLE `' . $table->TABLE_NAME . "` COMMENT = '" . $table->TABLE_COMMENT . "'"
            : '';
    }

    /**
     * @return object{dropFK: string[], createFK: string[]} - dropFK: Array with SQL to drop associated foreign keys,
     *                  createFK: Array with SQL to recreate them
     */
    public function getForeignKeyStatements(string $tableName, bool $single = false): object
    {
        $fkDefinitions = $this->getForeignKeyDefinitions($tableName, $single);
        $result        = (object)[
            'dropFK'   => [],
            'createFK' => [],
        ];

        if (\count($fkDefinitions) === 0) {
            return $result;
        }

        foreach ($fkDefinitions as $fkDefinition) {
            $result->dropFK[]   = 'ALTER TABLE `' . $fkDefinition->TABLE_NAME . '`'
                . ' DROP FOREIGN KEY `' . $fkDefinition->CONSTRAINT_NAME . '`';
            $result->createFK[] = 'ALTER TABLE `' . $fkDefinition->TABLE_NAME . '`'
                . ' ADD FOREIGN KEY `' . $fkDefinition->CONSTRAINT_NAME . '` (`' . $fkDefinition->COLUMN_NAME . '`)'
                . ' REFERENCES `' . $fkDefinition->REFERENCED_TABLE_NAME . '`'
                . '(`' . $fkDefinition->REFERENCED_COLUMN_NAME . '`)'
                . ' ON DELETE ' . $fkDefinition->DELETE_RULE
                . ' ON UPDATE ' . $fkDefinition->UPDATE_RULE;
        }

        return $result;
    }
}
