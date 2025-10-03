<?php

declare(strict_types=1);

namespace JTL\Widgets;

use JTL\Backend\Permissions;
use JTL\DB\SqlObject;
use JTL\Router\Controller\Backend\ActivationController;
use JTL\Shop;
use stdClass;

/**
 * Class UnlockRequestNotifier
 * @package JTL\Widgets
 */
class UnlockRequestNotifier extends AbstractWidget
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->setPermission(Permissions::UNLOCK_CENTRAL_VIEW);
        $sql          = '';
        $searchSQL    = new SqlObject();
        $groups       = [];
        $requestCount = 0;
        $controller   = new ActivationController(
            $this->getDB(),
            Shop::Container()->getCache(),
            Shop::Container()->getAlertService(),
            Shop::Container()->getAdminAccount(),
            Shop::Container()->getGetText()
        );

        $group                = new stdClass();
        $group->cGroupName    = \__('Customer reviews');
        $group->kRequestCount = \count($controller->getReviews($sql, $searchSQL));
        $groups[]             = $group;
        $requestCount         += $group->kRequestCount;

        $searchSQL->setOrder(' dZuletztGesucht DESC ');
        $group                = new stdClass();
        $group->cGroupName    = \__('Search queries');
        $group->kRequestCount = \count($controller->getSearchQueries($sql, $searchSQL));
        $groups[]             = $group;
        $requestCount         += $group->kRequestCount;

        $group                = new stdClass();
        $group->cGroupName    = \__('News comments');
        $group->kRequestCount = \count($controller->getNewsComments($sql, $searchSQL));
        $groups[]             = $group;
        $requestCount         += $group->kRequestCount;

        $searchSQL->setOrder(' tnewsletterempfaenger.dEingetragen DESC ');
        $group                = new stdClass();
        $group->cGroupName    = \__('Newsletter recipients');
        $group->kRequestCount = \count($controller->getNewsletterRecipients($sql, $searchSQL));
        $groups[]             = $group;
        $requestCount         += $group->kRequestCount;

        $this->getSmarty()->assign('groups', $groups)
            ->assign('requestCount', $requestCount);
    }

    /**
     * @inheritdoc
     */
    public function getContent(): string
    {
        return $this->getSmarty()->fetch('tpl_inc/widgets/widgetUnlockRequestNotifier.tpl');
    }
}
