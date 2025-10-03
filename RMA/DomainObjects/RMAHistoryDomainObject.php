<?php

declare(strict_types=1);

namespace JTL\RMA\DomainObjects;

use JTL\DataObjects\AbstractDomainObject;

/**
 * Class RMAHistoryDomainObject
 * @package JTL\RMA
 * @comment The public properties represent the database table columns
 */
class RMAHistoryDomainObject extends AbstractDomainObject
{
    public readonly string $createDate;

    /**
     * @param int                           $id
     * @param int                           $rmaID
     * @param string                        $eventName
     * @param string                        $eventDataJson
     * @param RMAEventDataDomainObject|null $eventDataDomainObject
     * @param string|null                   $createDate
     * @param array<mixed>                  $modifiedKeys
     */
    public function __construct(
        public readonly int $id,
        public readonly int $rmaID,
        public readonly string $eventName,
        public readonly string $eventDataJson,
        private readonly ?RMAEventDataDomainObject $eventDataDomainObject = null,
        ?string $createDate = null,
        array $modifiedKeys = []
    ) {
        $this->createDate = $createDate ?? \date('Y-m-d H:i:s');

        parent::__construct($modifiedKeys);
    }

    public function getEventDataDomainObject(): ?RMAEventDataDomainObject
    {
        return $this->eventDataDomainObject;
    }
}
