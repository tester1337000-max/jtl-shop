<?php

declare(strict_types=1);

namespace JTL\DB;

/**
 * Class SqlObject
 * @package JTL\DB
 */
class SqlObject
{
    private string $statement = '';

    /**
     * @var array<string, mixed>
     */
    private array $params = [];

    private string $select = '';

    private string $join = '';

    private string $where = '';

    private string $order = '';

    private string $groupBy = '';

    public function getStatement(): string
    {
        return $this->statement;
    }

    public function setStatement(string $statement): void
    {
        $this->statement = $statement;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function addParam(string $param, mixed $value): void
    {
        if (!\str_starts_with($param, ':')) {
            $param = ':' . $param;
        }
        $this->params[$param] = $value;
    }

    public function getSelect(): string
    {
        return $this->select;
    }

    public function setSelect(string $select): void
    {
        $this->select = $select;
    }

    public function getJoin(): string
    {
        return $this->join;
    }

    public function setJoin(string $join): void
    {
        $this->join = $join;
    }

    public function getWhere(): string
    {
        return $this->where;
    }

    public function setWhere(string $where): void
    {
        $this->where = $where;
    }

    public function getOrder(): string
    {
        return $this->order;
    }

    public function setOrder(string $order): void
    {
        $this->order = $order;
    }

    public function getGroupBy(): string
    {
        return $this->groupBy;
    }

    public function setGroupBy(string $groupBy): void
    {
        $this->groupBy = $groupBy;
    }
}
