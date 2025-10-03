<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Step;

use JTL\Filesystem\Filesystem;
use JTL\Shop;
use League\Flysystem\DirectoryAttributes;

final class RollbackFSBackup extends AbstractStep
{
    public function getTitle(): string
    {
        return \__('Restoring file system backup...');
    }

    public function run(mixed $data = null): StepConfiguration
    {
        $this->startTiming();
        $target = $this->unzip($this->progress->fsBackupFile, Shop::Container()->get(Filesystem::class));
        $this->copyToRoot($target);
        $this->progress->addInfo(\sprintf(\__('File system backup restored from %s.'), $this->progress->fsBackupFile));
        $this->stopTiming();

        return $this->progress;
    }

    private function unzip(string $archive, Filesystem $filesystem): string
    {
        $target = \PFAD_DBES_TMP . 'rollback';
        if ($filesystem->directoryExists($target)) {
            $filesystem->deleteDirectory($target);
        }
        $filesystem->createDirectory($target);
        if ($filesystem->unzip($archive, $target)) {
            return $target;
        }
        throw new StepFailedException(\sprintf(\__('Could not unzip archive %s to %s'), $archive, $target));
    }

    private function copyToRoot(string $source): void
    {
        $source = \rtrim($source, '/') . '/';
        /** @var DirectoryAttributes $item */
        foreach ($this->manager->listContents('root://' . $source, true) as $item) {
            $path   = $item->path();
            $target = \str_replace('root://' . $source, 'upgrade://', $path);
            if ($item->isDir()) {
                if (!$this->manager->directoryExists($target)) {
                    $this->progress->addDebug(\sprintf(\__('Created dir %s'), $target));
                    $this->manager->createDirectory($target);
                }
            } else {
                $this->progress->addDebug(\sprintf(\__('Copied file %s'), \str_replace('upgrade://', '', $target)));
                $this->manager->copy($path, $target);
            }
        }
    }
}
