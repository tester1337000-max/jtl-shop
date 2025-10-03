<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

class UpgradeLogger extends AbstractLogger
{
    /**
     * @var string[]
     */
    private array $warnings = [];

    /**
     * @var string[]
     */
    private array $debug = [];

    /**
     * @var string[]
     */
    private array $errors = [];

    /**
     * @var string[]
     */
    private array $infos = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        switch ($level) {
            case LogLevel::WARNING:
                $this->warnings[] = (string)$message;
                break;
            case LogLevel::ERROR:
                $this->errors[] = (string)$message;
                break;
            case LogLevel::DEBUG:
                $this->debug[] = (string)$message;
                break;
            case LogLevel::INFO:
            default:
                $this->infos[] = (string)$message;
                break;
        }
    }

    /**
     * @return string[]
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * @return string[]
     */
    public function getDebug(): array
    {
        return $this->debug;
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return string[]
     */
    public function getInfo(): array
    {
        return $this->infos;
    }

    public function reset(): void
    {
        $this->debug    = [];
        $this->warnings = [];
        $this->errors   = [];
        $this->infos    = [];
    }
}
