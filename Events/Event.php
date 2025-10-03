<?php

declare(strict_types=1);

namespace JTL\Events;

use MyCLabs\Enum\Enum;

/**
 * Class Event
 * @package JTL\Events
 * @extends Enum<Event::*>
 */
class Event extends Enum
{
    public const EMIT = 'router.emit';

    public const RUN = 'shop.run';

    public const MAP_CRONJOB_TYPE = 'map.cronjob.type';

    public const GET_AVAILABLE_CRONJOBS = 'get.available.cronjobs';

    public const REVISION_RESTORE_DELETE = 'backend.revision.restore.delete';

    public const OPC_AREA_GETPREVIEWHTML = 'shop.OPC.Area.getPreviewHtml';

    public const OPC_AREA_GETFINALHTML = 'shop.OPC.Area.getFinalHtml';

    public const OPC_PAGEDB_GETPUBLICPAGE = 'shop.OPC.PageDB.getPublicPage';

    public const OPC_PAGEDB_SAVEDRAFT_POSTVALIDATE = 'shop.OPC.PageDB.saveDraft:afterValidate';

    public const OPC_PAGEDB_GETPAGEROW = 'shop.OPC.PageDB.getPageRow';

    public const OPC_PAGESERVICE_RENDERMOUNTPOINT = 'shop.OPC.PageService.renderMountPoint';

    public const OPC_PORTLET_RENDERMOUNTPOINT = 'shop.OPC.PortletInstance.getPreviewHtml';

    public const OPC_PORTLET_GETFINALHTML = 'shop.OPC.PortletInstance.getFinalHtml';

    public const OPC_SERVICE_GETBLUEPRINTINSTANCE = 'shop.OPC.Service.getBlueprintInstance';
}
