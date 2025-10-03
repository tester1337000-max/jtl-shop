<?php

declare(strict_types=1);

namespace JTL\Filter;

/**
 * Interface ProductFilterSQLInterface
 * @package JTL\Filter
 */
interface ProductFilterSQLInterface
{
    /**
     * @param StateSQLInterface $state
     * @param string            $type
     * @return string
     * @throws \InvalidArgumentException
     */
    public function getBaseQuery(StateSQLInterface $state, string $type = 'filter'): string;

    /**
     * @param bool $withAnd
     * @return string
     */
    public function getStockFilterSQL(bool $withAnd = true): string;
}
