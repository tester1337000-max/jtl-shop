<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Step;

use DateTime;
use JTL\Backend\Upgrade\UpgradeLogger;
use JTLShop\SemVer\Version;

final class StepConfiguration implements \JsonSerializable
{
    public string $fsBackupFile = '';

    public string $dbBackupFile = '';

    public string $downloadURL = '';

    public string $tmpFile = '';

    public string $source = '';

    public string $checksum = '';

    public bool $finished = false;

    /**
     * @var array<string, int>
     */
    public array $updatedPlugins = [];

    public ?Version $targetVersion = null;

    public ?Version $sourceVersion = null;

    public const LOCK_FILE = \PFAD_ROOT . \PFAD_DBES_TMP . 'upgrade.lock';

    public function __construct(public UpgradeLogger $logger = new UpgradeLogger())
    {
        $this->sourceVersion = Version::parse(\APPLICATION_VERSION);
    }

    public function addInfo(string $log): void
    {
        $this->logger->info((new DateTime())->format('Y-m-d H:i:s') . ': ' . $log);
    }

    public function addError(string $log): void
    {
        $this->logger->error((new DateTime())->format('Y-m-d H:i:s') . ': ' . $log);
    }

    public function addDebug(string $log): void
    {
        $this->logger->debug((new DateTime())->format('Y-m-d H:i:s') . ': ' . $log);
    }

    public function addWarning(string $log): void
    {
        $this->logger->warning((new DateTime())->format('Y-m-d H:i:s') . ': ' . $log);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function fromArray(array $data): void
    {
        foreach ($data as $key => $value) {
            if ($key === 'targetVersion' || $key === 'sourceVersion') {
                $this->$key = Version::parse(\is_string($value) ? $value : '0.0.0');
                continue;
            }
            if (\in_array($key, ['logs', 'errors', 'warnings', 'debug'], true)) {
                /** @var string[] $value */
                $this->updateLogger($key, $value);
                continue;
            }
            $this->{$key} = $value;
        }
    }

    /**
     * @param string[] $logs
     */
    private function updateLogger(string $key, array $logs): void
    {
        if ($key === 'logs') {
            foreach ($logs as $log) {
                $this->logger->info($log);
            }
        } elseif ($key === 'errors') {
            foreach ($logs as $log) {
                $this->logger->error($log);
            }
        } elseif ($key === 'warnings') {
            foreach ($logs as $log) {
                $this->logger->warning($log);
            }
        } elseif ($key === 'debug') {
            foreach ($logs as $log) {
                $this->logger->debug($log);
            }
        }
    }

    public function jsonSerialize(): object
    {
        return (object)[
            'fsBackupFile'   => $this->fsBackupFile,
            'dbBackupFile'   => $this->dbBackupFile,
            'downloadURL'    => $this->downloadURL,
            'tmpFile'        => $this->tmpFile,
            'source'         => $this->source,
            'checksum'       => $this->checksum,
            'finished'       => $this->finished,
            'logs'           => $this->logger->getInfo(),
            'errors'         => $this->logger->getErrors(),
            'warnings'       => $this->logger->getWarnings(),
            'debug'          => $this->logger->getDebug(),
            'updatedPlugins' => $this->updatedPlugins,
            'targetVersion'  => (string)$this->targetVersion,
            'sourceVersion'  => (string)$this->sourceVersion,
        ];
    }
}
