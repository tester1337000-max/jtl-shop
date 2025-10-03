<?php

declare(strict_types=1);

namespace JTL\GeneralDataProtection;

/**
 * Class AnonymizeIps
 * @package JTL\GeneralDataProtection
 *
 * anonymize IPs in various tables.
 *
 * names of the tables, we manipulate:
 *
 * `tbestellung`
 * `tbesucherarchiv`
 * `tkontakthistory`
 * `tproduktanfragehistory`
 * `tredirectreferer`
 * `tsitemaptracker`
 * `tsuchanfragencache`
 * `tverfuegbarkeitsbenachrichtigung`
 * `tvergleichsliste`
 * `tfloodprotect`
 */
class AnonymizeIps extends Method implements MethodInterface
{
    /**
     * @var array<string, array{ColKey: string, ColIp: string, ColCreated: string, ColType: string, ColFlag?: string}>
     */
    private static array $tablesToUpdate = [
        'tbestellung'                      => [
            'ColKey'     => 'kBestellung',
            'ColIp'      => 'cIP',
            'ColCreated' => 'dErstellt',
            'ColType'    => 'DATETIME'
        ],
        'tbesucherarchiv'                  => [
            'ColKey'     => 'kBesucher',
            'ColIp'      => 'cIP',
            'ColCreated' => 'dZeit',
            'ColType'    => 'DATETIME',
            'ColFlag'    => 'nAnonymisiert'
        ],
        'tkontakthistory'                  => [
            'ColKey'     => 'kKontaktHistory',
            'ColIp'      => 'cIP',
            'ColCreated' => 'dErstellt',
            'ColType'    => 'DATETIME'
        ],
        'tproduktanfragehistory'           => [
            'ColKey'     => 'kProduktanfrageHistory',
            'ColIp'      => 'cIP',
            'ColCreated' => 'dErstellt',
            'ColType'    => 'DATETIME'
        ],
        'tredirectreferer'                 => [
            'ColKey'     => 'kRedirectReferer',
            'ColIp'      => 'cIP',
            'ColCreated' => 'dDate',
            'ColType'    => 'TIMESTAMP'
        ],
        'tsitemaptracker'                  => [
            'ColKey'     => 'kSitemapTracker',
            'ColIp'      => 'cIP',
            'ColCreated' => 'dErstellt',
            'ColType'    => 'DATETIME'
        ],
        'tsuchanfragencache'               => [
            'ColKey'     => 'kSuchanfrageCache',
            'ColIp'      => 'cIP',
            'ColCreated' => 'dZeit',
            'ColType'    => 'DATETIME'
        ],
        'tverfuegbarkeitsbenachrichtigung' => [
            'ColKey'     => 'kVerfuegbarkeitsbenachrichtigung',
            'ColIp'      => 'cIP',
            'ColCreated' => 'dErstellt',
            'ColType'    => 'DATETIME'
        ],
        'tvergleichsliste'                 => [
            'ColKey'     => 'kVergleichsliste',
            'ColIp'      => 'cIP',
            'ColCreated' => 'dDate',
            'ColType'    => 'DATETIME'
        ],
        'tfloodprotect'                    => [
            'ColKey'     => 'kFloodProtect',
            'ColIp'      => 'cIP',
            'ColCreated' => 'dErstellt',
            'ColType'    => 'DATETIME'
        ]
    ];

    /**
     * run all anonymize processes
     */
    public function execute(): void
    {
        $this->anonymizeAllIPs();
        $this->isFinished = ($this->workSum < $this->workLimit);
    }

    /**
     * @param array<string, mixed> $colData
     */
    private function getAnonymizeSql(
        array $colData,
        string $tableName,
        string $ipMaskV4,
        string $ipMaskV6,
        string $dtNow
    ): string {
        if (isset($colData['ColFlag'])) {
            // Optimierte Query mit Flag
            $sql = \sprintf(
                'SELECT %s, %s, %s
                        FROM %s
                        WHERE %s = 0',
                $colData['ColKey'],
                $colData['ColIp'],
                $colData['ColCreated'],
                $tableName,
                $colData['ColFlag']
            );
        } else {
            // Standard Query für Tabellen ohne Flag
            $sql = \sprintf(
                'SELECT %s, %s, %s
                        FROM %s
                        WHERE NOT INSTR(cIP, \'.*\') > 0
                          AND NOT INSTR(cIP, \'%s\') > 0
                          AND NOT INSTR(cIP, \'%s\') > 0',
                $colData['ColKey'],
                $colData['ColIp'],
                $colData['ColCreated'],
                $tableName,
                $ipMaskV4,
                $ipMaskV6
            );
        }
        if ($colData['ColType'] !== 'TIMESTAMP') {
            $sql .= \sprintf(
                ' AND %s <= \'%s\' - INTERVAL %d DAY',
                $colData['ColCreated'],
                $dtNow,
                $this->interval
            );
        } else {
            $sql .= \sprintf(
                ' AND FROM_UNIXTIME(%s) <= \'%s\' - INTERVAL %d DAY',
                $colData['ColCreated'],
                $dtNow,
                $this->interval
            );
        }
        $sql .= \sprintf(' ORDER BY %s ASC LIMIT %d', $colData['ColCreated'], $this->workLimit);

        return $sql;
    }

    /**
     * @param array<string, mixed> $colData
     */
    private function anonymizeIPTables(
        array $colData,
        string $tableName,
        string $ipMaskV4,
        string $ipMaskV6,
        string $dtNow,
        IpAnonymizer $anonymizer
    ): void {
        $sql = $this->getAnonymizeSql($colData, $tableName, $ipMaskV4, $ipMaskV6, $dtNow);

        foreach ($this->db->getObjects($sql) as $row) {
            try {
                $row->cIP = $anonymizer->setIp($row->cIP)->anonymize();
                if (isset($colData['ColFlag'])) {
                    $row->{$colData['ColFlag']} = 1;
                }
                $this->workSum++;
            } catch (\Exception $e) {
                ($this->logger === null) ?: $this->logger->warning($e->getMessage());
            }
            $szKeyColName = $colData['ColKey'];
            $this->db->update(
                $tableName,
                $colData['ColKey'],
                (int)$row->$szKeyColName,
                $row
            );
        }
    }

    /**
     * anonymize IPs in various tables
     */
    public function anonymizeAllIPs(): void
    {
        $anonymizer = new IpAnonymizer('', true); // anonymize "beautified"
        $ipMaskV4   = $anonymizer->getMaskV4();
        $ipMaskV6   = $anonymizer->getMaskV6();
        $ipMaskV4   = \mb_substr($ipMaskV4, \mb_strpos($ipMaskV4, '.0') ?: 0, \mb_strlen($ipMaskV4) - 1);
        $ipMaskV6   = \mb_substr($ipMaskV6, \mb_strpos($ipMaskV6, ':0000') ?: 0, \mb_strlen($ipMaskV6) - 1);
        $dtNow      = $this->now->format('Y-m-d H:i:s');
        foreach (self::$tablesToUpdate as $tableName => $colData) {
            $this->anonymizeIPTables($colData, $tableName, $ipMaskV4, $ipMaskV6, $dtNow, $anonymizer);
        }
    }
}
