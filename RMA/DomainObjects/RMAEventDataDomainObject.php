<?php

declare(strict_types=1);

namespace JTL\RMA\DomainObjects;

use JTL\DataObjects\AbstractDomainObject;

/**
 * Class RMAHistoryDomainObject
 * @package JTL\RMA
 * @description This is a data container for handling RMA related event data between the DB and the RMA History Service
 * @comment This Domain object does not represent any table in the database
 */
class RMAEventDataDomainObject extends AbstractDomainObject
{
    /**
     * @param int          $shippingNotePosID
     * @param int          $productID
     * @param array<mixed> $dataBefore
     * @param array<mixed> $dataAfter
     * @param array<mixed> $modifiedKeys
     */
    public function __construct(
        public readonly int $shippingNotePosID,
        public readonly int $productID,
        public readonly array $dataBefore,
        public readonly array $dataAfter = [],
        array $modifiedKeys = []
    ) {
        parent::__construct($modifiedKeys);
    }
}
