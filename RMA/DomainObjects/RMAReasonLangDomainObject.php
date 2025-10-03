<?php

declare(strict_types=1);

namespace JTL\RMA\DomainObjects;

use JTL\DataObjects\AbstractDomainObject;

/**
 * Class RMADomainObject
 * @package JTL\RMA
 * @description Container holding localizations for the RMAReasonDomainObject
 * @comment The public properties represent the database table columns
 */
class RMAReasonLangDomainObject extends AbstractDomainObject
{
    /**
     * @param int          $id
     * @param int          $reasonID
     * @param int          $langID
     * @param string       $title
     * @param array<mixed> $modifiedKeys
     */
    public function __construct(
        public readonly int $id = 0,
        public readonly int $reasonID = 0,
        public readonly int $langID = 0,
        public readonly string $title = '',
        array $modifiedKeys = []
    ) {
        parent::__construct($modifiedKeys);
    }
}
