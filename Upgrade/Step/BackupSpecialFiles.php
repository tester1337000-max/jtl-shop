<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Step;

final class BackupSpecialFiles extends AbstractStep
{
    public const SPECIAL_FILES = [
        '.htaccess',
        'robots.txt',
        'admin/.htaccess',
        'shopinfo.xml',
        'rss.xml'
    ];

    public function getTitle(): string
    {
        return \__('Backing up special files...');
    }

    public function run(mixed $data = null): StepConfiguration
    {
        $this->startTiming();
        foreach (self::SPECIAL_FILES as $file) {
            $this->manager->copy('root://' . $file, 'root://' . $file . '.bak');
            $this->progress->logger->info(\sprintf(\__('Backed up %s to %s'), $file, $file . '.bak'));
        }
        $this->stopTiming();

        return $this->progress;
    }

    public function restore(string $file): void
    {
        if (!\in_array($file, self::SPECIAL_FILES, true)) {
            throw new StepFailedException(\__('Invalid file name'));
        }
        $backup = $file . '.bak';
        if (!$this->manager->fileExists('root://' . $backup)) {
            throw new StepFailedException(\__('Backup file not found'));
        }
        $this->manager->copy('root://' . $backup, 'root://' . $file);
    }
}
