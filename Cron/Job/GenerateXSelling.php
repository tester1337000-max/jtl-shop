<?php

declare(strict_types=1);

namespace JTL\Cron\Job;

use JTL\Cron\Job;
use JTL\Cron\JobInterface;
use JTL\Cron\QueueEntry;
use JTL\Shop;
use stdClass;

/**
 * Class GenerateXSelling
 * @package JTL\Cron\Job
 */
class GenerateXSelling extends Job
{
    private int $xsellingCombiMax = 0;

    private int $xsellingCombiCount = 1;

    private bool $useStockFilter = false;

    /**
     * @inheritdoc
     */
    public function hydrate(object $data): self
    {
        parent::hydrate($data);
        if (\JOBQUEUE_LIMIT_M_XSELL > 0) {
            $this->setLimit(\JOBQUEUE_LIMIT_M_XSELL);
        }
        $this->xsellingCombiCount = (int)(Shop::getSettingValue(
            \CONF_ARTIKELDETAILS,
            'artikeldetails_xselling_combi_count'
        ) ?? 1);
        $this->xsellingCombiMax   = (int)(Shop::getSettingValue(
            \CONF_ARTIKELDETAILS,
            'artikeldetails_xselling_combi_max'
        ) ?? 0);
        if ($this->xsellingCombiMax < 0) {
            $this->xsellingCombiMax = (int)(Shop::getSettingValue(
                \CONF_ARTIKELDETAILS,
                'artikeldetails_xselling_kauf_anzahl'
            ) ?? 10);
        }
        if ($this->xsellingCombiMax === 0 && \JOBQUEUE_LIMIT_M_XSELL_ALL > 0) {
            $this->setLimit(\JOBQUEUE_LIMIT_M_XSELL_ALL);
        }
        $stockFilter          = (int)(Shop::getSettingValue(
            \CONF_GLOBAL,
            'artikel_artikelanzeigefilter'
        ) ?? \EINSTELLUNGEN_ARTIKELANZEIGEFILTER_ALLE);
        $this->useStockFilter = ($stockFilter === \EINSTELLUNGEN_ARTIKELANZEIGEFILTER_LAGER
            || $stockFilter === \EINSTELLUNGEN_ARTIKELANZEIGEFILTER_LAGERNULL);

        return $this;
    }

    /**
     * @param object{limitFrom: int, limit: int, combiCount?: int}&stdClass $params
     */
    private function generateQuery(stdClass $params): string
    {
        $params->combiCount = $this->xsellingCombiCount;

        return 'INSERT INTO cross_selling_view (
                    SELECT xsell.kArtikel, xsell.kXSellArtikel, nAnzahl
                    FROM txsellkauf AS xsell
                    INNER JOIN tartikel t1 ON xsell.kArtikel = t1.kArtikel
                        ' . ($this->useStockFilter ? 'AND (t1.cLagerBeachten != \'Y\'
                            OR t1.fLagerbestand > 0
                            OR t1.cLagerKleinerNull = \'Y\')' : '') . '
                    INNER JOIN tartikel t2 ON xsell.kXSellArtikel = t2.kArtikel
                        ' . ($this->useStockFilter ? 'AND (t2.cLagerBeachten != \'Y\'
                            OR t2.fLagerbestand > 0
                            OR t2.cLagerKleinerNull = \'Y\')' : '') . '
                    WHERE xsell.nAnzahl >= :combiCount
                    ORDER BY xsell.kArtikel, xsell.kXSellArtikel
                    LIMIT :limitFrom, :limit
                )';
    }

    /**
     * @param object{limitFrom: int, limit: int, combiCount?: int, combiMax?: int}&stdClass $params
     */
    private function generateQueryLimitCombi(stdClass $params): string
    {
        $params->combiCount = $this->xsellingCombiCount;
        $params->combiMax   = $this->xsellingCombiMax;
        $this->db->executeQuery('SET @a:=0;SET @b:=0;');

        return 'INSERT INTO cross_selling_view (
                    SELECT xsell.kArtikel, xsell.kXSellArtikel, nAnzahl
                    FROM (
                        SELECT x1.*, IF(@b != x1.kArtikel, @a:=0, @a:=@a+1), @b:=x1.kArtikel, @a AS pos
                        FROM txsellkauf x1
                        ORDER BY x1.kArtikel, x1.nAnzahl DESC
                    ) AS xsell
                    INNER JOIN tartikel t1 ON xsell.kArtikel = t1.kArtikel
                        ' . ($this->useStockFilter ? 'AND (t1.cLagerBeachten != \'Y\'
                            OR t1.fLagerbestand > 0
                            OR t1.cLagerKleinerNull = \'Y\')' : '') . '
                    INNER JOIN tartikel t2 ON xsell.kXSellArtikel = t2.kArtikel
                        ' . ($this->useStockFilter ? 'AND (t2.cLagerBeachten != \'Y\'
                            OR t2.fLagerbestand > 0
                            OR t2.cLagerKleinerNull = \'Y\')' : '') . '
                    WHERE xsell.pos < :combiMax
                      AND xsell.nAnzahl >= :combiCount
                    ORDER BY xsell.kArtikel, xsell.kXSellArtikel
                    LIMIT :limitFrom, :limit
                )';
    }

    private function generateXSelling(int $index): int
    {
        if ($index === 0) {
            $this->db->executeQuery('TRUNCATE TABLE cross_selling_view');
        }
        $params = (object)['limitFrom' => $index, 'limit' => $this->getLimit()];
        $sql    = $this->xsellingCombiMax === 0
            ? $this->generateQuery($params)
            : $this->generateQueryLimitCombi($params);

        $res = $this->db->getAffectedRows($sql, (array)$params);
        $this->db->executeQuery('ANALYZE TABLE cross_selling_view');

        return $res;
    }

    /**
     * @inheritdoc
     */
    public function start(QueueEntry $queueEntry): JobInterface
    {
        parent::start($queueEntry);
        $this->logger->debug('Generating cross selling - max. {cnt}', ['cnt' => $this->getLimit()]);

        $executed = $this->generateXSelling($queueEntry->tasksExecuted);

        $queueEntry->tasksExecuted += $executed;
        $this->setExecuted($queueEntry->tasksExecuted);
        $this->setFinished($executed < $this->getLimit());

        return $this;
    }
}
