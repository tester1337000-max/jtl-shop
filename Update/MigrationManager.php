<?php

declare(strict_types=1);

namespace JTL\Update;

use DateTime;
use Exception;
use Gettext\Translator;
use Gettext\TranslatorFunctions;
use InvalidArgumentException;
use JTL\DB\DbInterface;
use JTLShop\SemVer\Version;
use PDOException;
use stdClass;

/**
 * Class MigrationManager
 * @package JTL\Update
 */
class MigrationManager
{
    /**
     * @var IMigration[]
     */
    protected static array $migrations = [];

    /**
     * @var array<int, DateTime>|null
     */
    protected ?array $executedMigrations = null;

    public function __construct(protected DbInterface $db)
    {
    }

    /**
     * Migrate the specified identifier.
     * @return IMigration[]
     * @throws Exception
     */
    public function migrate(?int $identifier = null): array
    {
        $migrations         = $this->getMigrations();
        $executedMigrations = $this->getExecutedMigrations();
        $currentId          = $this->getCurrentId();
        if (empty($executedMigrations) && empty($migrations)) {
            return [];
        }
        if ($identifier === null) {
            $identifier = \max(\array_merge($executedMigrations, \array_keys($migrations)));
        }
        $direction  = $identifier > $currentId ? IMigration::UP : IMigration::DOWN;
        $executed   = [];
        $translator = new Translator();
        TranslatorFunctions::register($translator);
        try {
            if ($direction === IMigration::DOWN) {
                \krsort($migrations);
                foreach ($migrations as $migration) {
                    if ($migration->getId() <= $identifier) {
                        break;
                    }
                    if (\in_array($migration->getId(), $executedMigrations, true)) {
                        $executed[] = $migration;
                        $this->executeMigration($migration, IMigration::DOWN);
                    }
                }
            }
            \ksort($migrations);
            foreach ($migrations as $migration) {
                if ($migration->getId() > $identifier) {
                    break;
                }
                if (!\in_array($migration->getId(), $executedMigrations, true)) {
                    $executed[] = $migration;
                    $this->executeMigration($migration);
                }
            }
        } catch (PDOException $e) {
            [$code, , $message] = $e->errorInfo ?? [];
            $this->log($migration, $direction, $code, $message);
            throw $e;
        } catch (Exception $e) {
            $this->log($migration, $direction, 'JTL01', $e->getMessage());
            throw $e;
        }

        return $executed;
    }

    /**
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function getMigrationById(int|string $id): IMigration
    {
        $migrations = $this->getMigrations();
        if (!\array_key_exists($id, $migrations)) {
            throw new InvalidArgumentException(\sprintf('Migration "%s" not found', $id));
        }

        return $migrations[$id];
    }

    /**
     * @throws Exception
     */
    public function executeMigrationById(int|string $id, string $direction = IMigration::UP): void
    {
        $this->executeMigration($this->getMigrationById($id), $direction);
    }

    /**
     * @throws Exception
     */
    public function executeMigration(IMigration $migration, string $direction = IMigration::UP): void
    {
        // reset cached executed migrations
        $this->executedMigrations = null;
        $start                    = new DateTime('now');
        try {
            $this->db->beginTransaction();
            $migration->$direction();
            if ($this->db->getPDO()->inTransaction()) {
                // Transaction may be committed by DDL in migration
                $this->db->commit();
            }
            $this->migrated($migration, $direction, $start);
        } catch (Exception $e) {
            if ($this->db->getPDO()->inTransaction()) {
                $this->db->rollback();
            }
            throw new Exception(
                \sprintf('Error "%s" in migration %s', $e->getMessage(), $migration::class),
                (int)$e->getCode()
            );
        }
    }

    /**
     * @param IMigration[] $migrations
     */
    public function setMigrations(array $migrations): self
    {
        static::$migrations = $migrations;

        return $this;
    }

    /**
     * @throws Exception
     */
    public function hasMigrations(): bool
    {
        return \count($this->getMigrations()) > 0;
    }

    /**
     * @throws InvalidArgumentException
     * @throws Exception
     * @return IMigration[]
     */
    public function getMigrations(): array
    {
        if (\count(static::$migrations) > 0) {
            return static::$migrations;
        }
        $migrations = [];
        $executed   = $this->fetchExecutedMigrationsFromDB();
        $path       = MigrationHelper::getMigrationPath();
        foreach (\glob($path . '*.php') ?: [] as $filePath) {
            $baseName = \basename($filePath);
            if (!MigrationHelper::isValidMigrationFileName($baseName)) {
                continue;
            }
            $id   = MigrationHelper::getIdFromFileName($baseName);
            $info = MigrationHelper::getInfoFromFileName($baseName);
            /** @var class-string<IMigration> $class */
            $class = '\JTL\Migrations\Migration' . MigrationHelper::mapFileNameToClassName($baseName);
            $date  = $executed[(int)$id] ?? null;
            if (!\class_exists($class)) {
                throw new InvalidArgumentException(
                    \sprintf(
                        'Could not find class "%s" in file "%s"',
                        $class,
                        $filePath
                    )
                );
            }
            $migration = new $class($this->db, $info, $date);
            if (!\is_subclass_of($migration, IMigration::class)) {
                throw new InvalidArgumentException(
                    \sprintf(
                        'The class "%s" in file "%s" must implement IMigration interface',
                        $class,
                        $filePath
                    )
                );
            }
            $migrations[$id] = $migration;
        }
        \ksort($migrations);
        $this->setMigrations($migrations);

        return static::$migrations;
    }

    public function getCurrentId(): int
    {
        return $this->db->getSingleInt(
            'SELECT kMigration 
                FROM tmigration 
                ORDER BY kMigration DESC',
            'kMigration'
        );
    }

    /**
     * @return array<int, int>
     * @throws Exception
     */
    public function getExecutedMigrations(): array
    {
        return \array_keys($this->fetchExecutedMigrationsFromDB() ?? []);
    }

    /**
     * @param bool $force
     * @return array<int, int>
     * @throws Exception
     */
    public function getPendingMigrations(bool $force = false): array
    {
        static $pending = null;

        if ($force || $pending === null) {
            $executed   = $this->getExecutedMigrations();
            $migrations = \array_keys($this->getMigrations());
            $pending    = \array_udiff(
                $migrations,
                $executed,
                static fn($a, $b): int => \strcmp((string)$a, (string)$b)
            );
        }

        return $pending;
    }

    /**
     * @return array<int, DateTime>|null
     * @throws Exception
     */
    protected function fetchExecutedMigrationsFromDB(): ?array
    {
        if ($this->executedMigrations !== null) {
            return $this->executedMigrations;
        }
        $migrations = $this->db->getObjects(
            'SELECT * 
                FROM tmigration 
                ORDER BY kMigration ASC'
        );
        if (\count($migrations) === 0) {
            return null;
        }
        $this->executedMigrations = [];
        foreach ($migrations as $m) {
            $this->executedMigrations[(int)$m->kMigration] = new DateTime($m->dExecuted);
        }

        return $this->executedMigrations;
    }

    /**
     * @throws Exception
     */
    public function log(IMigration $migration, string $direction, int|string $state, string $message): void
    {
        $ins             = new stdClass();
        $ins->kMigration = $migration->getId();
        $ins->cDir       = $direction;
        $ins->cState     = $state;
        $ins->cLog       = $message;
        $ins->dCreated   = (new DateTime('now'))->format('Y-m-d H:i:s');
        $this->db->insert('tmigrationlog', $ins);
    }

    public function migrated(IMigration $migration, string $direction, DateTime $executed): self
    {
        if (\strcasecmp($direction, IMigration::UP) === 0) {
            $version = Version::parse(\APPLICATION_VERSION);
            $sql     = \sprintf(
                "INSERT INTO tmigration (kMigration, nVersion, dExecuted) VALUES ('%d', '%s', '%s');",
                $migration->getId(),
                \sprintf('%d%02d', $version->getMajor(), $version->getMinor()),
                $executed->format('Y-m-d H:i:s')
            );
        } else {
            $sql = \sprintf("DELETE FROM tmigration WHERE kMigration = '%d'", $migration->getId());
        }
        $this->db->query($sql);

        return $this;
    }
}
