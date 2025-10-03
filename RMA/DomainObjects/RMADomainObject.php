<?php

declare(strict_types=1);

namespace JTL\RMA\DomainObjects;

use Exception;
use JTL\DataObjects\AbstractDomainObject;
use JTL\RMA\Helper\RMAItems;
use JTL\Shop;

/**
 * Class RMADomainObject
 * @package JTL\RMA
 * @description Data container for RMA request created in shop or imported from WAWI
 * @comment The public properties represent the database table columns
 */
class RMADomainObject extends AbstractDomainObject
{
    public readonly int $id;
    public readonly ?int $wawiID;
    public readonly int $customerID;
    public readonly ?int $replacementOrderID;
    public readonly ?string $rmaNr;
    public readonly bool $voucherCredit;
    public readonly bool $refundShipping;
    public readonly bool $synced;
    public readonly int $status;
    public readonly ?string $comment;
    public readonly string $createDate;
    public readonly ?string $lastModified;
    private readonly ?RMAItems $items;
    private readonly ?RMAReturnAddressDomainObject $returnAddress;

    /**
     * @param int                                     $id
     * @param int|null                                $wawiID
     * @param int                                     $customerID
     * @param int|null                                $replacementOrderID
     * @param string|null                             $rmaNr
     * @param bool                                    $voucherCredit
     * @param bool                                    $refundShipping
     * @param bool                                    $synced
     * @param int                                     $status
     * @param string|null                             $comment
     * @param string|null                             $createDate
     * @param string|null                             $lastModified
     * @param RMAItems|array|null                     $items
     * @param RMAReturnAddressDomainObject|array|null $returnAddress
     * @param array<mixed>                            $modifiedKeys
     * @throws Exception
     */
    public function __construct(
        int $id = 0,
        ?int $wawiID = null,
        int $customerID = 0,
        ?int $replacementOrderID = null,
        ?string $rmaNr = null,
        bool $voucherCredit = false,
        bool $refundShipping = false,
        bool $synced = false,
        int $status = 1,
        ?string $comment = null,
        ?string $createDate = null,
        ?string $lastModified = null,
        RMAItems|array|null $items = null,
        RMAReturnAddressDomainObject|array|null $returnAddress = null,
        array $modifiedKeys = []
    ) {
        $this->id                 = $id;
        $this->wawiID             = $wawiID;
        $this->customerID         = $customerID;
        $this->replacementOrderID = $replacementOrderID;
        $this->rmaNr              = $rmaNr;
        $this->voucherCredit      = $voucherCredit;
        $this->refundShipping     = $refundShipping;
        $this->synced             = $synced;
        $this->status             = $status;
        $this->comment            = $comment;
        $this->createDate         = $createDate ?? \date('Y-m-d H:i:s');
        $this->lastModified       = $lastModified;

        if ($items instanceof RMAItems) {
            $this->items = $items;
        } else {
            $this->items = null;
        }

        if ($returnAddress instanceof RMAReturnAddressDomainObject) {
            $this->returnAddress = $returnAddress;
        } else {
            $this->returnAddress = null;
        }

        parent::__construct($modifiedKeys);
    }

    public function getRMAItems(): RMAItems
    {
        return $this->items ?? new RMAItems();
    }

    public function getReturnAddress(): ?RMAReturnAddressDomainObject
    {
        return $this->returnAddress;
    }

    public static function orderStatusToString(int $status): string
    {
        try {
            $result = match ($status) {
                \RETURN_STATUS_OPEN        => 'RETURN_STATUS_OPEN',
                \RETURN_STATUS_IN_PROGRESS => 'RETURN_STATUS_IN_PROGRESS',
                \RETURN_STATUS_ACCEPTED    => 'RETURN_STATUS_ACCEPTED',
                \RETURN_STATUS_COMPLETED   => 'RETURN_STATUS_COMPLETED',
                \RETURN_STATUS_REJECTED    => 'RETURN_STATUS_REJECTED',
            };
        } catch (\UnhandledMatchError $e) {
            Shop::Container()->getLogService()->error(
                'Unknown status ' . $status
                . ' in RMADomainObject::orderStatusToString.' . $e->getMessage()
            );
            $result = 'RETURN_STATUS_UNKNOWN';
        }

        return $result;
    }
}
