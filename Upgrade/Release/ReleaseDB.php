<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Release;

use JTL\DB\DbInterface;

class ReleaseDB
{
    private const MAX_RELEASES = 10;

    private const CHECK_INTERVAL_HOURS = 24;

    public function __construct(private readonly DbInterface $db)
    {
    }

    public function fetchReleaseDataFromDB(bool $checkTime = true): ?string
    {
        /** @var object{id: string, timestamp: string, data: string, returnCode: string}&\stdClass $data */
        $data = $this->db->getSingleObject(
            'SELECT * FROM releases
                WHERE returnCode = 200
                ORDER BY id DESC
                LIMIT 1'
        );
        if ($checkTime && ($data === null || $this->isExpired($data->timestamp))) {
            return null;
        }

        return $data->data ?? null;
    }

    private function isExpired(string $timestamp): bool
    {
        return (\time() - \strtotime($timestamp)) / (60 * 60) > self::CHECK_INTERVAL_HOURS;
    }

    public function saveReleaseData(string $body, int $statusCode): void
    {
        $this->db->insert(
            'releases',
            (object)[
                'returnCode' => $statusCode,
                'data'       => $body,
            ]
        );
        $this->housekeeping();
    }

    private function housekeeping(): void
    {
        $this->db->queryPrepared(
            'DELETE a 
                FROM releases AS a 
                JOIN ( 
                    SELECT id 
                        FROM releases 
                        ORDER BY timestamp DESC 
                        LIMIT 99999 OFFSET :max) AS b
                ON a.id = b.id',
            ['max' => self::MAX_RELEASES]
        );
    }
}
