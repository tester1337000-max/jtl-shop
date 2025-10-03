<?php

declare(strict_types=1);

namespace JTL\Pagination;

/**
 * Class Filter
 * @package JTL\Pagination
 */
class Filter
{
    protected string $id = 'Filter';

    /**
     * @var FilterField[]
     */
    protected array $fields = [];

    protected string $whereSQL = '';

    protected mixed $action = '';

    /**
     * @var array<string, mixed>
     */
    protected array $sessionData = [];

    public function __construct(?string $id = null)
    {
        if (\is_string($id)) {
            $this->id = $id;
        }

        $this->action = $_GET['action'] ?? '';
        $this->loadSessionStore();
    }

    /**
     * Add a text field to a filter object
     * @param string|string[] $title - either title-string for this field or a pair of short title and long title
     * @param string|string[] $column - the column name to be compared
     * @param Operation::*    $testOp
     * @param DataType::*     $dataType
     */
    public function addTextfield(
        string|array $title,
        string|array $column,
        int $testOp = Operation::CUSTOM,
        int $dataType = DataType::TEXT,
        ?string $id = null
    ): FilterTextField {
        $field                                      = new FilterTextField(
            $this,
            $title,
            $column,
            $testOp,
            $dataType,
            $id
        );
        $this->fields[]                             = $field;
        $this->sessionData[$field->getID()]         = $field->getValue();
        $this->sessionData[$field->getID() . '_op'] = $field->getTestOp();

        return $field;
    }

    /**
     * Add a select field to a filter object. Options can be added with FilterSelectField->addSelectOption() to this
     * select field
     *
     * @param string[]|string $title - either title-string for this field or a pair of short title and long title
     * @param string          $column - the column name to be compared
     * @param string|int      $defaultOption
     * @param string|null     $id
     * @return FilterSelectField
     */
    public function addSelectfield(
        array|string $title,
        string $column,
        string|int $defaultOption = 0,
        ?string $id = null
    ): FilterSelectField {
        $field                              = new FilterSelectField($this, $title, $column, $defaultOption, $id);
        $this->fields[]                     = $field;
        $this->sessionData[$field->getID()] = $field->getValue();

        return $field;
    }

    /**
     * Add a DateRange field to the filter object.
     * @param string[]|string $title
     */
    public function addDaterangefield(
        array|string $title,
        string $column,
        string $defaultValue = '',
        ?string $id = null
    ): FilterDateRangeField {
        $field                              = new FilterDateRangeField($this, $title, $column, $defaultValue, $id);
        $this->fields[]                     = $field;
        $this->sessionData[$field->getID()] = $field->getValue();

        return $field;
    }

    /**
     * Assemble filter object to be ready for use. Build WHERE clause.
     */
    public function assemble(): void
    {
        $this->whereSQL = \implode(
            ' AND ',
            \array_filter(
                \array_map(static fn(FilterField $field): ?string => $field->getWhereClause(), $this->fields)
            )
        );
        $this->saveSessionStore();
    }

    /**
     * @return FilterField[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function getField(int $i): ?FilterField
    {
        return $this->fields[$i];
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getWhereSQL(): string
    {
        return $this->whereSQL;
    }

    public function loadSessionStore(): void
    {
        $this->sessionData = $_SESSION['filter_' . $this->id] ?? [];
    }

    public function saveSessionStore(): void
    {
        $_SESSION['filter_' . $this->id] = $this->sessionData;
    }

    public function hasSessionField(string $field): bool
    {
        return isset($this->sessionData[$field]);
    }

    public function getSessionField(string $field): mixed
    {
        return $this->sessionData[$field];
    }

    public function getID(): string
    {
        return $this->id;
    }
}
