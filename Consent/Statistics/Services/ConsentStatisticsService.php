<?php

declare(strict_types=1);

namespace JTL\Consent\Statistics\Services;

use JTL\Abstracts\AbstractService;
use JTL\Consent\Statistics\Repositories\ConsentStatisticsRepository;
use stdClass;

/**
 * Class ConsentStatisticsService
 *
 * @package JTL\Consent\Statistics\Services
 * @since 5.4.0
 */
class ConsentStatisticsService extends AbstractService
{
    public function __construct(
        protected ConsentStatisticsRepository $consentStatisticsRepository = new ConsentStatisticsRepository()
    ) {
    }

    protected function getRepository(): ConsentStatisticsRepository
    {
        return $this->consentStatisticsRepository;
    }

    /**
     * @param array<string, int|bool> $consents
     */
    public function saveConsentValues(int $visitorID, array $consents = [], ?string $date = null): void
    {
        $this->getRepository()->saveConsentValues(
            visitorID: $visitorID,
            eventDate: $date ?? \date('Y-m-d'),
            consents: $consents
        );
    }

    /**
     * @param string[] $eventNames
     * @return object{dataTable: object{date: string|int, visitors: int, acceptance: int,
     *     consents: array<string, int>}[], dataChart: object{date: int|string, acceptance: int|float}[]}
     */
    public function getConsentStats(
        string $eventDateFrom,
        ?string $eventDateTo = null,
        array $eventNames = []
    ): object {
        $result        = (object)[
            'dataTable' => [],
            'dataChart' => [],
        ];
        $consentValues = $this->getRepository()->getConsentValues(
            [$eventDateFrom, ($eventDateTo ?? \date('Y-m-d'))],
            $eventNames
        );

        foreach ($consentValues as $consentDate => $consentDetails) {
            $details = '';
            foreach ($consentDetails->consents as $consentName => $consentValue) {
                $details .= $consentName . ': ' . $consentValue . ', ';
            }
            if (\strlen($details) > 1) {
                $details = \substr($details, 0, -2);
            }
            $obj                 = new stdClass();
            $obj->date           = $consentDate;
            $obj->acceptance     = ($consentDetails->acceptedAll / $consentDetails->visitors) * 100;
            $result->dataChart[] = $obj;

            $obj                 = new stdClass();
            $obj->date           = $consentDate;
            $obj->visitors       = $consentDetails->visitors;
            $obj->acceptance     = $consentDetails->acceptedAll;
            $obj->consents       = $details;
            $result->dataTable[] = $obj;
        }

        return $result;
    }

    /**
     * @param array<string, bool> $consents
     * @return bool
     */
    public function hasAcceptedAll(array $consents): bool
    {
        return !\in_array(false, $consents, true);
    }
}
