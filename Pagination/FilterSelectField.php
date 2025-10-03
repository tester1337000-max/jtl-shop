<?php

declare(strict_types=1);

namespace JTL\Pagination;

use JTL\Shop;

/**
 * Class FilterSelectField
 * @package JTL\Pagination
 */
class FilterSelectField extends FilterField
{
    /**
     * @var FilterSelectOption[]
     */
    public array $options = [];

    public bool $reloadOnChange = false;

    /**
     * @param Filter          $filter
     * @param string|string[] $title
     * @param string          $column
     * @param string|int      $defaultOption
     * @param string|null     $id
     */
    public function __construct(Filter $filter, $title, $column, $defaultOption = 0, ?string $id = null)
    {
        parent::__construct($filter, 'select', $title, $column, $defaultOption, $id);
    }

    /**
     * Add a select option to a filter select field
     *
     * @param string     $title - the label/title for this option
     * @param int|string $value
     * @param int        $testOp
     * @return FilterSelectOption
     */
    public function addSelectOption(
        string $title,
        int|string $value,
        int $testOp = Operation::CUSTOM
    ): FilterSelectOption {
        $option          = new FilterSelectOption($title, $value, $testOp);
        $this->options[] = $option;

        return $option;
    }

    /**
     * @return FilterSelectOption[]
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function getWhereClause(): ?string
    {
        $testOp = $this->options[(int)$this->value]->getTestOp();
        $value  = Shop::Container()->getDB()->escape($this->options[(int)$this->value]->getValue());
        $column = $this->getColumn();

        if ($value !== '' || $testOp === Operation::EQUALS || $testOp === Operation::NOT_EQUAL) {
            return match ($testOp) {
                Operation::CONTAINS           => $column . " LIKE '%" . $value . "%'",
                Operation::BEGINS_WITH        => $column . " LIKE '" . $value . "%'",
                Operation::ENDS_WITH          => $column . " LIKE '%" . $value . "'",
                Operation::EQUALS             => $column . " = '" . $value . "'",
                Operation::LOWER_THAN         => $column . " < '" . $value . "'",
                Operation::GREATER_THAN       => $column . " > '" . $value . "'",
                Operation::LOWER_THAN_EQUAL   => $column . " <= '" . $value . "'",
                Operation::GREATER_THAN_EQUAL => $column . " >= '" . $value . "'",
                Operation::NOT_EQUAL          => $column . " != '" . $value . "'",
                Operation::CUSTOM             => '',
                default                       => null,
            };
        }

        return null;
    }

    public function getSelectedOption(): ?FilterSelectOption
    {
        return $this->options[(int)$this->value] ?? null;
    }
}
