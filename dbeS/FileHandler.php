<?php

declare(strict_types=1);

namespace JTL\dbeS;

use JTL\Helpers\FileSystem;
use Psr\Log\LoggerInterface;
use ZipArchive;

/**
 * Class FileHandler
 * @package JTL\dbeS
 */
class FileHandler
{
    private const TEMP_DIR = \PFAD_ROOT . \PFAD_DBES . \PFAD_SYNC_TMP;

    private string $unzipPath = '';

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function __destruct()
    {
        if ($this->unzipPath !== '') {
            $this->removeTemporaryFiles($this->unzipPath, true);
        }
    }

    public function getUnzipPath(): string
    {
        return $this->unzipPath;
    }

    public function setUnzipPath(string $unzipPath): void
    {
        $this->unzipPath = $unzipPath;
    }

    /**
     * @param array<mixed>|null $data
     * @return string[]|null
     */
    public function getSyncFiles(?array $data = null): ?array
    {
        if (($zipFile = $this->checkFile($data)) === '') {
            return null;
        }
        $this->unzipPath = self::TEMP_DIR . \basename($zipFile) . '_' . \date('dhis') . '/';
        if (($syncFiles = $this->unzipSyncFiles($zipFile, $this->unzipPath)) === false) {
            $this->logger->error(
                'Error: Cannot extract zip file {file} to {target}',
                ['file' => $zipFile, 'target' => $this->unzipPath]
            );
            $this->removeTemporaryFiles($zipFile);
            $syncFiles = null;
        }

        return $syncFiles;
    }

    private function removeTemporaryFiles(string $file, bool $isDir = false): bool
    {
        if (\KEEP_SYNC_FILES === true) {
            return false;
        }

        return $isDir ? FileSystem::delDirRecursively($file) : \unlink($file);
    }

    /**
     * @return string[]|false
     */
    private function unzipSyncFiles(string $zipFile, string $targetPath): bool|array
    {
        $archive = new ZipArchive();
        if (($open = $archive->open($zipFile)) !== true) {
            $this->logger->error(
                'unzipSyncFiles: Kann Datei {file} nicht öffnen. ErrorCode: {err}',
                ['file' => $zipFile, 'err' => $open]
            );

            return false;
        }
        $filenames = [];
        if (\is_dir($targetPath) || (\mkdir($targetPath) && \is_dir($targetPath))) {
            for ($i = 0; $i < $archive->numFiles; ++$i) {
                $filenames[] = $targetPath . $archive->getNameIndex($i);
            }
            if ($archive->numFiles > 0 && !$archive->extractTo($targetPath)) {
                return false;
            }
            $archive->close();

            return \array_filter(
                \array_map(
                    static fn(string $file): ?string => \file_exists($file) ? $file : null,
                    $filenames
                )
            );
        }

        return false;
    }

    private function getErrorMessage(int $code): string
    {
        return match ($code) {
            \UPLOAD_ERR_INI_SIZE   => 'Dateigröße > upload_max_filesize directive in php.ini [1]',
            \UPLOAD_ERR_FORM_SIZE  => 'Dateigröße > MAX_FILE_SIZE [2]',
            \UPLOAD_ERR_PARTIAL    => 'Datei wurde nur zum Teil hochgeladen [3]',
            \UPLOAD_ERR_NO_FILE    => 'Es wurde keine Datei hochgeladen [4]',
            \UPLOAD_ERR_NO_TMP_DIR => 'Es fehlt ein TMP-Verzeichnis für Datei-Uploads! Bitte an Hoster wenden! [6]',
            \UPLOAD_ERR_CANT_WRITE => 'Datei konnte nicht auf Datenträger gespeichert werden! [7]',
            \UPLOAD_ERR_EXTENSION  => 'Dateiendung nicht akzeptiert, bitte an Hoster werden! [8]',
            default                => 'Fehler beim Datenaustausch - Datei kam nicht an oder Größe 0!',
        };
    }

    /**
     * @param array<mixed>|null $data
     * @return string
     */
    public function checkFile(?array $data = null): string
    {
        $files = $data ?? $_FILES;

        if (!isset($files['data'])) {
            return '';
        }
        if (!empty($files['data']['error']) || (isset($files['data']['size']) && $files['data']['size'] === 0)) {
            $this->logger->error(
                'ERROR: incoming: ' . $files['data']['name'] . ' size:' . $files['data']['size']
                . ' err:' . $files['data']['error']
            );
            $error = $this->getErrorMessage($files['data']['error'] ?? 0);
            \syncException($error . "\n" . \print_r($files, true), \FREIDEFINIERBARER_FEHLER);
        }
        $target = self::TEMP_DIR . \basename($files['data']['tmp_name']);
        \move_uploaded_file($files['data']['tmp_name'], $target);
        $files['data']['tmp_name'] = $target;

        return $target;
    }
}
