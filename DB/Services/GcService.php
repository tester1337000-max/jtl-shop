<?php

declare(strict_types=1);

namespace JTL\DB\Services;

use JTL\DB\DbInterface;

/**
 * Class GcService
 * @package JTL\DB\Services
 */
class GcService implements GcServiceInterface
{
    /**
     * @var array<string, array{cDate: string, cSubTable: array<string, string>|null, cInterval: string}>
     */
    protected static array $definition = [
        'tbesucherarchiv'                  => [
            'cDate'     => 'dZeit',
            'cSubTable' => null,
            'cInterval' => '180'
        ],
        'tcheckboxlogging'                 => [
            'cDate'     => 'dErstellt',
            'cSubTable' => null,
            'cInterval' => '365'
        ],
        'texportformatqueuebearbeitet'     => [
            'cDate'     => 'dZuletztGelaufen',
            'cSubTable' => null,
            'cInterval' => '60'
        ],
        'tkampagnevorgang'                 => [
            'cDate'     => 'dErstellt',
            'cSubTable' => null,
            'cInterval' => '365'
        ],
        'tpreisverlauf'                    => [
            'cDate'     => 'dDate',
            'cSubTable' => null,
            'cInterval' => '120'
        ],
        'tredirectreferer'                 => [
            'cDate'     => 'dDate',
            'cSubTable' => null,
            'cInterval' => '60'
        ],
        'tsitemapreport'                   => [
            'cDate'     => 'dErstellt',
            'cSubTable' => [
                'tsitemapreportfile' => 'kSitemapReport'
            ],
            'cInterval' => '120'
        ],
        'tsuchanfrage'                     => [
            'cDate'     => 'dZuletztGesucht',
            'cSubTable' => [
                'tsuchanfrageerfolglos' => 'cSuche',
                'tsuchanfrageblacklist' => 'cSuche',
                'tsuchanfragencache'    => 'cSuche'
            ],
            'cInterval' => '120'
        ],
        'tsuchcache'                       => [
            'cDate'     => 'dGueltigBis',
            'cSubTable' => [
                'tsuchcachetreffer' => 'kSuchCache'
            ],
            'cInterval' => '30'
        ],
        'tverfuegbarkeitsbenachrichtigung' => [
            'cDate'     => 'dBenachrichtigtAm',
            'cSubTable' => null,
            'cInterval' => '90'
        ]
    ];

    public function __construct(protected DbInterface $db)
    {
    }

    public function run(): GcServiceInterface
    {
        foreach (self::$definition as $table => $mainTables) {
            $dateField = $mainTables['cDate'];
            $subTables = $mainTables['cSubTable'];
            $interval  = $mainTables['cInterval'];
            if ($subTables !== null) {
                $cFrom = $table;
                $cJoin = '';
                foreach ($subTables as $subTable => $cKey) {
                    $cFrom .= ', ' . $subTable;
                    $cJoin .= \sprintf(' LEFT JOIN %s ON %s.%s = %s.%s', $subTable, $subTable, $cKey, $table, $cKey);
                }
                $this->db->query(
                    \sprintf(
                        'DELETE %s FROM %s %s WHERE DATE_SUB(NOW(), INTERVAL %s DAY) >= %s.%s',
                        $cFrom,
                        $table,
                        $cJoin,
                        $interval,
                        $table,
                        $dateField
                    )
                );
            } else {
                $this->db->query(
                    \sprintf(
                        'DELETE FROM %s WHERE DATE_SUB(NOW(), INTERVAL %s DAY) >= %s',
                        $table,
                        $interval,
                        $dateField
                    )
                );
            }
        }

        return $this;
    }
}
