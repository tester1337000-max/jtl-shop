<?php

declare(strict_types=1);

namespace JTL\Traits;

use JTL\Exceptions\ServiceNotFoundException;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Trait LoggerTrait
 * @package JTL\Router
 */
trait LoggerTrait
{
    protected ?LoggerInterface $logService = null;

    protected function getLogService(): LoggerInterface
    {
        if (!isset($this->logService)) {
            throw new ServiceNotFoundException(Logger::class);
        }

        return $this->logService;
    }

    /**
     * @param array<mixed> $param
     */
    protected function log(string $msg, array $param = [], string $type = 'info'): void
    {
        if (isset($this->logService)) {
            match ($type) {
                'warning' => $this->getLogService()->warning($msg, $param),
                'error'   => $this->getLogService()->error($msg, $param),
                default   => $this->getLogService()->info($msg, $param)
            };
        }
    }
}
