<?php

declare(strict_types=1);

namespace JTL\Filesystem;

use Exception;
use JTL\Path;
use JTL\Shop;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\MountManager;
use League\Flysystem\PathTraversalDetected;
use Symfony\Component\Finder\Finder;
use ZipArchive;

/**
 * Class Filesystem
 * @package JTL\Filesystem
 */
class Filesystem extends \League\Flysystem\Filesystem
{
    /**
     * @throws Exception
     */
    public function unzip(string $directory, string $path): bool
    {
        $directory   = Path::clean($directory);
        $location    = Path::clean($path, true);
        $zipArchive  = new ZipArchive();
        $directories = [];
        if (($code = $zipArchive->open($directory, ZipArchive::CHECKCONS)) !== true) {
            throw new Exception('Incompatible archive: ' . $directory, $code);
        }
        // Collect all directories to create
        for ($index = 0; $index < $zipArchive->numFiles; ++$index) {
            if (!$info = $zipArchive->statIndex($index)) {
                throw new Exception('Could not retrieve file from archive.');
            }
            if (\substr($info['name'], -1) === \DIRECTORY_SEPARATOR) {
                $directory = Path::removeTrailingSlash($info['name']);
            } elseif ($dirName = \dirname($info['name'])) {
                $directory = Path::removeTrailingSlash($dirName);
            }
            $directories[$directory] = $index;
        }

        // Flatten directory depths
        // ['/a', '/a/b', '/a/b/c'] => ['/a/b/c']
        foreach ($directories as $dir => $_) {
            $parent = \dirname($dir);
            if (\array_key_exists($parent, $directories)) {
                unset($directories[$parent]);
            }
        }

        $directories = \array_flip($directories);

        // Create location where to extract the archive
        $this->createDirectory($location);
        // Create required directories
        foreach ($directories as $dir) {
            $this->createDirectory(Path::combine($location, $dir));
        }

        unset($directories);

        // Copy files from archive
        for ($index = 0; $index < $zipArchive->numFiles; ++$index) {
            if (!$info = $zipArchive->statIndex($index)) {
                throw new Exception('Could not retrieve file from archive.');
            }

            // Directories are identified by trailing slash
            if (\str_ends_with($info['name'], '/')) {
                continue;
            }
            $contents = $zipArchive->getFromIndex($index);
            if ($contents === false) {
                throw new Exception('Could not extract file from archive.');
            }
            $file = Path::combine($location, $info['name']);
            $this->write($file, $contents);
        }
        $zipArchive->close();

        return true;
    }

    public function zip(Finder $finder, string $archive, ?callable $callback = null): bool
    {
        $localFS  = Shop::Container()->get(LocalFilesystem::class);
        $provider = new JTLZipArchiveProvider($archive);
        $manager  = new MountManager([
            'root' => $localFS,
            'zip'  => new Filesystem(new JTLZipArchiveAdapter($provider))
        ]);
        $count    = $finder->count();
        $index    = 0;
        foreach ($finder as $file) {
            $path = $file->getPathname();
            $pos  = \strpos($path, \PFAD_ROOT);
            if ($pos === 0) {
                $path = \substr_replace($path, '', $pos, \strlen(\PFAD_ROOT));
            }
            if ($file->getType() === 'dir') {
                $manager->createDirectory('zip://' . $path);
            } else {
                $manager->copy('root://' . $path, 'zip://' . $path);
            }
            if (\is_callable($callback)) {
                $callback($count, $index, $path);
                ++$index;
            }
        }
        $provider->createZipArchive()->close();

        return true;
    }

    public function zipDir(string $source, string $archive): bool
    {
        $realSource = \realpath($source);
        if (
            $realSource === false
            || !\str_contains($archive, '.zip')
            || !\str_starts_with($realSource, \realpath(\PFAD_ROOT) ?: 'invalid')
        ) {
            return false;
        }
        $manager = new MountManager([
            'root' => new Filesystem(new LocalFilesystemAdapter($realSource)),
            'zip'  => new Filesystem(new JTLZipArchiveAdapter(new JTLZipArchiveProvider($archive)))
        ]);
        foreach ($manager->listContents('root:///', true) as $item) {
            $path   = $item->path();
            $target = \str_replace('root://', '', $path);
            if ($item->isDir()) {
                $manager->createDirectory('zip://' . $target);
            } else {
                $manager->copy($path, 'zip://' . $target);
            }
        }

        return true;
    }

    public function normalizeToBasePath(string $path, string $basePath): string
    {
        $normalized = Path::clean($path);
        if (!\str_starts_with($normalized, $basePath)) {
            throw new PathTraversalDetected('Path traversal detected at path ' . $path);
        }

        return $normalized;
    }
}
