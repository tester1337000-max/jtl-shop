<?php

declare(strict_types=1);

namespace JTL\Cron;

use JTL\Shop;
use stdClass;

/**
 * Class JobQueue
 * @package JTL\Cron
 */
class JobQueue
{
    public int $nLastArticleID = 0;

    public function __construct(
        public ?int $kJobQueue = null,
        public int $kCron = 0,
        public int $kKey = 0,
        public int $nLimitN = 0,
        public int $nLimitM = 0,
        public int $nInArbeit = 0,
        public string $cJobArt = '',
        public string $cTabelle = '',
        public string $cKey = '',
        public string $dStartZeit = 'NOW()',
        public ?string $dZuletztGelaufen = null
    ) {
    }

    public function getKJobQueue(): ?int
    {
        return $this->kJobQueue;
    }

    public function setKJobQueue(int $kJobQueue): self
    {
        $this->kJobQueue = $kJobQueue;

        return $this;
    }

    public function getKCron(): int
    {
        return $this->kCron;
    }

    public function setKCron(int $kCron): self
    {
        $this->kCron = $kCron;

        return $this;
    }

    public function getKKey(): int
    {
        return $this->kKey;
    }

    public function setKKey(int $kKey): self
    {
        $this->kKey = $kKey;

        return $this;
    }

    public function getNLimitN(): int
    {
        return $this->nLimitN;
    }

    public function setNLimitN(int $nLimitN): self
    {
        $this->nLimitN = $nLimitN;

        return $this;
    }

    public function getNLimitM(): int
    {
        return $this->nLimitM;
    }

    public function setNLimitM(int $nLimitM): self
    {
        $this->nLimitM = $nLimitM;

        return $this;
    }

    public function getNLastArticleID(): int
    {
        return $this->nLastArticleID;
    }

    public function setNLastArticleID(int $nLastArticleID): self
    {
        $this->nLastArticleID = $nLastArticleID;

        return $this;
    }

    public function getNInArbeit(): int
    {
        return $this->nInArbeit;
    }

    public function setNInArbeit(int $nInArbeit): self
    {
        $this->nInArbeit = $nInArbeit;

        return $this;
    }

    public function getCJobArt(): string
    {
        return $this->cJobArt;
    }

    public function setCJobArt(string $cJobArt): self
    {
        $this->cJobArt = $cJobArt;

        return $this;
    }

    public function getCTabelle(): string
    {
        return $this->cTabelle;
    }

    public function setCTabelle(string $cTabelle): self
    {
        $this->cTabelle = $cTabelle;

        return $this;
    }

    public function getCKey(): string
    {
        return $this->cKey;
    }

    public function setCKey(string $cKey): void
    {
        $this->cKey = $cKey;
    }

    public function getDStartZeit(): string
    {
        return $this->dStartZeit;
    }

    public function setDStartZeit(string $dStartZeit): self
    {
        $this->dStartZeit = $dStartZeit;

        return $this;
    }

    public function getDZuletztGelaufen(): string
    {
        return $this->dZuletztGelaufen ?? '_DBNULL_';
    }

    public function setDZuletztGelaufen(string $dZuletztGelaufen): self
    {
        $this->dZuletztGelaufen = $dZuletztGelaufen;

        return $this;
    }

    public function holeJobArt(): ?stdClass
    {
        if ($this->kKey > 0 && \mb_strlen($this->cTabelle) > 0) {
            return Shop::Container()->getDB()->select(
                $this->cTabelle,
                $this->cKey,
                $this->kKey
            );
        }

        return null;
    }

    public function speicherJobInDB(): int
    {
        if (
            $this->kKey > 0
            && $this->nLimitM > 0
            && \mb_strlen($this->cJobArt) > 0
            && \mb_strlen($this->cKey) > 0
            && \mb_strlen($this->cTabelle) > 0
            && \mb_strlen($this->dStartZeit) > 0
        ) {
            $ins                = new stdClass();
            $ins->cronID        = $this->kCron;
            $ins->foreignKeyID  = $this->kKey;
            $ins->tasksExecuted = $this->nLimitN;
            $ins->taskLimit     = $this->nLimitM;
            $ins->lastProductID = $this->nLastArticleID;
            $ins->isRunning     = $this->nInArbeit;
            $ins->jobType       = $this->cJobArt;
            $ins->tableName     = $this->cTabelle;
            $ins->foreignKey    = $this->cKey;
            $ins->startTime     = $this->dStartZeit;
            $ins->lastStart     = $this->dZuletztGelaufen ?? '_DBNULL_';

            return Shop::Container()->getDB()->insert('tjobqueue', $ins);
        }

        return 0;
    }

    public function updateJobInDB(): int
    {
        if ($this->kJobQueue > 0) {
            $upd                = new stdClass();
            $upd->cronID        = $this->kCron;
            $upd->foreignKeyID  = $this->kKey;
            $upd->tasksExecuted = $this->nLimitN;
            $upd->taskLimit     = $this->nLimitM;
            $upd->lastProductID = $this->nLastArticleID;
            $upd->isRunning     = $this->nInArbeit;
            $upd->jobType       = $this->cJobArt;
            $upd->tableName     = $this->cTabelle;
            $upd->foreignKey    = $this->cKey;
            $upd->startTime     = $this->dStartZeit;
            $upd->lastStart     = $this->dZuletztGelaufen ?? '_DBNULL_';

            return Shop::Container()->getDB()->update('tjobqueue', 'jobQueueID', (int)$this->kJobQueue, $upd);
        }

        return 0;
    }

    public function deleteJobInDB(): int
    {
        return $this->kJobQueue > 0
            ? Shop::Container()->getDB()->delete('tjobqueue', 'jobQueueID', (int)$this->kJobQueue)
            : 0;
    }
}
