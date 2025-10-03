<?php

declare(strict_types=1);

namespace JTL\Filesystem;

use Generator;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\UnixVisibility\VisibilityConverter;
use League\Flysystem\ZipArchive\ZipArchiveProvider;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use League\MimeTypeDetection\MimeTypeDetector;
use Throwable;
use ZipArchive;

/**
 * Class JTLZipArchiveAdapter
 * @package JTL\Filesystem
 */
final class JTLZipArchiveAdapter implements FilesystemAdapter
{
    private PathPrefixer $pathPrefixer;

    private MimeTypeDetector $mimeTypeDetector;

    private VisibilityConverter $visibility;

    public function __construct(
        private readonly ZipArchiveProvider $zipArchiveProvider,
        string $root = '',
        ?MimeTypeDetector $mimeTypeDetector = null,
        ?VisibilityConverter $visibility = null
    ) {
        $this->pathPrefixer     = new PathPrefixer($root);
        $this->mimeTypeDetector = $mimeTypeDetector ?? new FinfoMimeTypeDetector();
        $this->visibility       = $visibility ?? new PortableVisibilityConverter();
    }

    /**
     * @inheritdoc
     */
    public function fileExists(string $path): bool
    {
        $archive = $this->zipArchiveProvider->createZipArchive();

        return $archive->locateName($this->pathPrefixer->prefixPath($path)) !== false;
    }

    /**
     * @inheritdoc
     */
    public function directoryExists(string $path): bool
    {
        $archive  = $this->zipArchiveProvider->createZipArchive();
        $location = $this->pathPrefixer->prefixDirectoryPath($path);

        return $archive->statName($location) !== false;
    }

    /**
     * @inheritdoc
     */
    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $this->ensureParentDirectoryExists($path, $config);
        } catch (Throwable $exception) {
            throw UnableToWriteFile::atLocation($path, 'creating parent directory failed', $exception);
        }
        $archive      = $this->zipArchiveProvider->createZipArchive();
        $prefixedPath = $this->pathPrefixer->prefixPath($path);
        if (!$archive->addFromString($prefixedPath, $contents)) {
            throw UnableToWriteFile::atLocation($path, 'writing the file failed');
        }
        /** @var string|null $visibility */
        $visibility = $config->get(Config::OPTION_VISIBILITY);
        $result     = $visibility === null || $this->setVisibilityAttribute($prefixedPath, $visibility, $archive);
        if ($result === false) {
            throw UnableToWriteFile::atLocation($path, 'setting visibility failed');
        }
    }

    /**
     * @inheritdoc
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        $streamContents = \stream_get_contents($contents);
        if ($streamContents === false) {
            throw UnableToWriteFile::atLocation($path, 'Could not get contents of given resource.');
        }
        $this->write($path, $streamContents, $config);
    }

    /**
     * @inheritdoc
     */
    public function read(string $path): string
    {
        $archive      = $this->zipArchiveProvider->createZipArchive();
        $contents     = $archive->getFromName($this->pathPrefixer->prefixPath($path));
        $statusString = $archive->getStatusString();
        if ($contents === false) {
            throw UnableToReadFile::fromLocation($path, $statusString);
        }

        return $contents;
    }

    /**
     * @inheritdoc
     */
    public function readStream(string $path)
    {
        $archive  = $this->zipArchiveProvider->createZipArchive();
        $resource = $archive->getStream($this->pathPrefixer->prefixPath($path));
        if ($resource === false) {
            $status = $archive->getStatusString();
            throw UnableToReadFile::fromLocation($path, $status);
        }
        $stream = \fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new \Exception('Could not open temporary stream.');
        }
        \stream_copy_to_stream($resource, $stream);
        \rewind($stream);
        \fclose($resource);

        return $stream;
    }

    /**
     * @inheritdoc
     */
    public function delete(string $path): void
    {
        $prefixedPath = $this->pathPrefixer->prefixPath($path);
        $zipArchive   = $this->zipArchiveProvider->createZipArchive();
        $success      = $zipArchive->locateName($prefixedPath) === false || $zipArchive->deleteName($prefixedPath);
        if (!$success) {
            throw UnableToDeleteFile::atLocation($path, $zipArchive->getStatusString());
        }
    }

    /**
     * @inheritdoc
     */
    public function deleteDirectory(string $path): void
    {
        $archive      = $this->zipArchiveProvider->createZipArchive();
        $prefixedPath = $this->pathPrefixer->prefixDirectoryPath($path);
        for ($i = $archive->numFiles; $i > 0; $i--) {
            if (($stats = $archive->statIndex($i)) === false) {
                continue;
            }
            $itemPath = $stats['name'];
            if ($prefixedPath === $itemPath || !\str_starts_with($itemPath, $prefixedPath)) {
                continue;
            }
            if (!$archive->deleteIndex($i)) {
                $statusString = $archive->getStatusString();
                throw UnableToDeleteDirectory::atLocation($path, $statusString);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function createDirectory(string $path, Config $config): void
    {
        try {
            $this->ensureDirectoryExists($path, $config);
        } catch (Throwable $exception) {
            throw UnableToCreateDirectory::dueToFailure($path, $exception);
        }
    }

    /**
     * @inheritdoc
     */
    public function setVisibility(string $path, string $visibility): void
    {
        $archive  = $this->zipArchiveProvider->createZipArchive();
        $location = $this->pathPrefixer->prefixPath($path);
        $stats    = $archive->statName($location) ?: $archive->statName($location . '/');
        if ($stats === false) {
            $statusString = $archive->getStatusString();
            throw UnableToSetVisibility::atLocation($path, $statusString);
        }
        if (!$this->setVisibilityAttribute($stats['name'], $visibility, $archive)) {
            $statusString1 = $archive->getStatusString();
            throw UnableToSetVisibility::atLocation($path, $statusString1);
        }
    }

    /**
     * @inheritdoc
     */
    public function visibility(string $path): FileAttributes
    {
        $opsys   = 0;
        $attr    = 0;
        $archive = $this->zipArchiveProvider->createZipArchive();
        $archive->getExternalAttributesName(
            $this->pathPrefixer->prefixPath($path),
            $opsys,
            $attr
        );
        if ($opsys !== ZipArchive::OPSYS_UNIX || $attr === null) {
            throw UnableToRetrieveMetadata::visibility($path);
        }

        return new FileAttributes(
            $path,
            null,
            $this->visibility->inverseForFile($attr >> 16)
        );
    }

    /**
     * @inheritdoc
     */
    public function mimeType(string $path): FileAttributes
    {
        try {
            $contents = $this->read($path);
            $mimetype = $this->mimeTypeDetector->detectMimeType($path, $contents);
        } catch (Throwable $exception) {
            throw UnableToRetrieveMetadata::mimeType($path, '', $exception);
        }
        if ($mimetype === null) {
            throw UnableToRetrieveMetadata::mimeType($path, 'Unknown.');
        }

        return new FileAttributes($path, null, null, null, $mimetype);
    }

    /**
     * @inheritdoc
     */
    public function lastModified(string $path): FileAttributes
    {
        $zipArchive   = $this->zipArchiveProvider->createZipArchive();
        $stats        = $zipArchive->statName($this->pathPrefixer->prefixPath($path));
        $statusString = $zipArchive->getStatusString();
        if ($stats === false) {
            throw UnableToRetrieveMetadata::lastModified($path, $statusString);
        }

        return new FileAttributes($path, null, null, $stats['mtime']);
    }

    /**
     * @inheritdoc
     */
    public function fileSize(string $path): FileAttributes
    {
        $archive      = $this->zipArchiveProvider->createZipArchive();
        $stats        = $archive->statName($this->pathPrefixer->prefixPath($path));
        $statusString = $archive->getStatusString();
        if ($stats === false) {
            throw UnableToRetrieveMetadata::fileSize($path, $statusString);
        }

        if ($this->isDirectoryPath($stats['name'])) {
            throw UnableToRetrieveMetadata::fileSize($path, "It's a directory.");
        }

        return new FileAttributes($path, $stats['size'], null, null);
    }

    /**
     * @inheritdoc
     */
    public function listContents(string $path, bool $deep): iterable
    {
        $archive  = $this->zipArchiveProvider->createZipArchive();
        $location = $this->pathPrefixer->prefixDirectoryPath($path);
        $items    = [];
        for ($i = 0; $i < $archive->numFiles; $i++) {
            $stats = $archive->statIndex($i);
            if ($stats === false) {
                continue;
            }
            $itemPath = $stats['name'];
            if (
                $location === $itemPath
                || ($deep && $location !== '' && !\str_starts_with($itemPath, $location))
                || ($deep === false && !$this->isAtRootDirectory($location, $itemPath))
            ) {
                continue;
            }

            $items[] = $this->isDirectoryPath($itemPath)
                ? new DirectoryAttributes(
                    $this->pathPrefixer->stripDirectoryPrefix($itemPath),
                    null,
                    $stats['mtime']
                )
                : new FileAttributes(
                    $this->pathPrefixer->stripPrefix($itemPath),
                    $stats['size'],
                    null,
                    $stats['mtime']
                );
        }

        return $this->yieldItemsFrom($items);
    }

    /**
     * @param DirectoryAttributes[]|FileAttributes[] $items
     * @return Generator
     */
    private function yieldItemsFrom(array $items): Generator
    {
        yield from $items;
    }

    /**
     * @inheritdoc
     */
    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->ensureParentDirectoryExists($destination, $config);
        } catch (Throwable $exception) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $exception);
        }

        $archive = $this->zipArchiveProvider->createZipArchive();
        $renamed = $archive->renameName(
            $this->pathPrefixer->prefixPath($source),
            $this->pathPrefixer->prefixPath($destination)
        );

        if ($renamed === false) {
            throw UnableToMoveFile::fromLocationTo($source, $destination);
        }
    }

    /**
     * @inheritdoc
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $readStream = $this->readStream($source);
            $this->writeStream($destination, $readStream, $config);
        } catch (Throwable $exception) {
            if (isset($readStream)) {
                @\fclose($readStream);
            }
            throw UnableToCopyFile::fromLocationTo($source, $destination, $exception);
        }
    }

    private function ensureParentDirectoryExists(string $path, Config $config): void
    {
        $dirname = \dirname($path);
        if ($dirname === '' || $dirname === '.') {
            return;
        }
        $this->ensureDirectoryExists($dirname, $config);
    }

    private function ensureDirectoryExists(string $dirname, Config $config): void
    {
        /** @var string|null $visibility */
        $visibility      = $config->get(Config::OPTION_DIRECTORY_VISIBILITY);
        $archive         = $this->zipArchiveProvider->createZipArchive();
        $prefixedDirname = $this->pathPrefixer->prefixDirectoryPath($dirname);
        $parts           = \array_filter(\explode('/', \trim($prefixedDirname, '/')));
        $dirPath         = '';
        foreach ($parts as $part) {
            $dirPath .= $part . '/';
            $info    = $archive->statName($dirPath);
            if ($info === false && $archive->addEmptyDir($dirPath) === false) {
                throw UnableToCreateDirectory::atLocation($dirname);
            }
            if ($visibility === null) {
                continue;
            }
            if (!$this->setVisibilityAttribute($dirPath, $visibility, $archive)) {
                throw UnableToCreateDirectory::atLocation($dirname, 'Unable to set visibility.');
            }
        }
    }

    private function isDirectoryPath(string $path): bool
    {
        return \str_ends_with($path, '/');
    }

    private function isAtRootDirectory(string $directoryRoot, string $path): bool
    {
        return $directoryRoot === (\rtrim(\dirname($path), '/') . '/');
    }

    private function setVisibilityAttribute(string $statsName, string $visibility, ZipArchive $archive): bool
    {
        $attr = $this->isDirectoryPath($statsName)
            ? $this->visibility->forDirectory($visibility)
            : $this->visibility->forFile($visibility);

        return $archive->setExternalAttributesName($statsName, ZipArchive::OPSYS_UNIX, $attr << 16);
    }
}
