<?php

declare(strict_types=1);

namespace JTL\dbeS;

use Exception;
use JTL\Export\Exporter\Factory;
use JTL\Export\Model;
use JTL\Language\LanguageHelper;
use JTL\Shop;
use stdClass;

/**
 * Class SyncCronjob
 * @package JTL\dbeS
 */
class SyncCronjob extends NetSyncHandler
{
    protected function request(int $request): void
    {
        require_once \PFAD_ROOT . \PFAD_ADMIN . \PFAD_INCLUDES . 'smartyinclude.php';
        switch ($request) {
            case NetSyncRequest::CRONJOBSTATUS:
                $exports = $this->getCronExports();
                foreach ($exports as &$job) {
                    $job = new CronjobStatus(
                        $job->kCron,
                        $job->cName,
                        $job->dStart_de,
                        $job->frequency,
                        (int)($job->oJobQueue->tasksExecuted ?? 0),
                        (int)($job->productCount ?? 0),
                        $job->dLetzterStart_de,
                        $job->dNaechsterStart_de
                    );
                }
                unset($job);

                self::throwResponse(NetSyncResponse::OK, $exports);
            // no break since throwResponse() will exit
            case NetSyncRequest::CRONJOBHISTORY:
                $queues = $this->getExportQueues(24 * 7);
                if (\is_array($queues)) {
                    foreach ($queues as &$job) {
                        $job = new CronjobHistory(
                            $job->cName,
                            $job->cDateiname,
                            (int)$job->nLimitN,
                            $job->dZuletztGelaufen_DE
                        );
                    }
                    unset($job);
                }

                self::throwResponse(NetSyncResponse::OK, $queues);
            // no break since throwResponse() will exit

            case NetSyncRequest::CRONJOBTRIGGER:
                $bCronManuell = true;
                require_once \PFAD_ROOT . \PFAD_INCLUDES . 'cron_inc.php';

                self::throwResponse(NetSyncResponse::OK, true);
            // no break since throwResponse() will exit
        }
    }

    /**
     * @return stdClass[]
     * @former holeExportformatCron()
     * @since 5.2.0
     */
    private function getCronExports(): array
    {
        $exports = $this->db->getObjects(
            "SELECT texportformat.*, tcron.cronID, tcron.frequency, tcron.startDate, 
            DATE_FORMAT(tcron.startDate, '%d.%m.%Y %H:%i') AS dStart_de, tcron.lastStart, 
            DATE_FORMAT(tcron.lastStart, '%d.%m.%Y %H:%i') AS dLetzterStart_de,
            DATE_FORMAT(COALESCE(tcron.nextStart, tcron.startDate), '%d.%m.%Y %H:%i') AS dNaechsterStart_de
            FROM texportformat
            JOIN tcron 
                ON tcron.jobType = 'exportformat'
                AND tcron.foreignKeyID = texportformat.kExportformat
            ORDER BY tcron.startDate DESC"
        );
        $factory = new Factory($this->db, Shop::Container()->getLogService(), Shop::Container()->getCache());
        foreach ($exports as $export) {
            $export->kExportformat      = (int)$export->kExportformat;
            $export->kKundengruppe      = (int)$export->kKundengruppe;
            $export->kSprache           = (int)$export->kSprache;
            $export->kWaehrung          = (int)$export->kWaehrung;
            $export->kKampagne          = (int)$export->kKampagne;
            $export->kPlugin            = (int)$export->kPlugin;
            $export->nSpecial           = (int)$export->nSpecial;
            $export->nVarKombiOption    = (int)$export->nVarKombiOption;
            $export->nSplitgroesse      = (int)$export->nSplitgroesse;
            $export->nUseCache          = (int)$export->nUseCache;
            $export->nFehlerhaft        = (int)$export->nFehlerhaft;
            $export->cronID             = (int)$export->cronID;
            $export->frequency          = (int)$export->frequency;
            $export->cAlleXStdToDays    = $this->getFrequency($export->frequency);
            $export->frequencyLocalized = $export->cAlleXStdToDays;

            $exporter = $factory->getExporter($export->kExportformat);
            $model    = Model::load(['id' => $export->kExportformat], $this->db);
            $exporter->initialize($export->kExportformat, $model, false, false);
            try {
                $export->Sprache = Shop::Lang()->getLanguageByID($export->kSprache);
            } catch (Exception) {
                $export->Sprache = LanguageHelper::getDefaultLanguage();
                $export->Sprache->setLocalizedName('???');
                $export->Sprache->setId(0);
                $export->nFehlerhaft = 1;
            }
            $export->Waehrung     = $this->db->select(
                'twaehrung',
                'kWaehrung',
                $export->kWaehrung
            );
            $export->Kundengruppe = $this->db->select(
                'tkundengruppe',
                'kKundengruppe',
                $export->kKundengruppe
            );
            $export->oJobQueue    = $this->db->getSingleObject(
                "SELECT *, DATE_FORMAT(lastStart, '%d.%m.%Y %H:%i') AS dZuletztGelaufen_de 
                    FROM tjobqueue 
                    WHERE cronID = :id",
                ['id' => $export->cronID]
            );
            $export->productCount = $exporter->getTotalCount();
        }

        return $exports;
    }

    /**
     * @return stdClass[]|bool
     * @former holeExportformatQueueBearbeitet()
     * @since 5.2.0
     */
    private function getExportQueues(int $hours = 24): array|bool
    {
        $languageID = (int)($_SESSION['kSprache'] ?? 0);
        if (!$languageID) {
            $tmp = LanguageHelper::getDefaultLanguage();
            if ($tmp !== null && $tmp->getId() > 0) {
                $languageID = $tmp->getId();
            } else {
                return false;
            }
        }
        $languages = LanguageHelper::getAllLanguages(1);
        $queues    = $this->db->getObjects(
            "SELECT texportformat.cName, texportformat.cDateiname, texportformatqueuebearbeitet.*,
            DATE_FORMAT(texportformatqueuebearbeitet.dZuletztGelaufen, '%d.%m.%Y %H:%i') AS dZuletztGelaufen_DE,
            tsprache.cNameDeutsch AS cNameSprache, tkundengruppe.cName AS cNameKundengruppe,
            twaehrung.cName AS cNameWaehrung
            FROM texportformatqueuebearbeitet
            JOIN texportformat
                ON texportformat.kExportformat = texportformatqueuebearbeitet.kExportformat
                AND texportformat.kSprache = :lid
            JOIN tsprache
                ON tsprache.kSprache = texportformat.kSprache
            JOIN tkundengruppe
                ON tkundengruppe.kKundengruppe = texportformat.kKundengruppe
            JOIN twaehrung
                ON twaehrung.kWaehrung = texportformat.kWaehrung
            WHERE DATE_SUB(NOW(), INTERVAL :hrs HOUR) < texportformatqueuebearbeitet.dZuletztGelaufen
            ORDER BY texportformatqueuebearbeitet.dZuletztGelaufen DESC",
            ['lid' => $languageID, 'hrs' => $hours]
        );
        foreach ($queues as $exportFormat) {
            $exportFormat->name      = $languages[$languageID]->getLocalizedName();
            $exportFormat->kJobQueue = (int)$exportFormat->kJobQueue;
            $exportFormat->nLimitN   = (int)$exportFormat->nLimitN;
            $exportFormat->nLimitM   = (int)$exportFormat->nLimitM;
            $exportFormat->nInArbeit = (int)$exportFormat->nInArbeit;
        }

        return $queues;
    }

    /**
     * @former rechneUmAlleXStunden()
     * @since 5.2.0
     */
    private function getFrequency(int $hours): false|string
    {
        if ($hours <= 0) {
            return false;
        }
        if ($hours > 24) {
            $res = \round($hours / 24);
            if ($res >= 365) {
                $res /= 365;
                if ($res === 1.0) {
                    $res .= \__('year');
                } else {
                    $res .= \__('years');
                }
            } elseif ($res === 1.0) {
                $res .= \__('day');
            } else {
                $res .= \__('days');
            }
        } elseif ($hours > 1) {
            $res = $hours . \__('hours');
        } else {
            $res = $hours . \__('hour');
        }

        return $res;
    }
}
