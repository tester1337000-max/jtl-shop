<?php

declare(strict_types=1);

namespace JTL\Media\Image;

use JsonSerializable;
use JTL\Media\Image;
use stdClass;

/**
 * Class StatsItem
 * @package JTL\Media\Image
 */
class StatsItem implements JsonSerializable
{
    private int $total = 0;

    private int $totalSize = 0;

    private int $corrupted = 0;

    private int $nextIndex = 0;

    private int $totalCount = 0;

    private bool $finished = false;

    /**
     * @var stdClass[]
     */
    private array $corruptedImagesList = [];

    /**
     * @var array<string, int>
     */
    private array $generated = [
        Image::SIZE_XS => 0,
        Image::SIZE_SM => 0,
        Image::SIZE_MD => 0,
        Image::SIZE_LG => 0,
        Image::SIZE_XL => 0,
    ];

    /**
     * @var array<string, int>
     */
    private array $generatedSize = [
        Image::SIZE_XS => 0,
        Image::SIZE_SM => 0,
        Image::SIZE_MD => 0,
        Image::SIZE_LG => 0,
        Image::SIZE_XL => 0,
    ];

    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotal(int $total): void
    {
        $this->total = $total;
    }

    public function addItem(): int
    {
        return ++$this->total;
    }

    public function getTotalSize(): int
    {
        return $this->totalSize;
    }

    public function setTotalSize(int $totalSize): void
    {
        $this->totalSize = $totalSize;
    }

    public function getCorrupted(): int
    {
        return $this->corrupted;
    }

    public function setCorrupted(int $corrupted): void
    {
        $this->corrupted = $corrupted;
    }

    public function addCorrupted(): int
    {
        return ++$this->corrupted;
    }

    public function addCorruptedImageItem(stdClass $data): void
    {
        $this->corruptedImagesList[] = $data;
    }

    /**
     * @return stdClass[]
     */
    public function getCorruptedImageList(): array
    {
        return $this->corruptedImagesList;
    }

    /**
     * @return array<string, int>
     */
    public function getGenerated(): array
    {
        return $this->generated;
    }

    public function getGeneratedBySize(string $size): int
    {
        return $this->generated[$size];
    }

    /**
     * @param array<string, int> $generated
     */
    public function setGenerated(array $generated): void
    {
        $this->generated = $generated;
    }

    public function addGeneratedItem(string $size): int
    {
        return ++$this->generated[$size];
    }

    /**
     * @return array<string, int>
     */
    public function getGeneratedSize(): array
    {
        return $this->generatedSize;
    }

    /**
     * @param array<string, int> $generatedSize
     */
    public function setGeneratedSize(array $generatedSize): void
    {
        $this->generatedSize = $generatedSize;
    }

    public function addGeneratedSizeItem(string $size, int $bytes): int
    {
        $this->generatedSize[$size] += $bytes;
        $this->totalSize            += $bytes;

        return $this->generatedSize[$size];
    }

    public function getNextIndex(): int
    {
        return $this->nextIndex;
    }

    public function setNextIndex(int $nextIndex): void
    {
        $this->nextIndex = $nextIndex;
    }

    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    public function setTotalCount(int $totalCount): void
    {
        $this->totalCount = $totalCount;
    }

    public function isFinished(): bool
    {
        return $this->finished;
    }

    public function setFinished(bool $finished): void
    {
        $this->finished = $finished;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return \get_object_vars($this);
    }
}
