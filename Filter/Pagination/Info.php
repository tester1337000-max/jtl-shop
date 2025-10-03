<?php

declare(strict_types=1);

namespace JTL\Filter\Pagination;

use JTL\MagicCompatibilityTrait;

/**
 * Class Info
 * @package JTL\Filter\Pagination
 */
class Info
{
    use MagicCompatibilityTrait;

    private int $currentPage = 0;

    private int $totalPages = 0;

    private int $minPage = 0;

    private int $maxPage = 0;

    /**
     * @var array<string, string>
     */
    public static array $mapping = [
        'AktuelleSeite' => 'CurrentPage',
        'MaxSeiten'     => 'TotalPages',
        'minSeite'      => 'MinPage',
        'maxSeite'      => 'MaxPage',
    ];

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function setCurrentPage(int $currentPage): void
    {
        $this->currentPage = $currentPage;
    }

    public function getTotalPages(): int
    {
        return $this->totalPages;
    }

    public function setTotalPages(int $totalPages): void
    {
        $this->totalPages = $totalPages;
    }

    public function getMinPage(): int
    {
        return $this->minPage;
    }

    public function setMinPage(int $minPage): void
    {
        $this->minPage = $minPage;
    }

    public function getMaxPage(): int
    {
        return $this->maxPage;
    }

    public function setMaxPage(int $maxPage): void
    {
        $this->maxPage = $maxPage;
    }
}
