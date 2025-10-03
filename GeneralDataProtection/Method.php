<?php

declare(strict_types=1);

namespace JTL\GeneralDataProtection;

use DateInterval;
use DateTime;
use Exception;
use JTL\DB\DbInterface;
use JTL\Shop;
use Psr\Log\LoggerInterface;

/**
 * Class Method
 * @package JTL\GeneralDataProtection
 */
class Method
{
    protected DateTime $now;

    protected int $workLimit = 1000;

    protected int $workSum = 0;

    protected bool $isFinished = false;

    protected int $taskRepetitions = 0;

    protected int $lastProductID = 0;

    protected ?string $dateLimit = null;

    protected ?LoggerInterface $logger;

    public function __construct(DateTime $now, protected int $interval, protected DbInterface $db)
    {
        try {
            $this->logger = Shop::Container()->getLogService();
        } catch (Exception) {
            $this->logger = null;
        }
        $this->now = clone $now;
        try {
            $this->dateLimit = $this->now->sub(
                new DateInterval('P' . $this->interval . 'D')
            )->format('Y-m-d H:i:s');
        } catch (Exception) {
            $this->logger?->warning('Wrong interval given: {interval}', ['interval' => $this->interval]);
        }
    }

    public function getIsFinished(): bool
    {
        return $this->isFinished;
    }

    public function getWorkSum(): int
    {
        return $this->workSum;
    }

    public function getTaskRepetitions(): int
    {
        return $this->taskRepetitions;
    }

    public function setTaskRepetitions(int $taskRepetitions): void
    {
        $this->taskRepetitions = $taskRepetitions;
    }

    public function getLastProductID(): int
    {
        return $this->lastProductID ?? 0;
    }

    public function setLastProductID(int $lastProductID): void
    {
        $this->lastProductID = $lastProductID;
    }
}
