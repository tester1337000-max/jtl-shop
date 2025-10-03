<?php

declare(strict_types=1);

namespace JTL\Filter\Pagination;

use JTL\Filter\ProductFilter;

/**
 * Class Pagination
 * @package JTL\Filter\Pagination
 */
class Pagination
{
    /**
     * @var Item[]
     */
    private array $pages = [];

    private Item $prev;

    private Item $next;

    /**
     * @var array<string, string>
     */
    public static array $mapping = [
        'zurueck' => 'Prev',
        'vor'     => 'Next',
    ];

    public function __construct(private readonly ProductFilter $productFilter, private readonly ItemFactory $factory)
    {
        $this->prev = $this->factory->create();
        $this->next = $this->factory->create();
    }

    /**
     * @param Info $pages
     * @return Item[]
     */
    public function create(Info $pages): array
    {
        if ($pages->getTotalPages() < 2 || $pages->getCurrentPage() === 0) {
            return $this->pages;
        }
        $naviURL = $this->productFilter->getFilterURL()->getURL();
        $sep     = !\str_contains($naviURL, '?')
            ? \SEP_SEITE
            : '&amp;seite=';
        $active  = $pages->getCurrentPage();
        $from    = $pages->getMinPage();
        $to      = $pages->getMaxPage();
        $current = $from;
        while ($current <= $to) {
            $page = $this->factory->create();
            $page->setPageNumber($current);
            $page->setURL($naviURL . $sep . $current);
            $page->setIsActive($current === $active);
            $this->pages[] = $page;
            if ($current === $active - 1) {
                $this->prev = clone $page;
            } elseif ($current === $active + 1) {
                $this->next = clone $page;
            }
            ++$current;
        }

        return $this->pages;
    }

    /**
     * for shop4 compatibility only!
     *
     * @return array<mixed>
     */
    public function getItemsCompat(): array
    {
        $items = [];
        foreach ($this->pages as $page) {
            $items[$page->getPageNumber()] = $page;
        }
        $this->next->nBTN = 1;
        $this->prev->nBTN = 1;
        $items['vor']     = $this->next;
        $items['zurueck'] = $this->prev;

        return $items;
    }

    /**
     * @return Item[]
     */
    public function getPages(): array
    {
        return $this->pages;
    }

    /**
     * @param Item[] $pages
     */
    public function setPages(array $pages): void
    {
        $this->pages = $pages;
    }

    /**
     * @return Item
     */
    public function getPrev(): Item
    {
        return $this->prev;
    }

    /**
     * @param Item $prev
     */
    public function setPrev(Item $prev): void
    {
        $this->prev = $prev;
    }

    /**
     * @return Item
     */
    public function getNext(): Item
    {
        return $this->next;
    }

    /**
     * @param Item $next
     */
    public function setNext(Item $next): void
    {
        $this->next = $next;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $res                  = \get_object_vars($this);
        $res['productFilter'] = '*truncated*';
        $res['factory']       = '*truncated*';

        return $res;
    }
}
