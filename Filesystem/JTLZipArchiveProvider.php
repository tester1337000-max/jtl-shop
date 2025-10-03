<?php

declare(strict_types=1);

namespace JTL\Filesystem;

use League\Flysystem\ZipArchive\UnableToCreateParentDirectory;
use League\Flysystem\ZipArchive\UnableToOpenZipArchive;
use League\Flysystem\ZipArchive\ZipArchiveProvider;
use ZipArchive;

/**
 * Class JTLZipArchiveProvider
 * @package JTL\Filesystem
 */
class JTLZipArchiveProvider implements ZipArchiveProvider
{
    private bool $parentDirectoryCreated = false;

    private ?ZipArchive $archive = null;

    public function __construct(
        private readonly string $filename,
        private readonly int $localDirectoryPermissions = 0700,
        private readonly int $mode = ZipArchive::CREATE
    ) {
    }

    public function createZipArchive(): ZipArchive
    {
        if ($this->parentDirectoryCreated !== true) {
            $this->parentDirectoryCreated = true;
            $this->createParentDirectoryForZipArchive($this->filename);
        }

        return $this->openZipArchive();
    }

    private function createParentDirectoryForZipArchive(string $fullPath): void
    {
        $dirname = \dirname($fullPath);
        if (\is_dir($dirname) || @\mkdir($dirname, $this->localDirectoryPermissions, true) || \is_dir($dirname)) {
            return;
        }
        if (!\is_dir($dirname)) {
            throw UnableToCreateParentDirectory::atLocation($fullPath, \error_get_last()['message'] ?? '');
        }
    }

    private function openZipArchive(): ZipArchive
    {
        $success = true;
        if ($this->archive === null) {
            $this->archive = new ZipArchive();
            $success       = $this->archive->open($this->filename, $this->mode);
        }

        if ($success !== true) {
            throw UnableToOpenZipArchive::atLocation($this->filename, $this->archive->getStatusString() ?: '');
        }

        return $this->archive;
    }
}
