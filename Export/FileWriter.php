<?php

declare(strict_types=1);

namespace JTL\Export;

use Exception;
use JTL\Helpers\Text;
use JTL\Smarty\ExportSmarty;

/**
 * Class FileWriter
 * @package JTL\Export
 */
class FileWriter implements ExportWriterInterface
{
    private string $tmpFileName;

    /**
     * @var resource|false
     */
    private $currentHandle;

    /**
     * @inheritdoc
     */
    public function __construct(
        private readonly Model $model,
        private readonly array $config,
        private readonly ?ExportSmarty $smarty = null
    ) {
        $this->tmpFileName = 'tmp_' . \basename($this->model->getFilename());
    }

    public function start(): void
    {
        $file                = \PFAD_ROOT . \PFAD_EXPORT . $this->tmpFileName;
        $this->currentHandle = @\fopen($file, 'ab');
        if ($this->currentHandle === false) {
            throw new Exception(\sprintf(\__('Cannot open export file %s.'), $file));
        }
    }

    /**
     * @inheritdoc
     */
    public function writeHeader(): int
    {
        $header = $this->smarty?->fetch('string:' . $this->model->getHeader()) ?? '';
        if ($this->currentHandle === false || \mb_strlen($header) === 0) {
            return 0;
        }
        $encoding = $this->model->getEncoding();
        if ($encoding === 'UTF-8') {
            \fwrite($this->currentHandle, "\xEF\xBB\xBF");
        }
        if ($encoding === 'UTF-8' || $encoding === 'UTF-8noBOM') {
            $header = Text::convertUTF8($header);
        }

        return \fwrite($this->currentHandle, $header . $this->getNewLine()) ?: 0;
    }

    /**
     * @inheritdoc
     */
    public function writeFooter(): int
    {
        $footer = $this->smarty?->fetch('string:' . $this->model->getFooter()) ?? '';
        if ($this->currentHandle === false || \mb_strlen($footer) === 0) {
            return 0;
        }
        $encoding = $this->model->getEncoding();
        if ($encoding === 'UTF-8' || $encoding === 'UTF-8noBOM') {
            $footer = Text::convertUTF8($footer);
        }

        return \fwrite($this->currentHandle, $footer) ?: 0;
    }

    /**
     * @inheritdoc
     */
    public function writeContent(string $data): int
    {
        if ($this->currentHandle === false) {
            return 0;
        }
        $utf8 = ($this->model->getEncoding() === 'UTF-8' || $this->model->getEncoding() === 'UTF-8noBOM');

        return \fwrite($this->currentHandle, ($utf8 ? Text::convertUTF8($data) : $data)) ?: 0;
    }

    /**
     * @inheritdoc
     */
    public function close(): bool
    {
        return $this->currentHandle !== null && $this->currentHandle !== false && \fclose($this->currentHandle);
    }

    /**
     * @inheritdoc
     */
    public function finish(): bool
    {
        if (
            $this->close() === true
            && \copy(
                \PFAD_ROOT . \PFAD_EXPORT . $this->tmpFileName,
                \PFAD_ROOT . \PFAD_EXPORT . $this->model->getFilename()
            )
        ) {
            \unlink(\PFAD_ROOT . \PFAD_EXPORT . $this->tmpFileName);

            return true;
        }

        return false;
    }

    private function cleanupFiles(string $fileName, string $fileNameSplit): ExportWriterInterface
    {
        if (\is_dir(\PFAD_ROOT . \PFAD_EXPORT) && ($dir = \opendir(\PFAD_ROOT . \PFAD_EXPORT)) !== false) {
            while (($fdir = \readdir($dir)) !== false) {
                if ($fdir !== $fileName && \str_contains($fdir, $fileNameSplit)) {
                    \unlink(\PFAD_ROOT . \PFAD_EXPORT . $fdir);
                }
            }
            \closedir($dir);
        }

        return $this;
    }

    public function deleteOldExports(): void
    {
        try {
            $path = $this->model->getSanitizedFilepath();
            if (\file_exists($path)) {
                \unlink($path);
            }
        } catch (Exception) {
        }
    }

    public function deleteOldTempFile(): void
    {
        if (\file_exists(\PFAD_ROOT . \PFAD_EXPORT . $this->tmpFileName)) {
            \unlink(\PFAD_ROOT . \PFAD_EXPORT . $this->tmpFileName);
        }
    }

    /**
     * @inheritdoc
     */
    public function split(): ExportWriterInterface
    {
        $path = $this->model->getSanitizedFilepath();
        $file = $this->model->getFilename();
        if ($this->model->getSplitSize() <= 0 || !\file_exists($path)) {
            return $this;
        }
        $fileCounter = 1;
        $splits      = [];
        $fileTypeIdx = \mb_strrpos($file, '.');
        // Dateiname splitten nach Name + Typ
        if ($fileTypeIdx !== false) {
            $splits[0] = \mb_substr($file, 0, $fileTypeIdx);
            $splits[1] = \mb_substr($file, $fileTypeIdx);
        } else {
            $splits[0] = $file;
        }
        // Ist die angelegte Datei größer als die Einstellung im Exportformat?
        \clearstatcache();
        $maxFileSize = $this->model->getSplitSize() * 1024 * 1024 - 102400;
        if (\filesize(\PFAD_ROOT . \PFAD_EXPORT . $file) >= $maxFileSize) {
            \sleep(2);
            $this->cleanupFiles($file, $splits[0]);
            $handle = \fopen($path, 'rb');
            if ($handle === false) {
                return $this;
            }
            $row                 = 1;
            $filesize            = 0;
            $this->currentHandle = \fopen($this->getFileName($splits, $fileCounter), 'wb');
            while (($content = \fgets($handle)) !== false) {
                if ($row > 1) {
                    $rowLen = \mb_strlen($content) + 2;
                    // Schwelle erreicht?
                    if ($filesize <= $maxFileSize) {
                        $this->writeContent($content);
                        $filesize += $rowLen;
                    } else {
                        // neue Datei
                        $this->writeFooter();
                        $this->close();
                        ++$fileCounter;
                        $this->currentHandle = \fopen($this->getFileName($splits, $fileCounter), 'wb');
                        $this->writeHeader();
                        $this->writeContent($content);
                        $filesize = $rowLen;
                    }
                } elseif ($row === 1) {
                    $this->writeHeader();
                }
                ++$row;
            }
            $this->close();
            \fclose($handle);
            \unlink($path);
        }

        return $this;
    }

    /**
     * @param array<int, string> $splits
     */
    private function getFileName(array $splits, int $fileCounter): string
    {
        $fn = \count($splits) > 1
            ? $splits[0] . $fileCounter . $splits[1]
            : $splits[0] . $fileCounter;

        return \PFAD_ROOT . \PFAD_EXPORT . $fn;
    }

    /**
     * @inheritdoc
     */
    public function getNewLine(): string
    {
        return ($this->config['exportformate_line_ending'] ?? 'LF') === 'LF' ? "\n" : "\r\n";
    }
}
