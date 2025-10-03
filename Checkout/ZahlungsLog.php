<?php

declare(strict_types=1);

namespace JTL\Checkout;

use JTL\Helpers\Text;
use JTL\Shop;
use stdClass;

/**
 * Class ZahlungsLog
 * @package JTL\Checkout
 */
class ZahlungsLog
{
    public bool $hasError = false;

    public function __construct(public string $cModulId)
    {
    }

    /**
     * @return stdClass[]
     */
    public function holeLog(string $limit, int $level = -1, string $whereSQL = '', string $searchString = ''): array
    {
        if ($level === 0) {
            $level = -1;
        }
        $condition = $level >= 0 ? (' AND nLevel = ' . $level) : '';
        $params    = ['mid' => $this->cModulId];
        $limits    = \explode(',', $limit);
        if (\count($limits) === 2) {
            $params['lmt']  = (int)$limits[0];
            $params['lmte'] = (int)$limits[1];
        } else {
            $params['lmt'] = (int)$limit;
        }

        return Shop::Container()->getDB()->getCollection(
            'SELECT * FROM tzahlungslog
                WHERE cModulId = :mid' . $condition . ($whereSQL !== '' ? ' AND ' . $whereSQL : '') . '
                ORDER BY dDatum DESC, kZahlunglog DESC 
                ' . (\count($limits) === 2 ? 'LIMIT :lmt, :lmte' : 'LIMIT :lmt'),
            $params
        )->map(static function (stdClass $log) use ($searchString): stdClass {
            $log->cLog     = Text::filterXSS($log->cLog);
            $log->cModulId = Text::filterXSS($log->cModulId);
            $log->cLogData = Text::filterXSS($log->cLogData ?? '');
            $log->nLevel   = (int)$log->nLevel;

            if (!empty($searchString)) {
                $log->cLog = \preg_replace(
                    '/(' . \preg_quote($searchString, '/') . ')/i',
                    '<mark>$1</mark>',
                    $log->cLog
                );
            }

            return $log;
        })->toArray();
    }

    public function logCount(): int
    {
        return Shop::Container()->getDB()->getSingleInt(
            'SELECT COUNT(*) AS cnt 
                FROM tzahlungslog 
                WHERE cModulId = :module',
            'cnt',
            ['module' => $this->cModulId]
        );
    }

    public function loeschen(): int
    {
        return Shop::Container()->getDB()->delete('tzahlungslog', 'cModulId', $this->cModulId);
    }

    public function log(string $msg): int
    {
        return self::add($this->cModulId, $msg);
    }

    public static function add(string $moduleID, string $msg, ?string $data = '', int $level = \LOGLEVEL_ERROR): int
    {
        if (\mb_strlen($moduleID) === 0) {
            return 0;
        }

        $log           = new stdClass();
        $log->cModulId = Text::filterXSS($moduleID);
        $log->cLog     = Text::filterXSS($msg);
        $log->cLogData = Text::filterXSS($data ?? '');
        $log->nLevel   = $level;
        $log->dDatum   = 'NOW()';

        return Shop::Container()->getDB()->insert('tzahlungslog', $log);
    }

    /**
     * @param array|mixed $moduleIDs
     * @param int         $offset
     * @param int         $limit
     * @param int         $level
     * @return stdClass[]
     */
    public static function getLog(mixed $moduleIDs, int $offset = 0, int $limit = 100, int $level = -1): array
    {
        if (!\is_array($moduleIDs)) {
            $moduleIDs = (array)$moduleIDs;
        }
        if (\count($moduleIDs) === 0) {
            return [];
        }
        $where        = ($level >= 0) ? ('AND nLevel = ' . $level) : '';
        $prep         = ['lmts' => $offset, 'lmte' => $limit];
        $i            = 0;
        $moduleIDlist = [];
        foreach ($moduleIDs as $moduleID) {
            $idx            = 'mid' . $i++;
            $prep[$idx]     = $moduleID;
            $moduleIDlist[] = ':' . $idx;
        }

        return Shop::Container()->getDB()->getCollection(
            'SELECT * FROM tzahlungslog
                WHERE cModulId IN(' . \implode(', ', $moduleIDlist) . ') ' . $where . '
                ORDER BY dDatum DESC, kZahlunglog DESC 
                LIMIT :lmts, :lmte',
            $prep
        )->map(static function (stdClass $log): stdClass {
            $log->cLog     = Text::filterXSS($log->cLog);
            $log->cModulId = Text::filterXSS($log->cModulId);
            $log->cLogData = Text::filterXSS($log->cLogData ?? '');
            $log->nLevel   = (int)$log->nLevel;

            return $log;
        })->toArray();
    }

    public static function count(string $moduleID, int $level = -1, string $whereSQL = ''): int
    {
        if ($level === 0) {
            $level = -1;
        }
        if ($level === -1) {
            return Shop::Container()->getDB()->getSingleInt(
                'SELECT COUNT(*) AS count 
                    FROM tzahlungslog 
                    WHERE cModulId = :cModulId ' . ($whereSQL !== '' ? ' AND ' . $whereSQL : ''),
                'count',
                ['cModulId' => $moduleID]
            );
        }

        return Shop::Container()->getDB()->getSingleInt(
            'SELECT COUNT(*) AS count 
                FROM tzahlungslog 
                WHERE cModulId = :cModulId 
                    AND nLevel = :nLevel ' . ($whereSQL !== '' ? ' AND ' . $whereSQL : ''),
            'count',
            ['nLevel' => $level, 'cModulId' => $moduleID],
        );
    }
}
