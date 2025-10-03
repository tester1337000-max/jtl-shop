<?php

declare(strict_types=1);

namespace JTL\RMA\DomainObjects\dbeS;

use JTL\Abstracts\AbstractDbeSObject;

/**
 * Class RMAReasonLocalizationSyncObject
 *
 * @package JTL\RMA\DomainObjects\dbeS
 * @description Container for RMA data imported from WAWI via dbeS
 */
class RMAReasonLocalizationSyncObject extends AbstractDbeSObject
{
    public function __construct(
        public int $reasonID,
        public int $langID,
        public string $title,
    ) {
        parent::__construct();
    }
}
