<?php

declare(strict_types=1);

namespace JTL\RMA\Repositories;

use JTL\Abstracts\AbstractDBRepository;

/**
 * Class RMAHistoryRepository
 * @description This is a layer between the RMA History Service and the database.
 * @package JTL\RMA\Repositories
 * @since 5.3.0
 */
class RMAHistoryRepository extends AbstractDBRepository
{
    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'rma_history';
    }

    public function getProductNameFromDB(int $productID, int $shippingNotePosID): ?\stdClass
    {
        return $this->db->getSingleObject(
            'SELECT name FROM rma_items WHERE productID = :productID AND shippingNotePosID = :shippingNotePosID',
            [
                'shippingNotePosID' => $shippingNotePosID,
                'productID'         => $productID,
            ]
        );
    }
}
