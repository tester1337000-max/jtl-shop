<?php

declare(strict_types=1);

namespace JTL\RMA\Repositories;

use JTL\Abstracts\AbstractDBRepository;

/**
 * Class RMAReasonRepository
 * @package JTL\RMA\Repositories
 * @description This is a layer between the RMA Reason Service and the database.
 */
class RMAReasonRepository extends AbstractDBRepository
{
    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'rma_reasons';
    }
}
