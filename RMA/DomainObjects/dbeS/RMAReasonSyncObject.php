<?php

declare(strict_types=1);

namespace JTL\RMA\DomainObjects\dbeS;

use JTL\Abstracts\AbstractDbeSObject;

/**
 * Class RMAReasonSyncObject
 *
 * @package JTL\RMA\DomainObjects\dbeS
 * @description Container for RMA data imported from WAWI via dbeS
 */
class RMAReasonSyncObject extends AbstractDbeSObject
{
    /**
     * @param array<mixed> $localization
     */
    public function __construct(
        public int $wawiID,
        public int $productTypeGroupID,
        public array $localization,
    ) {
        parent::__construct();
    }
}
