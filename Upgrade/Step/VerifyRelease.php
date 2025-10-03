<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Step;

final class VerifyRelease extends AbstractStep
{
    public function getTitle(): string
    {
        return \__('Validating checksum...');
    }

    public function run(mixed $data = null): StepConfiguration
    {
        $this->startTiming();
        $checksum = \sha1_file($this->progress->tmpFile);
        if ($checksum !== $this->progress->checksum) {
            $msg = \sprintf(
                \__('Invalid checksum - expected %s, got %s'),
                $this->progress->checksum,
                $checksum
            );
            $this->progress->addError($msg);
            $this->stopTiming();
            throw new StepFailedException($msg);
        }
        $this->progress->addInfo(\sprintf(\__('Checksum %s is valid.'), $checksum));
        $this->stopTiming();

        return $this->progress;
    }
}
