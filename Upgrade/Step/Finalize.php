<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Step;

use JTL\Shop;
use JTL\Smarty\BackendSmarty;

final class Finalize extends AbstractStep
{
    public function getTitle(): string
    {
        return \__('Finalizing...');
    }

    public function run(mixed $data = null): StepConfiguration
    {
        $this->startTiming();
        $this->clearCaches();
        $ins = (object)[
            'timestamp'    => 'now()',
            'version_from' => $this->progress->sourceVersion,
            'version_to'   => $this->progress->targetVersion,
            'backup_db'    => $this->progress->dbBackupFile ?? '_DBNULL_',
            'backup_fs'    => $this->progress->fsBackupFile ?? '_DBNULL_',
            'debug'        => '',//\implode(\PHP_EOL, $this->progress->logger->getDebug()),
            'warnings'     => \implode(\PHP_EOL, $this->progress->logger->getWarnings()),
            'errors'       => \implode(\PHP_EOL, $this->progress->logger->getErrors()),
            'logs'         => \implode(\PHP_EOL, $this->progress->logger->getInfo()),
        ];
        $this->db->insert('upgrade_log', $ins);
        $this->stopTiming();

        return $this->progress;
    }

    private function clearCaches(): void
    {
        $this->cache->flushAll();
        Shop::Smarty()->clearCompiledTemplate();
        (new BackendSmarty($this->db, $this->cache))->clearCompiledTemplate();
    }
}
