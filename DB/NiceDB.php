<?php

declare(strict_types=1);

namespace JTL\DB;

use Exception;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use JTL\Exceptions\InvalidEntityNameException;
use JTL\Shop;
use PDO;
use PDOException;
use PDOStatement;
use stdClass;

/**
 * Class NiceDB
 * @package JTL\DB
 */
class NiceDB implements DbInterface
{
    protected ?PDO $db = null;

    protected bool $isConnected = false;

    public bool $logErrors = false;

    private bool $debug = false;

    /**
     * debug level, 0 no debug, 1 normal, 2 verbose, 3 very verbose with backtrace
     */
    private int $debugLevel = 0;

    private PDO $pdo;

    public string $state = 'instanciated';

    /**
     * @var array<string, string>
     */
    private array $config;

    private int $transactionCount = 0;

    private QueryAnalyzer $queryAnalyzer;

    public function __construct(
        string $host,
        string $user,
        #[\SensitiveParameter] string $pass,
        string $db,
        bool $forceDebug = false
    ) {
        $dsn          = 'mysql:dbname=' . $db;
        $this->config = [
            'driver'   => 'mysql',
            'host'     => $host,
            'database' => $db,
            'username' => $user,
            'password' => $pass,
            'charset'  => \DB_CHARSET,
        ];
        if (\defined('DB_SOCKET')) {
            $dsn .= ';unix_socket=' . \DB_SOCKET;
        } else {
            $dsn .= ';host=' . $host;
        }
        $this->pdo = new PDO($dsn, $user, $pass, $this->getOptions());
        if (\DB_DEFAULT_SQL_MODE !== true) {
            $this->pdo->exec("SET SQL_MODE=''");
        }
        if (\DB_STARTUP_SQL !== '') {
            foreach (\explode(';', \DB_STARTUP_SQL) as $sql) {
                if (!empty($sql)) {
                    $this->pdo->exec($sql);
                }
            }
        }
        $this->initDebugging($forceDebug);
        $this->queryAnalyzer = new QueryAnalyzer($this->pdo, $this->debug, $this->debugLevel);
        $this->isConnected   = true;
    }

    /**
     * @return array<int, string|bool>
     */
    private function getOptions(): array
    {
        $options = [];
        if (\defined('DB_SSL_KEY') && \defined('DB_SSL_CERT') && \defined('DB_SSL_CA')) {
            $options = [
                PDO::MYSQL_ATTR_SSL_KEY  => \DB_SSL_KEY,
                PDO::MYSQL_ATTR_SSL_CERT => \DB_SSL_CERT,
                PDO::MYSQL_ATTR_SSL_CA   => \DB_SSL_CA
            ];
        }
        if (\defined('DB_PERSISTENT_CONNECTIONS') && \is_bool(\DB_PERSISTENT_CONNECTIONS)) {
            $options[PDO::ATTR_PERSISTENT] = \DB_PERSISTENT_CONNECTIONS;
        }
        if (\defined('DB_CHARSET')) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES '" . \DB_CHARSET . "'" . (\defined('DB_COLLATE')
                    ? " COLLATE '" . \DB_COLLATE . "'"
                    : '');
        }
        // this was added for compatibility with 5.1.2 and php8.1
        $options[PDO::ATTR_STRINGIFY_FETCHES] = true;

        return $options;
    }

    private function initDebugging(bool $debugOverride = false): void
    {
        if ($debugOverride === false && \PROFILE_QUERIES !== false) {
            $this->debugLevel = \DEBUG_LEVEL;
            if (\PROFILE_QUERIES === true) {
                $this->debug = true;
            }
        }
        if (\ES_DB_LOGGING === true) {
            $this->logErrors = true;
        }
        if (\ES_DB_LOGGING === true || \NICEDB_EXCEPTION_BACKTRACE === true) {
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
    }

    /**
     * descructor for debugging purposes and closing db connection
     */
    public function __destruct()
    {
        $this->state = 'destructed';
        if ($this->isConnected) {
            $this->close();
        }
    }

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @inheritdoc
     */
    public function reInit(): DbInterface
    {
        $dsn = 'mysql:dbname=' . $this->config['database'];
        if (\defined('DB_SOCKET')) {
            $dsn .= ';unix_socket=' . \DB_SOCKET;
        } else {
            $dsn .= ';host=' . $this->config['host'];
        }
        $this->pdo = new PDO($dsn, $this->config['username'], $this->config['password']);
        if (\defined('DB_CHARSET')) {
            $this->pdo->exec(
                "SET NAMES '" . \DB_CHARSET . "'" . (\defined('DB_COLLATE')
                    ? " COLLATE '" . \DB_COLLATE . "'"
                    : '')
            );
        }
        $this->queryAnalyzer = new QueryAnalyzer($this->pdo, $this->debug, $this->debugLevel);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function close(): bool
    {
        unset($this->pdo);
        $this->isConnected = false;

        return true;
    }

    /**
     * @inheritdoc
     */
    public function isConnected(): bool
    {
        return $this->isConnected;
    }

    /**
     * @inheritdoc
     */
    public function getServerInfo(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    }

    /**
     * @inheritdoc
     */
    public function getServerStats(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_SERVER_INFO);
    }

    /**
     * @inheritdoc
     */
    public function getPDO(): PDO
    {
        return $this->pdo;
    }

    /**
     * @inheritdoc
     * @throws InvalidEntityNameException
     */
    public function insertRow(string $tableName, object $object, bool $echo = false): int
    {
        $start   = \microtime(true);
        $keys    = []; // column names
        $values  = []; // column values - either sql statement like "now()" or prepared like ":my-var-name"
        $assigns = []; // assignments from prepared var name to values, will be inserted in ->prepare()
        $this->validateEntityName($tableName);
        $this->validateDbObject($object);
        foreach (\get_object_vars($object) as $col => $val) {
            $keys[] = '`' . $col . '`';
            if ($val === '_DBNULL_') {
                $val = null;
            } elseif ($val === null) {
                $val = '';
            }
            $lc = \mb_convert_case((string)$val, \MB_CASE_LOWER);
            if ($lc === 'now()' || $lc === 'current_timestamp') {
                $values[] = $val;
            } else {
                $values[]            = ':' . $col;
                $assigns[':' . $col] = $val;
            }
        }
        $stmt = 'INSERT INTO ' . $tableName
            . ' (' . \implode(', ', $keys) . ') VALUES (' . \implode(', ', $values) . ')';
        if ($echo) {
            echo $stmt;
        }
        try {
            $res = $this->pdo->prepare($stmt)->execute($assigns);
        } catch (PDOException $e) {
            $this->handleException($e, $stmt, $assigns);

            return 0;
        }
        if (!$res) {
            $this->logError($stmt);

            return 0;
        }
        $id = $this->pdo->lastInsertId();
        if (!\str_starts_with($tableName, 'tprofiler')) {
            $this->queryAnalyzer->analyzeQuery($stmt, $assigns, null, \microtime(true) - $start);
        }

        return $id > 0 ? (int)$id : 1;
    }

    /**
     * @inheritdoc
     */
    public function insertBatch(string $tableName, array $objects, bool $upsert = false): int
    {
        $this->validateEntityName($tableName);
        $keys    = []; // column names
        $values  = []; // column values - either sql statement like "now()" or prepared like ":my-var-name"
        $assigns = []; // assignments from prepared var name to values, will be inserted in ->prepare()
        $i       = 0;
        $j       = 0;
        $v       = [];
        foreach ($objects as $object) {
            $this->validateDbObject($object);
            foreach (\get_object_vars($object) as $col => $val) {
                if ($i === 0) {
                    $keys[] = '`' . $col . '`';
                }
                if ($val === '_DBNULL_') {
                    $val = null;
                } elseif ($val === null) {
                    $val = '';
                }
                $lc = \mb_convert_case((string)$val, \MB_CASE_LOWER);
                if ($lc === 'now()' || $lc === 'current_timestamp') {
                    $values[] = $val;
                } else {
                    $values[]           = ':a' . $j;
                    $assigns[':a' . $j] = $val;
                }
                ++$j;
            }
            $v[] = '(' . \implode(', ', $values) . ')';
            ++$i;
            $values = [];
        }
        if ($upsert) {
            $stmt = /** @lang text */
                'REPLACE INTO ';
        } else {
            $stmt = /** @lang text */
                'INSERT IGNORE INTO ';
        }
        $stmt .= $tableName . ' (' . \implode(', ', $keys) . ') VALUES ' . \implode(',', $v);
        try {
            $s   = $this->pdo->prepare($stmt);
            $res = $s->execute($assigns);
        } catch (PDOException $e) {
            $this->handleException($e, $stmt, $assigns);

            return 0;
        }
        if (!$res) {
            $this->logError($stmt);

            return 0;
        }

        return $s->rowCount();
    }

    /**
     * @inheritdoc
     */
    public function insert(string $tableName, $object, bool $echo = false): int
    {
        return $this->insertRow($tableName, $object, $echo);
    }

    /**
     * @inheritdoc
     */
    public function updateRow(
        string $tableName,
        array|string $keyname,
        mixed $keyvalue,
        object $object,
        bool $echo = false
    ): int {
        $start = \microtime(true);
        $this->validateEntityName($tableName);
        foreach ((array)$keyname as $x) {
            $this->validateEntityName($x);
        }
        $this->validateDbObject($object);
        $arr     = \get_object_vars($object);
        $updates = []; // list of "<column name>=?" or "<column name>=now()" strings
        $assigns = []; // list of values to insert as param for ->prepare()
        if (!$keyname || !$keyvalue) {
            return -1;
        }
        foreach ($arr as $_key => $_val) {
            if ($_val === '_DBNULL_') {
                $_val = null;
            } elseif ($_val === null) {
                $_val = '';
            }
            $lc = \mb_convert_case((string)$_val, \MB_CASE_LOWER);
            if ($lc === 'now()' || $lc === 'current_timestamp') {
                $updates[] = '`' . $_key . '`=' . $_val;
            } else {
                $updates[] = '`' . $_key . '`=?';
                $assigns[] = $_val;
            }
        }
        if (\is_array($keyname) && \is_array($keyvalue)) {
            $keynamePrepared = \array_map(static fn($_v): string => '`' . $_v . '`=?', $keyname);
            $where           = ' WHERE ' . \implode(' AND ', $keynamePrepared);
            foreach ($keyvalue as $_v) {
                $assigns[] = $_v;
            }
        } elseif (\is_string($keyname)) {
            $assigns[] = $keyvalue;
            $where     = ' WHERE `' . $keyname . '`=?';
        } else {
            throw new InvalidArgumentException('key name and key value must be either both arrays or both strings');
        }
        $stmt = 'UPDATE ' . $tableName . ' SET ' . \implode(',', $updates) . $where;
        if ($echo) {
            echo $stmt;
        }
        try {
            $statement = $this->pdo->prepare($stmt);
            $res       = $statement->execute($assigns);
        } catch (PDOException $e) {
            $this->handleException($e, $stmt, $assigns);

            return -1;
        }
        if ($res) {
            $ret = $statement->rowCount();
        } else {
            $this->logError($stmt);
            $ret = -1;
        }
        $this->queryAnalyzer->analyzeQuery($stmt, $assigns, null, \microtime(true) - $start);

        return $ret;
    }

    /**
     * @inheritdoc
     */
    public function update(
        string $tableName,
        array|string $keyname,
        mixed $keyvalue,
        object $object,
        bool $echo = false
    ): int {
        return $this->updateRow($tableName, $keyname, $keyvalue, $object, $echo);
    }

    /**
     * @inheritdoc
     * @throws InvalidEntityNameException
     */
    public function upsert(string $tableName, object $object, array $excludeUpdate = [], bool $echo = false): int
    {
        $start = \microtime(true);
        $this->validateEntityName($tableName);
        $this->validateDbObject($object);
        $insData = [];
        $updData = [];
        $assigns = [];
        foreach (\get_object_vars($object) as $column => $value) {
            if ($value === '_DBNULL_') {
                $value = null;
            } elseif ($value === null) {
                $value = '';
            }
            $lc = \mb_convert_case((string)$value, \MB_CASE_LOWER);
            if ($lc === 'now()' || $lc === 'current_timestamp') {
                $insData['`' . $column . '`'] = $value;
                if (!\in_array($column, $excludeUpdate, true)) {
                    $updData[] = '`' . $column . '` = ' . $value;
                }
            } else {
                $insData['`' . $column . '`'] = ':' . $column;
                $assigns[':' . $column]       = $value;
                if (!\in_array($column, $excludeUpdate, true)) {
                    $updData[] = '`' . $column . '` = :' . $column;
                }
            }
        }

        $sql = 'INSERT' . (\count($updData) > 0 ? ' ' : ' IGNORE ') . 'INTO ' . $tableName
            . '(' . \implode(', ', \array_keys($insData)) . ')
                    VALUES (' . \implode(', ', $insData) . ')' . (\count($updData) > 0 ? ' ON DUPLICATE KEY
                    UPDATE ' . \implode(', ', $updData) : '');
        if ($echo) {
            echo $sql;
        }
        $statement = $this->pdo->prepare($sql);
        try {
            $res = $statement->execute($assigns);
        } catch (PDOException $e) {
            $this->handleException($e, $sql, $assigns);

            return -1;
        }

        if (!$res) {
            $this->logError($sql);

            return -1;
        }

        $lastID = $this->pdo->lastInsertId();
        $this->queryAnalyzer->analyzeQuery($sql, $assigns, null, \microtime(true) - $start);

        return (int)$lastID;
    }

    /**
     * @inheritdoc
     * @throws InvalidEntityNameException
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
    ): ?stdClass {
        $start = \microtime(true);
        $this->validateEntityName($tableName);
        foreach ((array)$keyname as $x) {
            $this->validateEntityName($x);
        }
        if ($keyname1 !== null) {
            $this->validateEntityName($keyname1);
        }
        if ($keyname2 !== null) {
            $this->validateEntityName($keyname2);
        }
        $keys    = \is_array($keyname) ? $keyname : [$keyname, $keyname1, $keyname2];
        $values  = \is_array($keyvalue) ? $keyvalue : [$keyvalue, $keyvalue1, $keyvalue2];
        $assigns = [];
        $i       = 0;
        foreach ($keys as &$_key) {
            if ($_key !== null) {
                $_key      = '`' . $_key . '`=?';
                $assigns[] = $values[$i];
            } else {
                unset($keys[$i]);
            }
            ++$i;
        }
        unset($_key);
        $stmt = 'SELECT ' . $select
            . ' FROM ' . $tableName
            . ((\count($keys) > 0)
                ? (' WHERE ' . \implode(' AND ', $keys))
                : ''
            );
        if ($echo) {
            echo $stmt;
        }
        try {
            $statement = $this->pdo->prepare($stmt);
            $res       = $statement->execute($assigns);
        } catch (PDOException $e) {
            $this->handleException($e, $stmt, $assigns);

            return null;
        }
        if (!$res) {
            $this->logError($stmt);

            return null;
        }
        $ret = $statement->fetchObject();
        $this->queryAnalyzer->analyzeQuery($stmt, $assigns, null, \microtime(true) - $start);

        return $ret !== false ? $ret : null;
    }

    /**
     * @inheritdoc
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
    ): ?stdClass {
        return $this->selectSingleRow(
            $tableName,
            $keyname,
            $keyvalue,
            $keyname1,
            $keyvalue1,
            $keyname2,
            $keyvalue2,
            $echo,
            $select
        );
    }

    /**
     * @inheritdoc
     */
    public function selectArray(
        string $tableName,
        array|string $keys,
        mixed $values,
        string $select = '*',
        string $orderBy = '',
        int|string $limit = ''
    ): array {
        $this->validateEntityName($tableName);
        foreach ((array)$keys as $key) {
            $this->validateEntityName($key);
        }

        $keys   = \is_array($keys) ? $keys : [$keys];
        $values = \is_array($values) ? $values : [$values];
        $kv     = [];
        if (\count($keys) !== \count($values)) {
            throw new InvalidArgumentException(
                \sprintf(
                    'Number of keys must be equal to number of given keys. Got %d key(s) and %d value(s).',
                    \count($keys),
                    \count($values)
                )
            );
        }
        foreach ($keys as $_key) {
            $kv[] = '`' . $_key . '`=:' . $_key;
        }
        $stmt = 'SELECT ' . $select . ' FROM ' . $tableName
            . ((\count($keys) > 0) ?
                (' WHERE ' . \implode(' AND ', $kv)) :
                ''
            )
            . (!empty($orderBy) ? (' ORDER BY ' . $orderBy) : '')
            . (!empty($limit) ? (' LIMIT ' . $limit) : '');

        $res = $this->execute(1, $stmt, \array_combine($keys, $values), ReturnType::ARRAY_OF_OBJECTS);

        if (\is_array($res)) {
            return $res;
        }

        throw new InvalidArgumentException(
            'The queried table "' . $tableName . '" or one of its columns "' . $select . '" might not exist.'
        );
    }

    /**
     * @inheritdoc
     */
    public function selectAll(
        string $tableName,
        array|string $keys,
        mixed $values,
        string $select = '*',
        string $orderBy = '',
        int|string $limit = ''
    ): array {
        return $this->selectArray($tableName, $keys, $values, $select, $orderBy, $limit);
    }

    /**
     * @inheritdoc
     */
    public function executeQuery(
        string $stmt,
        int $return = ReturnType::DEFAULT,
        bool $echo = false,
        ?callable $fnInfo = null
    ): mixed {
        return $this->execute(0, $stmt, [], $return, $echo, $fnInfo);
    }

    /**
     * @inheritdoc
     */
    public function executeQueryPrepared(
        string $stmt,
        array $params,
        int $return = ReturnType::DEFAULT,
        bool $echo = false,
        ?callable $fnInfo = null
    ): mixed {
        return $this->execute(1, $stmt, $params, $return, $echo, $fnInfo);
    }

    /**
     * @inheritdoc
     */
    public function queryPrepared(
        string $stmt,
        array $params,
        int $return = ReturnType::DEFAULT,
        bool $echo = false,
        ?callable $fnInfo = null
    ): mixed {
        return $this->execute(1, $stmt, $params, $return, $echo, $fnInfo);
    }

    /**
     * @inheritdoc
     */
    public function getArrays(string $stmt, array $params = []): array
    {
        return $this->execute(1, $stmt, $params, ReturnType::ARRAY_OF_ASSOC_ARRAYS);
    }

    /**
     * @inheritdoc
     */
    public function getInts(string $stmt, string $rowName, array $params = []): array
    {
        return \array_map(
            static fn(array $ele): int => (int)$ele[$rowName],
            $this->execute(1, $stmt, $params, ReturnType::ARRAY_OF_ASSOC_ARRAYS)
        );
    }

    /**
     * @inheritdoc
     */
    public function getObjects(string $stmt, array $params = []): array
    {
        return $this->execute(1, $stmt, $params, ReturnType::ARRAY_OF_OBJECTS);
    }

    /**
     * @inheritdoc
     */
    public function getCollection(string $stmt, array $params = []): Collection
    {
        return $this->execute(1, $stmt, $params, ReturnType::COLLECTION);
    }

    /**
     * @inheritdoc
     */
    public function getSingleObject(string $stmt, array $params = []): ?stdClass
    {
        $res = $this->execute(1, $stmt, $params, ReturnType::SINGLE_OBJECT);

        return $res !== false ? $res : null;
    }

    /**
     * @inheritdoc
     */
    public function ddl(string $stmt, array $params = []): bool
    {
        return $this->execute(1, $stmt, $params, ReturnType::QUERY_OK);
    }

    /**
     * @inheritdoc
     */
    public function getSingleInt(string $stmt, string $rowName, array $params = []): int
    {
        $res = $this->getSingleObject($stmt, $params);

        return $res === null ? -1 : ((int)$res->$rowName);
    }

    /**
     * @inheritdoc
     */
    public function getLastInsertedID(string $stmt, array $params = []): int
    {
        $res = $this->execute(1, $stmt, $params, ReturnType::LAST_INSERTED_ID);

        return $res !== false ? (int)$res : 0;
    }

    /**
     * @inheritdoc
     */
    public function getSingleArray(string $stmt, array $params = []): ?array
    {
        return $this->execute(1, $stmt, $params, ReturnType::SINGLE_ASSOC_ARRAY);
    }

    /**
     * @inheritdoc
     */
    public function getAffectedRows(string $stmt, array $params = []): int
    {
        return $this->execute(1, $stmt, $params, ReturnType::AFFECTED_ROWS);
    }

    /**
     * @inheritdoc
     */
    public function getPDOStatement(string $stmt, array $params = []): PDOStatement
    {
        return $this->execute(1, $stmt, $params, ReturnType::QUERYSINGLE);
    }

    /**
     * executes query and returns misc data
     *
     * @param int                  $type - Type [0 => query, 1 => prepared]
     * @param string               $stmt - Statement to be executed
     * @param array<string, mixed> $params - An array of values with as many elements as there are bound parameters
     * @param int                  $return - what should be returned.
     * @param bool                 $echo print current stmt
     * @param null|callable        $fnInfo
     * 1  - single fetched object
     * 2  - array of fetched objects
     * 3  - affected rows
     * 7  - last inserted id
     * 8  - fetched assoc array
     * 9  - array of fetched assoc arrays
     * 10 - result of querysingle
     * 11 - fetch both arrays
     * 12 - collection of fetched objects
     * 13 - query ok
     * @throws InvalidArgumentException
     * @return ($return is 1 ? stdClass : ($return is 2 ? stdClass[] : ($return is 3 ? int : ($return is 7 ? int :
     * ($return is 8 ? array<mixed> : ($return is 9 ? array<array<mixed>> : ($return is 10 ? PDOStatement :
     * ($return is 11 ? array<int, array<mixed>> : ($return is 12 ? Collection<int, stdClass> :
     * ($return is 13 ? bool : bool))))))))))
     */
    protected function execute(
        int $type,
        string $stmt,
        array $params,
        int $return,
        bool $echo = false,
        ?callable $fnInfo = null
    ): mixed {
        if (!\in_array($type, [0, 1], true)) {
            throw new InvalidArgumentException('$type parameter must be 0 or 1, "' . $type . '" given');
        }
        if ($return <= 0 || $return > 13) {
            throw new InvalidArgumentException('$return parameter must be between 1 - 13, "' . $return . '" given');
        }

        if ($echo) {
            echo $stmt;
        }

        $start = \microtime(true);
        try {
            if ($type === 0) {
                $res = $this->pdo->query($stmt);
            } else {
                $res = $this->pdo->prepare($stmt);
                foreach ($params as $k => $v) {
                    $this->bind($res, $k, $v);
                }
                if ($res->execute() === false) {
                    return $this->failExecute($return);
                }
            }
        } catch (PDOException $e) {
            $this->handleException($e, $this->readableQuery($stmt, $params));
            if ($this->transactionCount > 0) {
                throw $e;
            }

            return $this->failExecute($return);
        }

        if ($fnInfo !== null) {
            $info = [
                'mysqlerrno' => $this->pdo->errorCode(),
                'statement'  => $stmt,
                'time'       => \microtime(true) - $start
            ];
            $fnInfo($info);
        }

        if (!$res) {
            $this->logError($this->readableQuery($stmt, $params));

            return $this->failExecute($return);
        }

        $ret = $this->getQueryResult($return, $res);
        $this->queryAnalyzer->analyzeQuery($stmt, null, $type === 0 ? null : $params, \microtime(true) - $start);

        return $ret;
    }

    /**
     * @return array<mixed>|array<array<mixed>>|bool|Collection<int, stdClass>|int|PDOStatement|null
     */
    private function failExecute(int $returnType): mixed
    {
        return match ($returnType) {
            ReturnType::COLLECTION         => new Collection(),
            ReturnType::ARRAY_OF_OBJECTS,
            ReturnType::ARRAY_OF_ASSOC_ARRAYS,
            ReturnType::ARRAY_OF_BOTH_ARRAYS,
            ReturnType::SINGLE_ASSOC_ARRAY => [],
            ReturnType::SINGLE_OBJECT      => null,
            ReturnType::QUERYSINGLE        => new PDOStatement(),
            ReturnType::DEFAULT            => true,
            ReturnType::QUERY_OK           => false,
            default                        => 0,
        };
    }

    private function getQueryResult(int $type, PDOStatement $statement): mixed
    {
        switch ($type) {
            case ReturnType::SINGLE_OBJECT:
                try {
                    $result = $statement->fetchObject();
                } catch (Exception $e) {
                    $this->logError($statement->queryString, $e);
                    $result = false;
                }
                break;
            case ReturnType::ARRAY_OF_OBJECTS:
                $result = [];
                try {
                    while (($row = $statement->fetchObject()) !== false) {
                        $result[] = $row;
                    }
                } catch (Exception $e) {
                    $this->logError($statement->queryString, $e);
                }
                break;
            case ReturnType::COLLECTION:
                $result = new Collection();
                try {
                    while (($row = $statement->fetchObject()) !== false) {
                        $result->push($row);
                    }
                } catch (Exception $e) {
                    $this->logError($statement->queryString, $e);
                }
                break;
            case ReturnType::AFFECTED_ROWS:
                try {
                    $result = $statement->rowCount();
                } catch (Exception $e) {
                    $this->logError($statement->queryString, $e);
                    $result = 0;
                }
                break;
            case ReturnType::LAST_INSERTED_ID:
                $id     = $this->pdo->lastInsertId();
                $result = ($id > 0) ? $id : 1;
                break;
            case ReturnType::SINGLE_ASSOC_ARRAY:
                try {
                    $result = $statement->fetchAll(PDO::FETCH_NAMED);
                } catch (Exception $e) {
                    $this->logError($statement->queryString, $e);
                    $result = null;
                }
                if (\is_array($result) && isset($result[0])) {
                    $result = $result[0];
                } else {
                    $result = null;
                }
                break;
            case ReturnType::ARRAY_OF_ASSOC_ARRAYS:
                try {
                    $result = $statement->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $this->logError($statement->queryString, $e);
                    $result = [];
                }
                break;
            case ReturnType::QUERYSINGLE:
                $result = $statement;
                break;
            case ReturnType::ARRAY_OF_BOTH_ARRAYS:
                try {
                    $result = $statement->fetchAll(PDO::FETCH_BOTH);
                } catch (Exception $e) {
                    $this->logError($statement->queryString, $e);
                    $result = [];
                }
                break;
            default:
                $result = true;
                break;
        }

        return $result;
    }

    /**
     * @inheritdoc
     * @throws InvalidEntityNameException
     */
    public function deleteRow(
        string $tableName,
        array|string $keyname,
        mixed $keyvalue,
        bool $echo = false
    ): int {
        $this->validateEntityName($tableName);
        foreach ((array)$keyname as $i) {
            $this->validateEntityName($i);
        }
        $start   = \microtime(true);
        $assigns = [];
        if (\is_array($keyname) && \is_array($keyvalue)) {
            $keyname = \array_map(static fn($_v): string => '`' . $_v . '`=?', $keyname);
            $where   = \implode(' AND ', $keyname);
            foreach ($keyvalue as $_v) {
                $assigns[] = $_v;
            }
        } else {
            /** @var string $keyname */
            $assigns[] = $keyvalue;
            $where     = '`' . $keyname . '`=?';
        }

        $stmt = 'DELETE FROM ' . $tableName . ' WHERE ' . $where;

        if ($echo) {
            echo $stmt;
        }
        try {
            $statement = $this->pdo->prepare($stmt);
            $res       = $statement->execute($assigns);
        } catch (PDOException $e) {
            $this->handleException($e, $stmt);

            return -1;
        }
        if (!$res) {
            $this->logError($stmt);

            return -1;
        }
        $ret = $statement->rowCount();
        $this->queryAnalyzer->analyzeQuery($stmt, $assigns, null, \microtime(true) - $start);

        return $ret;
    }

    /**
     * @inheritdoc
     */
    public function delete(
        string $tableName,
        array|string $keyname,
        mixed $keyvalue,
        bool $echo = false
    ): int {
        return $this->deleteRow($tableName, $keyname, $keyvalue, $echo);
    }

    /**
     * @inheritdoc
     */
    public function executeExQuery(string $stmt): int|PDOStatement
    {
        try {
            $res = $this->pdo->query($stmt);
        } catch (PDOException $e) {
            $this->handleException($e, $stmt);

            return 0;
        }
        if (!$res) {
            $res = 0;
            $this->logError($stmt);
        }

        return $res;
    }

    /**
     * @inheritdoc
     */
    public function query(string $stmt, int $return = ReturnType::DEFAULT, bool $echo = false): mixed
    {
        return $this->execute(0, $stmt, [], $return, $echo);
    }

    protected function isPdoResult(mixed $res): bool
    {
        return \is_object($res) && $res instanceof PDOStatement;
    }

    /**
     * @inheritdoc
     */
    public function quote(mixed $value): string
    {
        if (\is_bool($value)) {
            $value = $value ?: '0';
        }

        return $this->pdo->quote((string)$value);
    }

    /**
     * @inheritdoc
     */
    public function escape(mixed $value): string
    {
        // remove outer single quotes
        return \preg_replace('/^\'(.*)\'$/', '$1', $this->quote($value)) ?? '';
    }

    /**
     * @inheritdoc
     */
    public function getErrorCode(): mixed
    {
        $errorCode = $this->pdo->errorCode();

        return $errorCode !== '00000' ? $errorCode : 0;
    }

    /**
     * @inheritdoc
     */
    public function getError(): array
    {
        return $this->pdo->errorInfo();
    }

    /**
     * @inheritdoc
     */
    public function getErrorMessage(): string
    {
        $error = $this->getError();

        return (isset($error[2]) && \is_string($error[2])) ? $error[2] : '';
    }

    /**
     * @inheritdoc
     */
    public function beginTransaction(): bool
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($this->transactionCount++ <= 0) {
            return $this->pdo->beginTransaction();
        }

        return $this->transactionCount >= 0;
    }

    /**
     * @inheritdoc
     */
    public function commit(): bool
    {
        if ($this->transactionCount-- === 1) {
            return $this->pdo->commit();
        }
        if (\NICEDB_EXCEPTION_BACKTRACE === false) {
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        }

        return $this->transactionCount <= 0;
    }

    /**
     * @inheritdoc
     */
    public function rollback(): bool
    {
        $result = false;
        if ($this->transactionCount >= 0) {
            $result = $this->pdo->rollBack();
        }
        $this->transactionCount = 0;

        return $result;
    }

    /**
     * @param PDOStatement $stmt
     * @param string|int   $parameter
     * @param mixed        $value
     * @param int|null     $type
     */
    protected function bind(PDOStatement $stmt, mixed $parameter, mixed $value, ?int $type = null): void
    {
        $parameter = \is_string($parameter) ? $this->bindName($parameter) : $parameter;

        if ($type === null) {
            $type = match (true) {
                \is_bool($value) => PDO::PARAM_BOOL,
                \is_int($value)  => PDO::PARAM_INT,
                $value === null  => PDO::PARAM_NULL,
                default          => PDO::PARAM_STR,
            };
        }

        $stmt->bindValue($parameter, $value, $type);
    }

    protected function bindName(string $name): string
    {
        return ':' . \ltrim($name, ':');
    }

    /**
     * @inheritdoc
     */
    public function readableQuery(string $query, array $params): string
    {
        $keys   = [];
        $values = [];
        foreach ($params as $key => $value) {
            $key    = \is_string($key)
                ? $this->bindName($key)
                : '[?]';
            $keys[] = '/' . $key . '/';
            $value  = \is_int($value)
                ? $value
                : $this->quote($value);

            $values[] = $value;
        }

        return \preg_replace($keys, $values, $query, 1) ?? '';
    }

    /**
     * Verifies that a database entity name matches the preconditions. Those preconditions are enforced to prevent
     * SQL-Injection through not preparable sql command components.
     */
    protected function isValidEntityName(string $name): bool
    {
        return \preg_match('/^[a-z_\d]+$/i', \trim($name)) === 1;
    }

    /**
     * Verifies db entity names and throws an exception if it does not match the preconditions
     * @throws InvalidEntityNameException
     */
    protected function validateEntityName(string $name): void
    {
        if (!$this->isValidEntityName($name)) {
            throw new InvalidEntityNameException($name);
        }
    }

    /**
     * This method shall prevent SQL-Injection through the member names of objects because they are not preparable.
     * @throws InvalidEntityNameException
     */
    protected function validateDbObject(object $obj): void
    {
        foreach (\get_object_vars($obj) as $key => $value) {
            if (!$this->isValidEntityName($key)) {
                throw new InvalidEntityNameException($key);
            }
        }
    }

    /**
     * @param PDOException $e
     * @param string       $stmt
     * @param mixed[]|null $assigns
     */
    private function handleException(PDOException $e, string $stmt, ?array $assigns = null): void
    {
        if (\NICEDB_EXCEPTION_ECHO === true) {
            Shop::dbg($stmt, false, 'NiceDB exception executing sql: ');
            if ($assigns !== null) {
                Shop::dbg($assigns, false, 'Bound params:');
            }
            Shop::dbg($e->getMessage());
            if (\NICEDB_EXCEPTION_BACKTRACE === true) {
                Shop::dbg(\debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS), false, 'Backtrace:');
            }
        }
        $this->logError($stmt, $e);
    }

    private function logError(string $stmt, ?Exception $e = null): void
    {
        if (!$this->logErrors) {
            return;
        }
        $errorMessage = $e === null
            ? $this->getErrorCode() . ': ' . $this->getErrorMessage()
            : $e->getMessage();
        Shop::Container()->getLogService()->error(
            "Error executing query {qry}\n{msg}",
            ['qry' => $stmt, 'msg' => $errorMessage]
        );
    }

    /**
     * @inheritdoc
     */
    public function tableExists(string $table): bool
    {
        $res = $this->getSingleObject(
            'SELECT 1 
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_NAME = :tbl
                    AND TABLE_SCHEMA = :sma',
            ['tbl' => $table, 'sma' => \DB_NAME]
        );

        return $res !== null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        foreach (\get_object_vars(Shop::Container()->getDB()) as $k => $v) {
            $this->$k = $v;
        }
    }

    /**
     * @return array{}
     */
    public function __serialize(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $res                       = \get_object_vars($this);
        $res['config']['password'] = '*****';

        return $res;
    }
}
