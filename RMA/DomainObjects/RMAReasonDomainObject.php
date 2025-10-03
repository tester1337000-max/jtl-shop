<?php

declare(strict_types=1);

namespace JTL\RMA\DomainObjects;

use JTL\DataObjects\AbstractDomainObject;

/**
 * Class RMADomainObject
 * @package JTL\RMA
 * @description This container holds possible reasons for an RMA request
 * @comment The public properties represent the database table columns
 */
class RMAReasonDomainObject extends AbstractDomainObject
{
    /**
     * @param int          $id
     * @param int          $wawiID
     * @param int|null     $productTypeGroupID
     * @param array<mixed> $modifiedKeys
     */
    public function __construct(
        public readonly int $id = 0,
        public readonly int $wawiID = 0,
        public readonly ?int $productTypeGroupID = null,
        array $modifiedKeys = []
    ) {
        parent::__construct($modifiedKeys);
    }
}
