<?php

declare(strict_types=1);

namespace JTL\ServiceReport;

use InvalidArgumentException;
use JTL\Abstracts\AbstractDBRepository;
use stdClass;

class ReportRepository extends AbstractDBRepository
{
    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'reports';
    }

    /**
     * @param string[]          $keys
     * @param array<string|int> $values
     * @return stdClass[]
     */
    public function getReports(array $keys, array $values): array
    {
        return $this->getDB()->selectAll($this->getTableName(), $keys, $values);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getReportByID(int $id): stdClass
    {
        $res = $this->get($id);
        if ($res === null) {
            throw new InvalidArgumentException(\__('Report not found'));
        }

        return $res;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getReportByHash(string $hash): stdClass
    {
        $res = $this->filter(['hash' => $hash]);
        if ($res === null) {
            throw new InvalidArgumentException(\__('Report not found'));
        }

        return $res;
    }

    public function updateAuthorizationData(int $id, string $hash): int
    {
        return $this->getDB()->getAffectedRows(
            'UPDATE reports
                SET validUntil = DATE_ADD(NOW(), INTERVAL 7 DAY), remoteIP = NULL, visited = NULL, hash = :hash
                WHERE id = :id',
            ['id' => $id, 'hash' => $hash]
        );
    }

    public function updateReportByID(int $id, stdClass $data): int
    {
        return $this->getDB()->update($this->getTableName(), 'id', $id, $data);
    }

    public function addReport(stdClass $data): int
    {
        return $this->getDB()->insert('reports', $data);
    }
}
