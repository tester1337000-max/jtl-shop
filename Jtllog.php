<?php

declare(strict_types=1);

namespace JTL;

use JTL\Helpers\Text;
use JTL\Settings\Option\Globals;
use JTL\Settings\Settings;
use stdClass;

/**
 * Class Jtllog
 * @package JTL
 */
class Jtllog
{
    protected int $kLog = 0;

    protected int $nLevel = 0;

    protected string $cLog = '';

    protected string $cKey = '';

    protected int $kKey = 0;

    protected string $dErstellt = '';

    public function __construct(int $kLog = 0)
    {
        if ($kLog > 0) {
            $this->loadFromDB($kLog);
        }
    }

    private function loadFromDB(int $id): self
    {
        $data = Shop::Container()->getDB()->select('tjtllog', 'kLog', $id);
        if ($data !== null && $data->kLog > 0) {
            $this->kLog      = (int)$data->kLog;
            $this->nLevel    = (int)$data->nLevel;
            $this->kKey      = (int)$data->kKey;
            $this->cLog      = $data->cLog;
            $this->cKey      = $data->cKey;
            $this->dErstellt = $data->dErstellt;
        }

        return $this;
    }

    /**
     * @return stdClass[]
     */
    public static function getLogWhere(string $whereSQL = '', string $limitSQL = ''): array
    {
        return Shop::Container()->getDB()->getCollection(
            'SELECT *
                FROM tjtllog' .
            ($whereSQL !== '' ? ' WHERE ' . $whereSQL : '') .
            ' ORDER BY dErstellt DESC, kLog DESC ' .
            ($limitSQL !== '' ? ' LIMIT ' . $limitSQL : '')
        )->map(static function (stdClass $log): stdClass {
            $log->kLog   = (int)$log->kLog;
            $log->nLevel = (int)$log->nLevel;
            $log->kKey   = (int)$log->kKey;
            $log->cLog   = Text::filterXSS($log->cLog);

            return $log;
        })->all();
    }

    public static function getLogCount(string $filter = '', int $level = 0, string $extraCondition = ''): int
    {
        $conditions = [];
        $prep       = [];
        if ($level > 0) {
            $prep['lvl']  = $level;
            $conditions[] = 'nLevel = :lvl';
        }
        if (\mb_strlen($filter) > 0) {
            $prep['fltr'] = '%' . $filter . '%';
            $conditions[] = 'cLog LIKE :fltr';
        }
        if ($extraCondition !== '') {
            $conditions[] = $extraCondition;
        }
        $where = \count($conditions) > 0 ? ' WHERE ' . \implode(' AND ', $conditions) : '';

        return Shop::Container()->getDB()->getSingleInt(
            'SELECT COUNT(*) AS cnt 
                FROM tjtllog' . $where,
            'cnt',
            $prep
        );
    }

    public static function truncateLog(): void
    {
        $db = Shop::Container()->getDB();
        $db->query(
            'DELETE FROM tjtllog 
                WHERE DATE_ADD(dErstellt, INTERVAL 30 DAY) < NOW()'
        );
        $count = $db->getSingleInt(
            'SELECT COUNT(*) AS cnt 
                FROM tjtllog',
            'cnt'
        );
        if ($count > \JTLLOG_MAX_LOGSIZE) {
            $db->query('DELETE FROM tjtllog ORDER BY dErstellt LIMIT ' . ($count - \JTLLOG_MAX_LOGSIZE));
        }
    }

    /**
     * @param int[] $ids
     */
    public static function deleteIDs(array $ids): int
    {
        return Shop::Container()->getDB()->getAffectedRows(
            'DELETE FROM tjtllog WHERE kLog IN (' . \implode(',', \array_map('\intval', $ids)) . ')'
        );
    }

    public static function deleteAll(): int
    {
        return Shop::Container()->getDB()->getAffectedRows('TRUNCATE TABLE tjtllog');
    }

    public function delete(): int
    {
        return Shop::Container()->getDB()->delete('tjtllog', 'kLog', $this->getkLog());
    }

    public function setkLog(int $kLog): self
    {
        $this->kLog = $kLog;

        return $this;
    }

    public function setLevel(int $nLevel): self
    {
        $this->nLevel = $nLevel;

        return $this;
    }

    public function setcLog(string $cLog, bool $bFilter = true): self
    {
        $this->cLog = $bFilter ? Text::filterXSS($cLog) : $cLog;

        return $this;
    }

    public function setcKey(string $cKey): self
    {
        $this->cKey = $cKey;

        return $this;
    }

    public function setkKey(int|string $kKey): self
    {
        $this->kKey = (int)$kKey;

        return $this;
    }

    public function setErstellt(string $dErstellt): self
    {
        $this->dErstellt = $dErstellt;

        return $this;
    }

    public function getkLog(): int
    {
        return $this->kLog;
    }

    public function getLevel(): int
    {
        return $this->nLevel;
    }

    public function getcLog(): string
    {
        return $this->cLog;
    }

    public function getcKey(): string
    {
        return $this->cKey;
    }

    public function getkKey(): int
    {
        return $this->kKey;
    }

    public function getErstellt(): string
    {
        return $this->dErstellt;
    }

    /**
     * @former getSytemlogFlag()
     */
    public static function getSytemlogFlag(bool $cache = true): int
    {
        if ($cache === true) {
            return Settings::intValue(Globals::SYSLOG_LEVEL);
        }
        $conf = Shop::Container()->getDB()->getSingleObject(
            "SELECT cWert 
                FROM teinstellungen 
                WHERE cName = 'systemlog_flag'"
        );

        return (int)($conf->cWert ?? 0);
    }
}
