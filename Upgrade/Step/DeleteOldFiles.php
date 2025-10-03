<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Step;

use JTL\Backend\FileCheck;
use JTL\Path;

final class DeleteOldFiles extends AbstractStep
{
    public function getTitle(): string
    {
        return \__('Deleting old files...');
    }

    public function run(mixed $data = null): StepConfiguration
    {
        $this->startTiming();
        $this->manager->deleteDirectory('root://' . \PFAD_DBES_TMP . 'release');
        $this->manager->delete('root://' . \PFAD_DBES_TMP . '.release.tmp.zip');
        $targetVersion = (string)($this->getConfiguration()->targetVersion ?? \APPLICATION_VERSION);
        $fileList      = \PFAD_ADMIN . \PFAD_INCLUDES . \PFAD_SHOPMD5
            . 'deleted_files_' . (new FileCheck())->getVersionString($targetVersion) . '.csv';
        if (!$this->manager->fileExists('upgrade://' . $fileList)) {
            $this->progress->addError(\sprintf(\__('No deleted files list: %s'), $fileList));

            return $this->progress;
        }
        $this->progress->addInfo(\sprintf(\__('Reading deleted files list: %s'), $fileList));
        $files      = \array_filter(\explode(\PHP_EOL, $this->manager->read('upgrade://' . $fileList)));
        $totalCount = \count($files);
        foreach ($files as $i => $file) {
            if ($data !== null && \is_callable($data)) {
                $data($i, $totalCount, $file);
            }
            $path = 'upgrade://' . Path::clean($file);
            if (!$this->manager->has($path)) {
                continue;
            }
            if ($this->manager->fileExists($path)) {
                $this->manager->delete($path);
                $this->progress->addDebug(\sprintf(\__('Deleted file %s'), $file));
            } else {
                $this->manager->deleteDirectory($path);
                $this->progress->addDebug(\sprintf(\__('Deleted directory %s'), $file));
            }
        }
        $this->manager->deleteDirectory('upgrade://' . \PFAD_INSTALL);
        $this->progress->addDebug(\sprintf(\__('Deleted directory %s'), \PFAD_INSTALL));
        $this->stopTiming();

        return $this->progress;
    }
}
