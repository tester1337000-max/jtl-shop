<?php

declare(strict_types=1);

namespace JTL\Pagination;

use DateTime;

/**
 * Class FilterDateRangeField
 * @package JTL\Pagination
 */
class FilterDateRangeField extends FilterField
{
    private string $dStart = '';

    private string $dEnd = '';

    /**
     * @param string|string[] $title
     */
    public function __construct(Filter $filter, $title, string $column, $defaultValue = '', ?string $id = null)
    {
        parent::__construct($filter, 'daterange', $title, $column, $defaultValue, $id);

        $dRange = \explode(' - ', $this->value);
        if (\count($dRange) === 2) {
            $this->dStart = (new DateTime($dRange[0]))->format('Y-m-d') . ' 00:00:00';
            $this->dEnd   = (new DateTime($dRange[1]))->format('Y-m-d') . ' 23:59:59';
        }
    }

    public function getWhereClause(): ?string
    {
        $dRange = \explode(' - ', $this->value);
        if (\count($dRange) !== 2) {
            return null;
        }
        $dStart = (new DateTime($dRange[0]))->format('Y-m-d') . ' 00:00:00';
        $dEnd   = (new DateTime($dRange[1]))->format('Y-m-d') . ' 23:59:59';
        $column = $this->getColumn();

        return $column . " >= '" . $dStart . "' AND " . $column . " <= '" . $dEnd . "'";
    }

    public function getStart(): string
    {
        return $this->dStart;
    }

    public function getEnd(): string
    {
        return $this->dEnd;
    }
}
