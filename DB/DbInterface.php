<?php

declare(strict_types=1);

namespace JTL\DB;

use Illuminate\Support\Collection;
use PDOStatement;
use stdClass;

/**
 * Interface DbInterface
 * @package JTL\DB
 */
interface DbInterface
{
    /**
     * Database configuration
     *
     * @return array<string, string>
     */
    public function getConfig(): array;

    /**
     * avoid destructer races with object cache
     *
     * @return $this
     */
    public function reInit(): DbInterface;

    /**
     * close db connection
     *
     * @return bool
     */
    public function close(): bool;

    /**
     * check if connected
     *
     * @return bool
     */
    public function isConnected(): bool;

    /**
     * get server version information
     *
     * @return string
     */
    public function getServerInfo(): string;

    /**
     * get server stats
     *
     * @return string
     */
    public function getServerStats(): string;

    /**
     * @return \PDO
     */
    public function getPDO(): \PDO;

    /**
     * insert row into db
     *
     * @param string $tableName - table name
     * @param object $object - object to insert
     * @param bool   $echo - true -> print statement
     * @return int - 0 if fails, PrimaryKeyValue if successful
     */
    public function insertRow(string $tableName, object $object, bool $echo = false): int;

    /**
     * @param string     $tableName
     * @param stdClass[] $objects
     * @param bool       $upsert
     * @return int
     */
    public function insertBatch(string $tableName, array $objects, bool $upsert = false): int;

    /**
     * @param string $tableName
     * @param object $object
     * @param bool   $echo
     * @return int
     */
    public function insert(string $tableName, object $object, bool $echo = false): int;

    /**
     * update table row
     *
     * @param string                  $tableName - table name
     * @param string|string[]         $keyname - Name of Key which should be compared
     * @param int|string|array<mixed> $keyvalue - Value of Key which should be compared
     * @param object                  $object - object to update with
     * @param bool                    $echo - true -> print statement
     * @return int - -1 if fails, number of affected rows if successful
     */
    public function updateRow(
        string $tableName,
        array|string $keyname,
        mixed $keyvalue,
        object $object,
        bool $echo = false
    ): int;

    /**
     * @param string             $tableName
     * @param string|string[]    $keyname
     * @param string|int|mixed[] $keyvalue
     * @param object             $object
     * @param bool               $echo
     * @return int
     */
    public function update(
        string $tableName,
        array|string $keyname,
        mixed $keyvalue,
        object $object,
        bool $echo = false
    ): int;

    /**
     * @param string   $tableName
     * @param object   $object
     * @param string[] $excludeUpdate
     * @param bool     $echo
     * @return int - -1 if fails, 0 if update, PrimaryKeyValue if successful inserted
     */
    public function upsert(string $tableName, object $object, array $excludeUpdate = [], bool $echo = false): int;

    /**
     * selects all (*) values in a single row from a table - gives just one row back!
     *
     * @param string             $tableName - Tabellenname
     * @param string|string[]    $keyname - Name of Key which should be compared
     * @param string|int|mixed[] $keyvalue - Value of Key which should be compared
     * @param string|null        $keyname1 - Name of Key which should be compared
     * @param string|int|null    $keyvalue1 - Value of Key which should be compared
     * @param string|null        $keyname2 - Name of Key which should be compared
     * @param string|int|null    $keyvalue2 - Value of Key which should be compared
     * @param bool               $echo - true -> print statement
     * @param string             $select - the key to select
     * @return null|stdClass - null if fails, resultObject if successful
     */
    public function selectSingleRow(
        string $tableName,
        array|string $keyname,
        mixed $keyvalue,
        ?string $keyname1 = null,
        mixed $keyvalue1 = null,
        ?string $keyname2 = null,
        mixed $keyvalue2 = null,
        bool $echo = false,
        string $select = '*'
    ): ?stdClass;

    /**
     * @param string             $tableName
     * @param string|string[]    $keyname
     * @param string|int|mixed[] $keyvalue
     * @param string|null        $keyname1
     * @param string|int|null    $keyvalue1
     * @param string|null        $keyname2
     * @param string|int|null    $keyvalue2
     * @param bool               $echo
     * @param string             $select
     * @return stdClass|null
     */
    public function select(
        string $tableName,
        array|string $keyname,
        mixed $keyvalue,
        ?string $keyname1 = null,
        mixed $keyvalue1 = null,
        ?string $keyname2 = null,
        mixed $keyvalue2 = null,
        bool $echo = false,
        string $select = '*'
    ): ?stdClass;

    /**
     * @param string             $tableName
     * @param string|string[]    $keys
     * @param string|mixed[]|int $values
     * @param string             $select
     * @param string             $orderBy
     * @param int|string         $limit
     * @return stdClass[]
     * @throws \InvalidArgumentException
     */
    public function selectArray(
        string $tableName,
        array|string $keys,
        mixed $values,
        string $select = '*',
        string $orderBy = '',
        int|string $limit = ''
    ): array;

    /**
     * @param string                  $tableName
     * @param string|string[]         $keys
     * @param string|int|array<mixed> $values
     * @param string                  $select
     * @param string                  $orderBy
     * @param int|string              $limit
     * @return stdClass[]
     */
    public function selectAll(
        string $tableName,
        array|string $keys,
        mixed $values,
        string $select = '*',
        string $orderBy = '',
        int|string $limit = ''
    ): array;

    /**
     * executes query and returns misc data
     *
     * @param string        $stmt - Statement to be executed
     * @param ReturnType::* $return - what should be returned.
     * 1  - single fetched object
     * 2  - array of fetched objects
     * 3  - affected rows
     * 7  - last inserted id
     * 8  - fetched assoc array
     * 9  - array of fetched assoc arrays
     * 10 - result of querysingle
     * 11 - fetch both arrays
     * @param bool          $echo print current stmt
     * @param callable|null $fnInfo statistic callback
     * @return array<mixed>|stdClass[]|stdClass|PDOStatement|int|bool - 0 if fails, 1 if successful or LastInsertID
     * @throws \InvalidArgumentException
     */
    public function executeQuery(
        string $stmt,
        int $return = ReturnType::DEFAULT,
        bool $echo = false,
        ?callable $fnInfo = null
    ): mixed;

    /**
     * @param string        $stmt
     * @param ReturnType::* $return
     * @param bool          $echo
     * @return array<mixed>|stdClass[]|stdClass|PDOStatement|int|bool|null|Collection<int, stdClass>
     */
    public function query(string $stmt, int $return = ReturnType::DEFAULT, bool $echo = false): mixed;

    /**
     * executes query and returns misc data
     *
     * @param string               $stmt - Statement to be executed
     * @param array<string, mixed> $params - An array of values with as many elements as there
     * are bound parameters in the SQL statement being executed
     * @param ReturnType::*        $return - what should be returned.
     * 1  - single fetched object
     * 2  - array of fetched objects
     * 3  - affected rows
     * 7  - last inserted id
     * 8  - fetched assoc array
     * 9  - array of fetched assoc arrays
     * 10 - result of querysingle
     * 11 - fetch both arrays
     * @param bool                 $echo print current stmt
     * @param callable|null        $fnInfo statistic callback
     * @return array<mixed>|stdClass[]|stdClass|PDOStatement|int|bool - 0 if fails, 1 if successful or LastInsertID
     * @throws \InvalidArgumentException
     */
    public function executeQueryPrepared(
        string $stmt,
        array $params,
        int $return = ReturnType::DEFAULT,
        bool $echo = false,
        ?callable $fnInfo = null
    ): mixed;

    /**
     * @param string        $stmt
     * @param array<mixed>  $params
     * @param ReturnType::* $return
     * @param bool          $echo
     * @param callable|null $fnInfo
     * @return bool|int|object|array<mixed>|Collection
     */
    public function queryPrepared(
        string $stmt,
        array $params,
        int $return = ReturnType::DEFAULT,
        bool $echo = false,
        ?callable $fnInfo = null
    ): mixed;

    /**
     * @param string               $stmt
     * @param array<string, mixed> $params
     * @return array<int, array<mixed>>
     * @since 5.1.0
     */
    public function getArrays(string $stmt, array $params = []): array;

    /**
     * @param string               $stmt
     * @param string               $rowName
     * @param array<string, mixed> $params
     * @return int[]
     * @since 5.2.0
     */
    public function getInts(string $stmt, string $rowName, array $params = []): array;

    /**
     * @param string                   $stmt
     * @param array<string|int, mixed> $params
     * @return stdClass[]
     * @since 5.1.0
     */
    public function getObjects(string $stmt, array $params = []): array;

    /**
     * @param string               $stmt
     * @param array<string, mixed> $params
     * @return Collection<int, stdClass>
     * @since 5.1.0
     */
    public function getCollection(string $stmt, array $params = []): Collection;

    /**
     * @param string               $stmt
     * @param array<string, mixed> $params
     * @return stdClass|null
     * @since 5.1.0
     */
    public function getSingleObject(string $stmt, array $params = []): ?stdClass;

    /**
     * @param string               $stmt
     * @param array<string, mixed> $params
     * @return bool
     * @since 5.5.0
     */
    public function ddl(string $stmt, array $params = []): bool;

    /**
     * @param string               $stmt
     * @param string               $rowName
     * @param array<string, mixed> $params
     * @return int
     * @since 5.2.0
     */
    public function getSingleInt(string $stmt, string $rowName, array $params = []): int;

    /**
     * @param string               $stmt
     * @param array<string, mixed> $params
     * @return int
     * @since 5.4.0
     */
    public function getLastInsertedID(string $stmt, array $params = []): int;

    /**
     * @param string               $stmt
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     * @since 5.1.0
     */
    public function getSingleArray(string $stmt, array $params = []): ?array;

    /**
     * @param string               $stmt
     * @param array<string, mixed> $params
     * @return int
     * @since 5.1.0
     */
    public function getAffectedRows(string $stmt, array $params = []): int;

    /**
     * @param string               $stmt
     * @param array<string, mixed> $params
     * @return PDOStatement
     * @since 5.1.0
     */
    public function getPDOStatement(string $stmt, array $params = []): PDOStatement;

    /**
     * delete row from table
     *
     * @param string             $tableName - table name
     * @param string|string[]    $keyname - Name of Key which should be compared
     * @param string|int|mixed[] $keyvalue - Value of Key which should be compared
     * @param bool               $echo - true -> print statement
     * @return int - -1 if fails, #affectedRows if successful
     */
    public function deleteRow(
        string $tableName,
        array|string $keyname,
        mixed $keyvalue,
        bool $echo = false
    ): int;

    /**
     * @param string             $tableName
     * @param string|string[]    $keyname
     * @param string|int|mixed[] $keyvalue
     * @param bool               $echo
     * @return int
     */
    public function delete(
        string $tableName,
        array|string $keyname,
        mixed $keyvalue,
        bool $echo = false
    ): int;

    /**
     * executes a query and gives back the result
     *
     * @param string $stmt - Statement to be executed
     * @return int|PDOStatement
     */
    public function executeExQuery(string $stmt): int|PDOStatement;

    /**
     * Quotes a string with outer quotes for use in a query.
     *
     * @param mixed $value
     * @return string
     */
    public function quote(mixed $value): string;

    /**
     * Quotes a string for use in a query.
     *
     * @param mixed $value
     * @return string
     */
    public function escape(mixed $value): string;

    /**
     * @return mixed
     */
    public function getErrorCode(): mixed;

    /**
     * @return array{0: string, 1: ?int, 2: ?string}
     */
    public function getError(): array;

    /**
     * @return string
     */
    public function getErrorMessage(): string;

    /**
     * @return bool
     */
    public function beginTransaction(): bool;

    /**
     * @return bool
     */
    public function commit(): bool;

    /**
     * @return bool
     */
    public function rollback(): bool;

    /**
     * @param string              $query
     * @param array<mixed, mixed> $params
     * @return string
     */
    public function readableQuery(string $query, array $params): string;

    /**
     * @param string $table
     * @return bool
     */
    public function tableExists(string $table): bool;
}
