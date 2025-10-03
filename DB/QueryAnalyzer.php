<?php

declare(strict_types=1);

namespace JTL\DB;

use JTL\Profiler;
use PDO;
use PDOException;
use PDOStatement;
use stdClass;

readonly class QueryAnalyzer
{
    public function __construct(private PDO $pdo, private bool $debug, private int $debugLevel)
    {
    }

    /**
     * @param string       $stmt
     * @param mixed[]|null $assigns
     * @param mixed[]|null $named
     * @param float        $time
     */
    public function analyzeQuery(string $stmt, ?array $assigns = null, ?array $named = null, float $time = 0.0): void
    {
        if (
            $this->debug !== true
            || \str_contains($stmt, 'tprofiler')
            || \mb_stripos($stmt, 'create table') !== false
        ) {
            return;
        }
        $explain = 'EXPLAIN ' . $stmt;
        try {
            if ($named !== null) {
                $res = $this->pdo->prepare($explain);
                foreach ($named as $k => $v) {
                    $this->bind($res, $k, $v);
                }
                $res->execute();
            } elseif ($assigns !== null) {
                $res = $this->pdo->prepare($explain);
                $res->execute($assigns);
            } else {
                $res = $this->pdo->query($explain);
            }
        } catch (PDOException) {
            return;
        }
        if ($res === false) {
            return;
        }
        $this->saveProfile($res, $time, $stmt);
    }

    /**
     * @param array<mixed>|null $backtrace
     * @return array<array<string, string>>|null
     */
    private function getBacktrace(?array $backtrace = null): ?array
    {
        if (!\is_array($backtrace)) {
            return null;
        }
        $stripped = [];
        foreach ($backtrace as $bt) {
            $bt['class']    = $bt['class'] ?? '';
            $bt['function'] = $bt['function'] ?? '';
            if (
                isset($bt['file'])
                && !($bt['class'] === __CLASS__ && $bt['function'] === '__call')
                && !\str_contains($bt['file'], 'NiceDB.php')
            ) {
                $stripped[] = [
                    'file'     => $bt['file'],
                    'line'     => $bt['line'],
                    'class'    => $bt['class'],
                    'function' => $bt['function']
                ];
            }
        }

        return $stripped;
    }

    /**
     * @param PDOStatement $stmt
     * @param string|int   $parameter
     * @param mixed        $value
     */
    private function bind(PDOStatement $stmt, string|int $parameter, mixed $value): void
    {
        $parameter = \is_string($parameter) ? ':' . \ltrim($parameter, ':') : $parameter;
        $type      = match (true) {
            \is_bool($value) => PDO::PARAM_BOOL,
            \is_int($value)  => PDO::PARAM_INT,
            $value === null  => PDO::PARAM_NULL,
            default          => PDO::PARAM_STR,
        };
        $stmt->bindValue($parameter, $value, $type);
    }

    /**
     * @param PDOStatement $res
     * @param float        $time
     * @param string       $stmt
     */
    public function saveProfile(PDOStatement $res, float $time, string $stmt): void
    {
        $backtrace = $this->getBacktrace($this->debugLevel > 2 ? \debug_backtrace() : null);
        while (($row = $res->fetchObject()) !== false) {
            if (!empty($row->table)) {
                $tableData            = new stdClass();
                $tableData->type      = $row->select_type ?? '???';
                $tableData->table     = $row->table;
                $tableData->count     = 1;
                $tableData->time      = $time;
                $tableData->hash      = \md5($stmt);
                $tableData->statement = null;
                $tableData->backtrace = null;
                if ($this->debugLevel > 1) {
                    $tableData->statement = \preg_replace('/\s\s+/', ' ', \mb_substr($stmt, 0, \NICEDB_DEBUG_STMT_LEN));
                    $tableData->backtrace = $backtrace;
                }
                Profiler::setSQLProfile($tableData);
            } elseif ($this->debugLevel > 1 && isset($row->Extra)) {
                $tableData            = new stdClass();
                $tableData->type      = $row->select_type ?? '???';
                $tableData->message   = $row->Extra;
                $tableData->statement = \preg_replace('/\s\s+/', ' ', $stmt);
                $tableData->backtrace = $backtrace;
                Profiler::setSQLError($tableData);
            }
        }
    }
}
