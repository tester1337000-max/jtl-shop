<?php

declare(strict_types=1);

namespace JTL\Backend;

use Exception;
use JTL\Filesystem\Filesystem;
use JTL\Shop;
use JTLShop\SemVer\Version;
use stdClass;
use Symfony\Component\Finder\Finder;

use function Functional\map;

/**
 * Class FileCheck
 * @package JTL\Backend
 */
class FileCheck
{
    public const ERROR_RESULT_NO_ARRAY = 4;

    public const ERROR_NO_HASHES_FOUND = 3;

    public const ERROR_INPUT_FILE_MISSING = 2;

    public const OK = 1;

    /**
     * @param stdClass[] $result
     */
    public function validateCsvFile(string $hashFile, array &$result, int &$errors, string $prefix = \PFAD_ROOT): int
    {
        if (!\file_exists($hashFile)) {
            return self::ERROR_INPUT_FILE_MISSING;
        }
        $hashes = \file_get_contents($hashFile);
        if ($hashes === false || \mb_strlen($hashes) === 0) {
            return self::ERROR_NO_HASHES_FOUND;
        }
        $shopFiles = \explode("\n", $hashes);
        if (\count($shopFiles) === 0) {
            return self::OK;
        }
        $errors = 0;
        \array_multisort($shopFiles);
        foreach ($shopFiles as $shopFile) {
            if (\mb_strlen($shopFile) === 0) {
                continue;
            }
            if (\count(\explode(';', $shopFile)) === 1) {
                if (\file_exists($prefix . $shopFile)) {
                    $mtime    = \filemtime($prefix . $shopFile);
                    $result[] = (object)[
                        'name'         => $shopFile,
                        'lastModified' => \date('d.m.Y H:i:s', $mtime ?: null)
                    ];
                    $errors++;
                }
            } else {
                [$hash, $file] = \explode(';', $shopFile);

                $currentHash = '';
                $path        = $prefix . $file;
                if (\file_exists($path)) {
                    $currentHash = \md5_file($path);
                }
                if ($currentHash !== $hash) {
                    $mtime    = \file_exists($path) ? \filemtime($path) : 0;
                    $result[] = (object)[
                        'name'         => $file,
                        'lastModified' => \date('d.m.Y H:i:s', $mtime ?: null)
                    ];
                    $errors++;
                }
            }
        }

        return self::OK;
    }

    public function getVersionString(string $targetVersion = \APPLICATION_VERSION): string
    {
        $version    = Version::parse($targetVersion);
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

    /**
     * @param stdClass[] $orphanedFiles
     */
    public function deleteOrphanedFiles(array &$orphanedFiles, string $backupFile): int
    {
        $fs     = Shop::Container()->get(Filesystem::class);
        $count  = 0;
        $files  = [];
        $dirs   = [];
        $finder = new Finder();
        $finder->append(map($orphanedFiles, fn(stdClass $e): string => \PFAD_ROOT . $e->name));
        foreach ($finder as $file) {
            if ($file->getType() === 'dir') {
                $dirs[] = $file->getPathname();
            } else {
                $files[] = $file->getPathname();
            }
        }
        $zipFinder = new Finder();
        $zipFinder->in($dirs);
        $zipFinder->append($files);
        try {
            $fs->zip($zipFinder, $backupFile);
        } catch (Exception) {
            return -1;
        }
        foreach ($finder as $file) {
            try {
                $fsPath = \mb_substr($file->getPathname(), \mb_strlen(\PFAD_ROOT));
                if ($file->getType() === 'dir') {
                    $fs->deleteDirectory($fsPath);
                } else {
                    $fs->delete($fsPath);
                }
                unset($orphanedFiles[$count]);
                ++$count;
            } catch (Exception) {
            }
        }

        return $count;
    }

    public function generateBashScript(): string
    {
        return '#!/bin/bash
base="' . \PFAD_ROOT . '"
source=$base"' . \PFAD_ADMIN . \PFAD_INCLUDES . \PFAD_SHOPMD5 . 'deleted_files_' . $this->getVersionString() . '.csv"
if [ -f $source ]
then
    while IFS= read -r line
    do
        file=$base$line
        if [ -f $file ]
        then
            echo "deleting $file"
            rm -rf "$file"
        fi
    done <"$source"
else
    echo "$source does not exist!"
fi';
    }
}
