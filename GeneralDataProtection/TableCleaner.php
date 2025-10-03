<?php

declare(strict_types=1);

namespace JTL\GeneralDataProtection;

use DateTime;
use Exception;
use JTL\DB\DbInterface;
use JTL\Shop;
use Psr\Log\LoggerInterface;

/**
 * Class TableCleaner
 * @package JTL\GeneralDataProtection
 *
 * controller of "shop customer data anonymization"
 * ("GDPR" or "Global Data Protection Rules", german: "DSGVO")
 */
class TableCleaner
{
    private DateTime $now;

    private ?LoggerInterface $logger;

    private DbInterface $db;

    private bool $isFinished = true;

    private int $taskRepetitions;

    private int $lastProductID;

    /**
     * anonymize methods
     * (NOTE: the order of this methods is not insignificant and "can be configured")
     *
     * @var array<array{name: string, intervalDays: int}>
     */
    private static array $methods = [
        ['name' => 'AnonymizeIps', 'intervalDays' => 7],
        ['name' => 'AnonymizeDeletedCustomer', 'intervalDays' => 7],
        ['name' => 'CleanupCustomerRelicts', 'intervalDays' => 0],
        ['name' => 'CleanupNewsletterRecipients', 'intervalDays' => 30],
        ['name' => 'CleanupLogs', 'intervalDays' => 90],
        ['name' => 'CleanupService', 'intervalDays' => 0],  // multiple own intervals
        ['name' => 'CleanupForgottenOptins', 'intervalDays' => 1],  // same as 24 hours
        ['name' => 'CleanupGuestAccountsWithoutOrders', 'intervalDays' => 0],
        ['name' => 'CleanupStatistics', 'intervalDays' => 365], // 1 year and older will be deleted
    ];

    public function __construct()
    {
        try {
            $this->logger = Shop::Container()->getLogService();
        } catch (Exception) {
            $this->logger = null;
        }
        $this->now = new DateTime();
        $this->db  = Shop::Container()->getDB();
    }

    public function getMethodCount(): int
    {
        return \count(self::$methods);
    }

    public function getIsFinished(): bool
    {
        return $this->isFinished;
    }

    public function getTaskRepetitions(): int
    {
        return $this->taskRepetitions;
    }

    public function getLastProductID(): int
    {
        return $this->lastProductID;
    }

    public function executeByStep(int $taskIdx, int $taskRepetitions, int $lastProductID): void
    {
        $this->lastProductID = $lastProductID;
        if ($taskIdx < 0 || $taskIdx > \count(self::$methods)) {
            $this->logger?->notice('GeneralDataProtection: No task ID given.');

            return;
        }
        /** @var class-string<MethodInterface> $methodName */
        $methodName = __NAMESPACE__ . '\\' . self::$methods[$taskIdx]['name'];
        $instance   = new $methodName($this->now, self::$methods[$taskIdx]['intervalDays'], $this->db);
        // repetition-value from DB has preference over task-setting!
        if ($taskRepetitions !== 0) {
            // override the repetition-value of the instance
            $instance->setTaskRepetitions($taskRepetitions);
            $this->taskRepetitions = $taskRepetitions;
        } else {
            $this->taskRepetitions = $instance->getTaskRepetitions();
        }
        $instance->setLastProductID($this->lastProductID);
        $instance->execute();
        $this->taskRepetitions = $instance->getTaskRepetitions();
        $this->lastProductID   = $instance->getLastProductID();
        $this->isFinished      = $instance->getIsFinished();
        $this->logger?->notice(
            'Anonymize method executed: {name}, {cnt} entities processed.',
            ['name' => self::$methods[$taskIdx]['name'], 'cnt' => $instance->getWorkSum()]
        );
    }

    public function executeAll(): void
    {
        $timeStart = \microtime(true);
        foreach (self::$methods as $method) {
            /** @var class-string<MethodInterface> $methodName */
            $methodName = __NAMESPACE__ . '\\' . $method['name'];
            $instance   = new $methodName($this->now, $method['intervalDays'], $this->db);
            $instance->execute();
            $this->logger?->notice('Anonymize method executed: {method}', ['method' => $method['name']]);
        }
        $this->logger?->notice('Anonymizing finished in: ' . \sprintf('%01.4fs', \microtime(true) - $timeStart));
    }

    public function __destruct()
    {
        // removes journal-entries at the end of next year after their creation
        $this->db->queryPrepared(
            'DELETE FROM tanondatajournal
                WHERE dEventTime <= LAST_DAY(DATE_ADD(:pNow - INTERVAL 2 YEAR, INTERVAL 12 - MONTH(:pNow) MONTH))',
            ['pNow' => $this->now->format('Y-m-d H:i:s')]
        );
    }
}
