<?php

declare(strict_types=1);

namespace JTL\Filter;

/**
 * Class Query
 * @package JTL\Filter
 */
class Query implements QueryInterface
{
    private string $type = '=';

    private string $table = '';

    private string $comment = '';

    private string $on = '';

    private string $origin = '';

    private string $where = '';

    /**
     * @var array<mixed>
     */
    private array $params = [];

    /**
     * @inheritdoc
     */
    public function setWhere(string $where): QueryInterface
    {
        $this->where = $where;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getWhere(): string
    {
        return $this->where;
    }

    /**
     * @inheritdoc
     */
    public function setOrigin(string $origin): QueryInterface
    {
        $this->origin = $origin;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getOrigin(): string
    {
        return $this->origin;
    }

    /**
     * @inheritdoc
     */
    public function setType(string $type): QueryInterface
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @inheritdoc
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @inheritdoc
     */
    public function setTable(string $table): QueryInterface
    {
        $this->table = $table;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getComment(): string
    {
        return empty($this->comment)
            ? ''
            : "\n#" . $this->comment . "\n";
    }

    /**
     * @inheritdoc
     */
    public function setComment(string $comment): QueryInterface
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getOn(): string
    {
        return $this->on;
    }

    /**
     * @inheritdoc
     */
    public function setOn(string $on): QueryInterface
    {
        $this->on = $on;

        return $this;
    }

    public function __toString(): string
    {
        return $this->getSQL();
    }

    /**
     * @inheritdoc
     */
    public function setParams(array $params): QueryInterface
    {
        $this->params = $params;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function addParams(array $params): QueryInterface
    {
        foreach ($params as $param) {
            $this->params[] = $param;
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @inheritdoc
     */
    public function getSQL(): string
    {
        $where = $this->where;
        foreach ($this->params as $param => $value) {
            if (\is_array($value)) {
                $value = \implode(',', $value);
            }
            $where = \str_replace('{' . $param . '}', $value, $where);
        }

        return $this->getComment() . $where;
    }
}
