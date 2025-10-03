<?php

declare(strict_types=1);

namespace JTL\RMA\Repositories;

use JTL\Abstracts\AbstractDBRepository;

/**
 * Class RMAReasonLangRepository
 * @package JTL\RMA\Repositories
 * @description This is a layer between the RMA Reason Service and the database.
 */
class RMAReasonLangRepository extends AbstractDBRepository
{
    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'rma_reasons_lang';
    }
}
