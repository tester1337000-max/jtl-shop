<?php

declare(strict_types=1);

namespace JTL\Export\Exporter;

use DateTime;
use InvalidArgumentException;
use JTL\Cron\QueueEntry;
use JTL\Export\AsyncCallback;
use JTL\Plugin\Helper as PluginHelper;
use JTL\Plugin\State;
use Psr\Http\Message\ResponseInterface;
use stdClass;

/**
 * Class PluginExporter
 * @package JTL\Export\Exporter
 */
class PluginExporter extends AbstractExporter
{
    public function start(QueueEntry $queueEntry, ?int $max = null)
    {
        $this->prepare($queueEntry, $max);
        $pluginID = $this->model?->getPluginID();
        try {
            if ($pluginID === null) {
                throw new InvalidArgumentException('Plugin ID not found');
            }
            $loader  = PluginHelper::getLoaderByPluginID($pluginID, $this->db);
            $oPlugin = $loader->init($pluginID);
            if ($this->model === null) {
                throw new InvalidArgumentException('Export model not found');
            }
        } catch (InvalidArgumentException $e) {
            $this->logger->error($e->getMessage());
            if ($this->isCron) {
                return false;
            }

            return $this->quit($e->getMessage());
        }
        if ($oPlugin->getState() !== State::ACTIVATED) {
            if ($this->isCron) {
                $this->logger->notice('Plugin disabled');

                return false;
            }

            return $this->quit('Plugin disabled');
        }
        $this->logger->notice(
            'Starting plugin exportformat "{nme}" for language {lid}, currency {cur}'
            . ' and customer group {cid} with caching {cie}',
            [
                'nme' => $this->model->getName(),
                'lid' => $this->model->getLanguageID(),
                'cur' => $this->model->getCurrency()->getName(),
                'cid' => $this->model->getCustomerGroupID(),
                'cie' => ($this->getCache()->isActive() && $this->model->getUseCache())
                    ? 'enabled'
                    : 'disabled'
            ]
        );
        if ($this->isCron === true) {
            global $oJobQueue;
            $oJobQueue = $queueEntry;
        } else {
            global $queue;
            $queue = $queueEntry;
        }
        global $exportformat, $ExportEinstellungen, $started;
        $exportformat                   = new stdClass();
        $exportformat->kKundengruppe    = $this->model->getCustomerGroupID();
        $exportformat->kExportformat    = $this->model->getId();
        $exportformat->kSprache         = $this->model->getLanguageID();
        $exportformat->kWaehrung        = $this->model->getCurrencyID();
        $exportformat->kKampagne        = $this->model->getCampaignID();
        $exportformat->kPlugin          = $this->model->getPluginID();
        $exportformat->cName            = $this->model->getName();
        $exportformat->cDateiname       = $this->model->getFilename();
        $exportformat->cKopfzeile       = $this->model->getHeader();
        $exportformat->cContent         = $this->model->getContent();
        $exportformat->cFusszeile       = $this->model->getFooter();
        $exportformat->cKodierung       = $this->model->getEncoding();
        $exportformat->nSpecial         = $this->model->getIsSpecial();
        $exportformat->nVarKombiOption  = $this->model->getVarcombOption();
        $exportformat->nSplitgroesse    = $this->model->getSplitSize();
        $exportformat->dZuletztErstellt = $this->model->getDateLastCreated();
        $exportformat->nUseCache        = $this->model->getUseCache();
        $exportformat->max              = $this->max;
        $exportformat->async            = $this->isAsync;
        // needed by Google Shopping export format plugin
        $exportformat->tkampagne_cParameter = $this->model->getCampaignParameter();
        $exportformat->tkampagne_cWert      = $this->model->getCampaignValue();
        // needed for plugin exports
        $ExportEinstellungen = $this->getConfig();
        $exportFile          = $oPlugin->getPaths()->getExportPath()
            . \str_replace(\PLUGIN_EXPORTFORMAT_CONTENTFILE, '', $this->model->getContent());
        if (!\file_exists($exportFile)) {
            $this->logger->error('Export file not found: {file}', ['file' => $exportFile]);

            return $this->quit('Export file not found');
        }
        include $exportFile;
        $this->model->setDateLastCreated(new DateTime());
        $this->model->save(['dateLastCreated']);
        if ($this->isCron === false) {
            return $this->quit();
        }

        return !\is_bool($started) || !$started;
    }

    private function quit(?string $error = null): ResponseInterface
    {
        $cb = new AsyncCallback();
        $cb->setProductCount(0);
        $cb->setError($error);

        return $this->syncReturn($cb);
    }
}
