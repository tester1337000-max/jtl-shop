<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Step;

use JTL\Filesystem\Filesystem;
use JTL\Shop;

final class UnzipRelease extends AbstractStep
{
    public function getTitle(): string
    {
        return \sprintf(\__('Unzipping archive %s...'), $this->progress->tmpFile);
    }

    public function run(mixed $data = null): StepConfiguration
    {
        $this->startTiming();
        /** @var Filesystem $fileSystem */
        $fileSystem = Shop::Container()->get(Filesystem::class);
        $archive    = $this->progress->tmpFile;
        $target     = \PFAD_DBES_TMP . 'release';
        if ($fileSystem->directoryExists($target)) {
            $fileSystem->deleteDirectory($target);
        }
        $fileSystem->createDirectory($target);
        if ($fileSystem->unzip($archive, $target)) {
            $this->progress->source = $target;
            $this->stopTiming();

            return $this->progress;
        }
        $this->stopTiming();
        throw new StepFailedException(\sprintf('Could not unzip archive %s to %s', $archive, $target));
    }
}
