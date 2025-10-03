<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Step;

use JTL\Filesystem\Filesystem;
use JTL\Shop;
use JTLShop\SemVer\Version;
use Symfony\Component\Finder\Finder;

final class CreateFSBackup extends AbstractStep
{
    public function getTitle(): string
    {
        return \__('Creating filesystem backup...');
    }

    private function getVersionString(): string
    {
        $version    = Version::parse(\APPLICATION_VERSION);
        $versionStr = $version->getMajor() . '-' . $version->getMinor() . '-' . $version->getPatch();
        if ($version->hasPreRelease()) {
            $preRelease = $version->getPreRelease();
            $versionStr .= '-' . $preRelease->getGreek();
            if ($preRelease->getReleaseNumber() > 0) {
                $versionStr .= '-' . $preRelease->getReleaseNumber();
            }
        }

        return $versionStr;
    }

    public function run(mixed $data = null): StepConfiguration
    {
        $this->startTiming();
        $excludes = [];
        /** @var Filesystem $fileSystem */
        $fileSystem    = Shop::Container()->get(Filesystem::class);
        $backupedFiles = [];
        $random        = Shop::Container()->getCryptoService()->randomString(12);
        $archive       = \PFAD_ROOT . \PFAD_EXPORT_BACKUP . \date('YmdHis') . '_file_' . $random . '_backup.zip';
        $baseDirs      = [
            \PFAD_ROOT . \PFAD_ADMIN,
            \PFAD_ROOT . \PFAD_DBES,
            \PFAD_ROOT . \PFAD_INCLUDES,
            \PFAD_ROOT . \PLUGIN_DIR,
            \PFAD_ROOT . \PFAD_TEMPLATES
        ];

        $finder = Finder::create()
            ->ignoreVCS(true)
            ->ignoreDotFiles(false)
            ->exclude($this->getExcludes($excludes))
            ->in($baseDirs)
            ->append($this->getShopFiles());
        if ($data === null || !\is_callable($data)) {
            $data = static function ($count, $index, $path) use (&$backupedFiles): void {
                $backupedFiles[] = $path;
            };
        }
        $fileSystem->zip($finder, $archive, static function ($count, $index, $path) use (&$data): void {
            $data($count, $index, $path);
        });
        $this->progress->fsBackupFile = $archive;
        $this->progress->addInfo(\sprintf(\__('Created filesystem backup %s'), $archive));
        $this->stopTiming();

        return $this->progress;
    }

    /**
     * @param string[] $excludes
     * @return string[]
     */
    private function getExcludes(array $excludes): array
    {
        return \array_merge(
            [
                'admin/templates_c',
                'export',
                'templates_c',
                'dbeS/tmp',
                'dbeS/logs',
                'includes/plugins',
                'install',
                'media',
                'mediafiles',
                'docs',
                'downloads',
                'uploads'
            ],
            $excludes
        );
    }

    /**
     * @return string[]
     */
    private function getShopFiles(): array
    {
        $src = \PFAD_ROOT . \PFAD_ADMIN . \PFAD_INCLUDES . \PFAD_SHOPMD5 . $this->getVersionString() . '.csv';
        if (!\file_exists($src)) {
            throw new StepFailedException(\sprintf(\__('Cannot find file %s'), $src));
        }
        $shopFiles = \explode("\n", \file_get_contents($src) ?: '');
        $include   = [];
        foreach ($shopFiles as $row) {
            $data = \explode(';', $row);
            if (\count($data) !== 2) {
                continue;
            }
            $filename = $data[1];
            if (\str_starts_with($filename, 'admin') || \str_starts_with($filename, 'includes')) {
                continue;
            }
            if (!\file_exists(\PFAD_ROOT . $filename)) {
                $this->progress->addWarning(\sprintf(\__('Not backing up missing file %s'), $filename));
                continue;
            }
            $include[] = \PFAD_ROOT . $filename;
        }

        return $include;
    }
}
