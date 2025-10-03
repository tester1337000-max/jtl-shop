<?php

declare(strict_types=1);

namespace JTL\RMA\Repositories;

use JTL\Abstracts\AbstractDBRepository;

/**
 * Class RMAItemRepository
 * @package JTL\RMA\Repositories
 * @description This is a layer between the RMA Service (products) and the database.
 */
class RMAItemRepository extends AbstractDBRepository
{
    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'rma_items';
    }
}
