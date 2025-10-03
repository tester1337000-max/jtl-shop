<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Step;

final class CheckDiskSpace extends AbstractStep
{
    private float $minFreeBytes = 1024 * 1024 * 1024; // 1 GB

    public function getTitle(): string
    {
        return \__('Checking free disk space...');
    }

    public function run(mixed $data = null): StepConfiguration
    {
        $cb = \is_callable($data) ? $data : [$this, 'getFreeSpace'];
        $this->startTiming();
        /** @var float|false $freeBytes */
        $freeBytes = $cb();
        if ($freeBytes === false) {
            $this->progress->addWarning(\__('Could not determine free disk space.'));

            return $this->progress;
        }
        if ($freeBytes < $this->minFreeBytes) {
            throw new StepFailedException(
                \sprintf(
                    \__('Not enough free disk space available. Need %s, got %s.'),
                    $this->bytesToUnitString($this->minFreeBytes),
                    $this->bytesToUnitString($freeBytes)
                )
            );
        }
        $free = \sprintf(\__('%s free disk space'), $this->bytesToUnitString($freeBytes));
        $this->progress->addInfo($free);
        $this->stopTiming();

        return $this->progress;
    }

    private function bytesToUnitString(float $bytes): string
    {
        $prefixes = ['B', 'KB', 'MB', 'GB', 'TB'];
        $class    = \min((int)\log($bytes, 1024), \count($prefixes) - 1);

        return \sprintf('%1.2f %s', $bytes / (1024 ** $class), $prefixes[$class]);
    }

    private function getFreeSpace(): float|false
    {
        return \disk_free_space(\PFAD_ROOT);
    }
}
