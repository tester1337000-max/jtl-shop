<?php

declare(strict_types=1);

namespace JTL\Filter;

/**
 * Interface JoinInterface
 * @package JTL\Filter
 */
interface JoinInterface
{
    /**
     * @param string $origin
     * @return $this
     */
    public function setOrigin(string $origin): JoinInterface;

    /**
     * @return string
     */
    public function getOrigin(): string;

    /**
     * @param string $type
     * @return $this
     */
    public function setType(string $type): JoinInterface;

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
    public function setTable(string $table): JoinInterface;

    /**
     * @return string
     */
    public function getComment(): string;

    /**
     * @param string $comment
     * @return $this
     */
    public function setComment(string $comment): JoinInterface;

    /**
     * @return string
     */
    public function getOn(): string;

    /**
     * @param string $on
     * @return $this
     */
    public function setOn(string $on): JoinInterface;

    /**
     * @return string
     */
    public function getSQL(): string;
}
