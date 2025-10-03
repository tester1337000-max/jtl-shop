<?php

declare(strict_types=1);

namespace JTL\Consent\Statistics\Repositories;

use JTL\Abstracts\AbstractDBRepository;
use stdClass;

/**
 * Class ConsentStatisticsRepository
 * @package JTL\Consent\Statistics\Repositories
 * @since 5.4.0
 * @description This is a layer between the Consent Statistics Service and the database.
 */
class ConsentStatisticsRepository extends AbstractDBRepository
{
    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'consent_statistics';
    }

    /**
     * @param array<string, int|bool> $consents
     */
    public function saveConsentValues(int $visitorID, string $eventDate, array $consents): void
    {
        foreach ($consents as $consentName => $consentValue) {
            if ($consentName === 'visitor') {
                $this->db->executeQueryPrepared(
                    'INSERT IGNORE INTO `consent_statistics` (`visitorID`, `eventDate`, `eventName`, `eventValue`)
                        VALUES (:visitorID, :eventDate, :eventName, :eventValue);',
                    [
                        ':visitorID'  => $visitorID,
                        ':eventDate'  => $eventDate,
                        ':eventName'  => $consentName,
                        ':eventValue' => 1
                    ]
                );
                continue;
            }
            $this->db->executeQueryPrepared(
                'INSERT INTO `consent_statistics` (`visitorID`, `eventDate`, `eventName`, `eventValue`)
                    VALUES (:visitorID, :eventDate, :eventName, :eventValue)
                    ON DUPLICATE KEY UPDATE `eventValue` = :eventValue;',
                [
                    ':visitorID'  => $visitorID,
                    ':eventDate'  => $eventDate,
                    ':eventName'  => $consentName,
                    ':eventValue' => (int)$consentValue
                ]
            );
        }
    }

    /**
     * @param string[] $timeframe
     * @param string[] $eventNames
     * @return stdClass[]
     */
    public function getConsentValues(array $timeframe, array $eventNames): array
    {
        /** @var stdClass[] $result */
        $result           = [];
        $filterEventNames = '';
        $param            = [
            ':from' => $timeframe[0],
            ':to'   => $timeframe[1]
        ];
        if (!empty($eventNames)) {
            $filterEventNames     = ' AND consent_statistics.eventName IN (:eventNames)';
            $param[':eventNames'] = $eventNames;
        }

        $this->db->getCollection(
            'SELECT COUNT(DISTINCT(consent_statistics.visitorID)) AS visitors,
                SUM(consent_statistics.eventValue) AS total, consent_statistics.eventName,
                consent_statistics.eventDate
                FROM consent_statistics
                WHERE consent_statistics.eventDate BETWEEN :from AND :to' . $filterEventNames
            . ' GROUP BY consent_statistics.eventDate, consent_statistics.eventName'
            . ' ORDER BY consent_statistics.eventDate',
            $param
        )->map(
            static function (stdClass $item) use (&$result): void {
                /**
                 * @var object{visitors: string, total: string, eventName: string, eventDate: string}&stdClass $item
                 */
                if (!isset($result[$item->eventDate])) {
                    $result[$item->eventDate]              = new stdClass();
                    $result[$item->eventDate]->visitors    = (int)$item->visitors;
                    $result[$item->eventDate]->acceptedAll = 0;
                    $result[$item->eventDate]->consents    = [];
                }

                match ($item->eventName === 'accepted_all') {
                    true  => $result[$item->eventDate]->acceptedAll                = (int)$item->total,
                    false => $result[$item->eventDate]->consents[$item->eventName] = (int)$item->total
                };
            }
        );

        return $result;
    }
}
