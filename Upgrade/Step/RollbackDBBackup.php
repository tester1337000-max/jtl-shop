<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Step;

use Ifsnop\Mysqldump\Mysqldump;

final class RollbackDBBackup extends AbstractStep
{
    public function getTitle(): string
    {
        return \__('Restoring database backup...');
    }

    public function run(mixed $data = null): StepConfiguration
    {
        $this->startTiming();
        $dbBackupFile = $this->progress->dbBackupFile;
        $unzippedFile = \PFAD_ROOT . \PFAD_DBES_TMP . 'tmp.sql';
        if (!$this->decompressGzipToFile($dbBackupFile, $unzippedFile)) {
            throw new StepFailedException(\__('Backup file could not be decompressed.'));
        }
        $connection = \sprintf('mysql:host=%s;dbname=%s', \DB_HOST, \DB_NAME);
        (new Mysqldump($connection, \DB_USER, \DB_PASS))->restore($unzippedFile);
        $this->progress->addInfo(\sprintf(\__('Database backup restored from %s.'), $dbBackupFile));
        $this->stopTiming();

        return $this->progress;
    }

    private function decompressGzipToFile(string $inputFile, string $outputFile): bool
    {
        $inputHandle  = \gzopen($inputFile, 'rb')
            ?: throw new StepFailedException(\sprintf(\__('Cannot open file %s'), $inputFile));
        $outputHandle = \fopen($outputFile, 'wb')
            ?: throw new StepFailedException(\sprintf(\__('Cannot open file %s'), $inputFile));
        \stream_set_write_buffer($outputHandle, 0);
        while (!\gzeof($inputHandle)) {
            $buffer = \gzread($inputHandle, 8192);
            if ($buffer === false) {
                throw new StepFailedException(\sprintf(\__('Cannot read from file %s'), $inputFile));
            }
            \fwrite($outputHandle, $buffer);
        }

        return \gzclose($inputHandle) && \fclose($outputHandle);
    }
}
