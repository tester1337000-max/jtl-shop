<?php

declare(strict_types=1);

namespace JTL\Filter\Pagination;

use JTL\MagicCompatibilityTrait;

/**
 * Class Item
 * @package JTL\Filter\Pagination
 */
class Item
{
    use MagicCompatibilityTrait;

    private int $page = 0;

    private ?string $url = null;

    private bool $isActive = false;

    /**
     * @var int|null - compatibility only
     */
    public ?int $nBTN = null;

    /**
     * @var array<string, string>
     */
    public static array $mapping = [
        'cURL'   => 'URL',
        'page'   => 'PageNumber',
        'nSeite' => 'PageNumber',
    ];

    public function getPageNumber(): int
    {
        return $this->page;
    }

    public function setPageNumber(int $page): void
    {
        $this->page = $page;
    }

    public function getURL(): ?string
    {
        return $this->url;
    }

    public function setURL(?string $url): void
    {
        $this->url = $url;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }
}
