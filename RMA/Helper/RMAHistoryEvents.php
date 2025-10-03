<?php

declare(strict_types=1);

namespace JTL\RMA\Helper;

/**
 * @package JTL\RMA\Helper
 */
enum RMAHistoryEvents: string
{
    case ITEM_MODIFIED          = 'itemModified';
    case ITEM_ADDED             = 'itemAdded';
    case ITEM_REMOVED           = 'itemRemoved';
    case ITEM_MODIFIED_REASON   = 'itemModifiedReason';
    case ITEM_MODIFIED_QUANTITY = 'itemModifiedQuantity';
    case REPLACEMENT_ORDER      = 'replacementOrderAssigned';
    case STATUS_CHANGED         = 'statusChanged';
    case ADDRESS_MODIFIED       = 'addressModified';
    case REFUND_SHIPPING        = 'refundShipping';
    case VOUCHER_CREDIT         = 'voucherCredit';
}
