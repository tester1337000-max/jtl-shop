<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Step;

use League\Flysystem\DirectoryAttributes;

final class MoveFilesToRoot extends AbstractStep
{
    public function getTitle(): string
    {
        return \sprintf(\__('Moving source %s to shop root...'), $this->progress->source);
    }

    public function run(mixed $data = null): StepConfiguration
    {
        $this->startTiming();
        $source = $this->progress->source;
        $source = \rtrim($source, '/') . '/';
        /** @var DirectoryAttributes $item */
        foreach ($this->manager->listContents('root://' . $source, true) as $item) {
            $sourcePath = $item->path();
            $targetPath = \str_replace('root://' . $source, 'upgrade://', $sourcePath);
            if ($item->isDir()) {
                if (!$this->manager->directoryExists($targetPath)) {
                    $this->progress->addDebug(\sprintf(\__('Created dir %s'), $targetPath));
                    $this->manager->createDirectory($targetPath);
                }
            } else {
                $this->progress->addDebug(\sprintf(\__('Copied file %s'), \str_replace('upgrade://', '', $targetPath)));
                $this->manager->copy($sourcePath, $targetPath);
            }
        }
        $this->stopTiming();

        return $this->progress;
    }
}
