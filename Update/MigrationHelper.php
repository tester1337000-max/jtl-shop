<?php

declare(strict_types=1);

namespace JTL\Update;

use DateTime;
use Exception;
use JTL\Filesystem\LocalFilesystem;
use JTL\Shop;
use JTL\Smarty\CLISmarty;

/**
 * Class MigrationHelper
 * @package JTL\Update
 */
class MigrationHelper
{
    public const DATE_FORMAT = 'YmdHis';

    public const DATE_FORMAT_READABLE = 'Y-m-d H:i:s';

    public const MIGRATION_CLASS_NAME_PATTERN = '/^Migration(\d+)$/i';

    public const MIGRATION_FILE_NAME_PATTERN = '/^Migration(\d+).php$/i';

    public static function getMigrationPath(): string
    {
        return \PFAD_ROOT . \PFAD_INCLUDES . 'src/Migrations/';
    }

    /**
     * @return string[]
     */
    public static function getExistingMigrationClassNames(): array
    {
        $classNames = [];
        $path       = static::getMigrationPath();
        foreach (\glob($path . '*.php') ?: [] as $filePath) {
            if (\preg_match(static::MIGRATION_FILE_NAME_PATTERN, \basename($filePath))) {
                $classNames[] = static::mapFileNameToClassName(\basename($filePath));
            }
        }

        return $classNames;
    }

    public static function getIdFromFileName(string $fileName): ?string
    {
        $matches = [];

        return \preg_match(static::MIGRATION_FILE_NAME_PATTERN, \basename($fileName), $matches)
            ? $matches[1]
            : null;
    }

    /**
     * Get the info from a file name.
     */
    public static function getInfoFromFileName(string $fileName): ?string
    {
        return null;
    }

    /**
     * Returns names like 'Migration12345678901234'.
     */
    public static function mapFileNameToClassName(string $fileName): string
    {
        return static::getIdFromFileName($fileName) ?? '';
    }

    /**
     * Returns names like '12345678901234'.
     */
    public static function mapClassNameToId(string $className): ?int
    {
        $matches = [];
        if (\preg_match(static::MIGRATION_CLASS_NAME_PATTERN, $className, $matches)) {
            return (int)$matches[1];
        }

        return null;
    }

    public static function isValidMigrationFileName(string $fileName): bool
    {
        return (bool)\preg_match(static::MIGRATION_FILE_NAME_PATTERN, $fileName);
    }

    /**
     * Check database integrity
     */
    public static function verifyIntegrity(): void
    {
        if (
            Shop::Container()->getDB()->getSingleObject(
                "SELECT `table_name` 
                    FROM information_schema.tables 
                    WHERE `table_type` = 'base table'
                        AND `table_schema` = :sma
                        AND `table_name` = :tn",
                ['sma' => DB_NAME, 'tn' => 'tmigration']
            ) === null
        ) {
            Shop::Container()->getDB()->query(
                "CREATE TABLE IF NOT EXISTS tmigration 
                (
                    kMigration bigint(14) NOT NULL, 
                    nVersion int(3) NOT NULL, 
                    dExecuted datetime NOT NULL,
                    PRIMARY KEY (kMigration)
                ) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_unicode_ci'"
            );
            Shop::Container()->getDB()->query(
                "CREATE TABLE IF NOT EXISTS tmigrationlog 
                (
                    kMigrationlog int(10) NOT NULL AUTO_INCREMENT, 
                    kMigration bigint(20) NOT NULL, 
                    cDir enum('up','down') NOT NULL, 
                    cState varchar(6) NOT NULL, 
                    cLog text NOT NULL, 
                    dCreated datetime NOT NULL, 
                    PRIMARY KEY (kMigrationlog)
                ) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_unicode_ci'"
            );
        }
    }

    /**
     * @return \stdClass[]
     */
    public static function indexColumns(string $idxTable, string $idxName): array
    {
        return Shop::Container()->getDB()->getObjects(
            \sprintf('SHOW INDEXES FROM `%s` WHERE Key_name = :idxName', $idxTable),
            ['idxName' => $idxName]
        );
    }

    /**
     * @param string[] $idxColumns
     */
    public static function createIndex(
        string $idxTable,
        array $idxColumns,
        ?string $idxName = null,
        bool $idxUnique = false
    ): bool {
        if (empty($idxName)) {
            $idxName = \implode('_', $idxColumns) . '_' . ($idxUnique ? 'UQ' : 'IDX');
        }
        if (self::dropIndex($idxTable, $idxName)) {
            $ddl = 'CREATE' . ($idxUnique ? ' UNIQUE' : '')
                . ' INDEX `' . $idxName . '` ON `' . $idxTable . '` '
                . '(`' . \implode('`, `', $idxColumns) . '`)';

            return Shop::Container()->getDB()->ddl($ddl);
        }

        return false;
    }

    public static function dropIndex(string $idxTable, string $idxName): bool
    {
        if (\count(self::indexColumns($idxTable, $idxName)) === 0) {
            return true;
        }

        return Shop::Container()->getDB()->ddl(\sprintf('DROP INDEX `%s` ON `%s` ', $idxName, $idxTable));
    }

    /**
     * @throws Exception
     */
    public static function create(string $description, string $author): string
    {
        $datetime      = new DateTime('NOW');
        $timestamp     = $datetime->format('YmdHis');
        $filePath      = 'Migration' . $timestamp . '.php';
        $migrationPath = \PFAD_INCLUDES . 'src/Migrations/' . $filePath;

        $content = (new CLISmarty())->assign('description', $description)
            ->assign('author', $author)
            ->assign('created', $datetime->format(DateTime::RSS))
            ->assign('timestamp', $timestamp)
            ->fetch(\PFAD_ROOT . \PFAD_INCLUDES . 'src/Console/Command/Migration/Template/migration.class.tpl');
        $localFS = Shop::Container()->get(LocalFilesystem::class);
        $localFS->write($migrationPath, $content);

        return $migrationPath;
    }
}
