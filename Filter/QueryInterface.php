<?php

declare(strict_types=1);

namespace JTL\Filter;

/**
 * Interface QueryInterface
 * @package JTL\Filter
 */
interface QueryInterface
{
    /**
     * @param string $where
     * @return $this
     */
    public function setWhere(string $where): QueryInterface;

    /**
     * @return string
     */
    public function getWhere(): string;

    /**
     * @param string $origin
     * @return $this
     */
    public function setOrigin(string $origin): QueryInterface;

    /**
     * @return string
     */
    public function getOrigin(): string;

    /**
     * @param string $type
     * @return $this
     */
    public function setType(string $type): QueryInterface;

    /**
     * @return string
     */
    public function getType(): string;

    /**
     * @return string
     */
    public function getTable(): string;

    /**
     * @param string $table
     * @return $this
     */
    public function setTable(string $table): QueryInterface;

    /**
     * @return string
     */
    public function getComment(): string;

    /**
     * @param string $comment
     * @return $this
     */
    public function setComment(string $comment): QueryInterface;

    /**
     * @return string
     */
    public function getOn(): string;

    /**
     * @param string $on
     * @return $this
     */
    public function setOn(string $on): QueryInterface;

    /**
     * @param array<mixed> $params
     * @return $this
     */
    public function setParams(array $params): QueryInterface;

    /**
     * @param array<mixed> $params
     * @return $this
     */
    public function addParams(array $params): QueryInterface;

    /**
     * @return array<mixed>
     */
    public function getParams(): array;

    /**
     * @return string
     */
    public function getSQL(): string;
}
