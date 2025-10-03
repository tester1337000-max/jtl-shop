<?php

declare(strict_types=1);

namespace JTL\Widgets;

use JTL\Backend\Permissions;
use JTL\Helpers\Request;
use JTL\Router\Controller\Backend\AbstractBackendController;
use JTL\Router\Controller\Backend\CampaignController;
use JTL\Shop;

/**
 * Class Campaigns
 * @package JTL\Widgets
 */
class Campaigns extends AbstractWidget
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->setPermission(Permissions::STATS_CAMPAIGN_VIEW);

        $controller = new CampaignController(
            $this->getDB(),
            Shop::Container()->getCache(),
            Shop::Container()->getAlertService(),
            Shop::Container()->getAdminAccount(),
            Shop::Container()->getGetText()
        );
        $controller->setDefaultCampaignViewConfiguration();
        $campaigns           = AbstractBackendController::getCampaigns(true, false, $this->getDB());
        $campaignDefinitions = $controller->getDefinitions();
        $first               = \array_keys($campaigns);
        $first               = $first[0];
        $campaignID          = $campaigns[$first]->kKampagne;
        if (
            isset($_SESSION['jtl_widget_kampagnen']['kKampagne'])
            && $_SESSION['jtl_widget_kampagnen']['kKampagne'] > 0
        ) {
            $campaignID = (int)$_SESSION['jtl_widget_kampagnen']['kKampagne'];
        }
        if (Request::gInt('kKampagne') > 0) {
            $campaignID = Request::gInt('kKampagne');
        }
        $_SESSION['jtl_widget_kampagnen']['kKampagne'] = $campaignID;

        $stats = $controller->getDetailStats($campaignID, $campaignDefinitions);

        $this->getSmarty()->assign('kKampagne', $campaignID)
            ->assign('types', \array_keys($stats))
            ->assign('campaigns', $campaigns)
            ->assign('campaignDefinitions', $campaignDefinitions)
            ->assign('campaignStats', $stats);
    }

    /**
     * @inheritdoc
     */
    public function getContent(): string
    {
        return $this->getSmarty()->fetch('tpl_inc/widgets/widgetCampaigns.tpl');
    }
}
