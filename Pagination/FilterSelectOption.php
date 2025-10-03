<?php

declare(strict_types=1);

namespace JTL\Pagination;

/**
 * Class FilterSelectOption
 * @package JTL\Pagination
 */
class FilterSelectOption
{
    protected string $title = '';

    protected string|int $value = '';

    protected int $testOp = Operation::CUSTOM;

    public function __construct(string $title, int|string $value, int $testOp)
    {
        $this->title  = $title;
        $this->value  = $value;
        $this->testOp = $testOp;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getValue(): int|string
    {
        return $this->value;
    }

    public function getTestOp(): int
    {
        return $this->testOp;
    }
}
