<?php

declare(strict_types=1);

namespace JTL\Pagination;

use JTL\Helpers\Text;

/**
 * Class FilterField
 * @package JTL\Pagination
 */
abstract class FilterField
{
    protected string $title = '';

    protected string $titleLong = '';

    /**
     * @var string|string[]
     */
    protected string|array $column = '';

    /**
     * @var mixed|string
     */
    protected mixed $value = '';

    protected string $id = '';

    /**
     * @param Filter          $filter
     * @param string          $type
     * @param string[]|string $title - either title-string for this field or a pair of short title and long title
     * @param string|string[] $column
     * @param int|string      $defaultValue
     * @param string|null     $id
     */
    public function __construct(
        protected Filter $filter,
        protected string $type,
        array|string $title,
        array|string $column,
        int|string $defaultValue = '',
        ?string $id = null
    ) {
        $this->title     = \is_array($title) ? $title[0] : $title;
        $this->titleLong = \is_array($title) ? $title[1] : '';
        $this->column    = $column;
        $this->id        = $id ?? \preg_replace('/\W+/', '', $this->title) ?? $this->title;
        $this->value     = $this->initValue($filter, $defaultValue);
    }

    private function initValue(Filter $filter, int|string $defaultValue): string
    {
        if ($filter->getAction() === $filter->getID() . '_filter') {
            $value = $_GET[$filter->getID() . '_' . $this->id];
        } elseif ($filter->getAction() === $filter->getID() . '_resetfilter') {
            $value = $defaultValue;
        } elseif ($filter->hasSessionField($this->id)) {
            $value = $filter->getSessionField($this->id);
        } else {
            $value = $defaultValue;
        }

        return Text::filterXSS((string)$value);
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getColumn(): string
    {
        return \is_array($this->column) ? '' : $this->column;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getTitleLong(): string
    {
        return $this->titleLong;
    }

    public function getID(): string
    {
        return $this->id;
    }

    public function setID(string $id): void
    {
        $this->id = $id;
    }

    /**
     * @return string|null
     */
    abstract public function getWhereClause(): ?string;
}
