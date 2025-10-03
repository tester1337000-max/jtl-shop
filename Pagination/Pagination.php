<?php

declare(strict_types=1);

namespace JTL\Pagination;

use Illuminate\Support\Collection;

/**
 * Class Pagination
 * @package JTL\Pagination
 */
class Pagination
{
    private string $id = 'pagi';

    private int $dispPagesRadius = 2;

    /**
     * @var int[]
     */
    private array $itemsPerPageOptions = [10, 20, 50, 100];

    /**
     * @var array<int, array<int|string, string>>
     */
    private array $sortByOptions = [];

    private int $itemCount = 0;

    private int $itemsPerPage = 10;

    private bool $itemsPerPageExplicit = false;

    private int $sortBy = 0;

    private int $sortDir = 0;

    private int $sortByDir = 0;

    private int $page = 0;

    private int $pageCount = 0;

    private int $prevPage = 0;

    private int $nextPage = 0;

    private int $leftRangePage = 0;

    private int $rightRangePage = 0;

    private int $firstPageItem = 0;

    private int $pageItemCount = 0;

    private string $sortBySQL = '';

    /**
     * @var 'ASC'|'DESC'|''
     */
    private string $sortDirSQL = '';

    private string $limitSQL = '';

    private string $orderSQL = '';

    /**
     * @var array<mixed>|Collection<int, mixed>|null
     */
    private Collection|array|null $items = null;

    /**
     * @var array<mixed>|Collection<int, mixed>|null
     */
    private Collection|array|null $pageItems = null;

    private int $defaultItemsPerPage = 0;

    private int $defaultSortByDir = 0;

    /**
     * @var callable|null
     */
    private $sortFunction = null;

    public function __construct(?string $id = null)
    {
        if ($id !== null) {
            $this->id = $id;
        }
    }

    public function setId(string|int $id): self
    {
        $this->id = (string)$id;

        return $this;
    }

    /**
     * @param int $range - number of page buttons to be displayed before and after the active page button
     * @return $this
     */
    public function setRange(int $range): self
    {
        $this->dispPagesRadius = $range;

        return $this;
    }

    /**
     * @param int[] $itemsPerPageOptions - to be offered as items per page count options (non-empty)
     * @return $this
     */
    public function setItemsPerPageOptions(array $itemsPerPageOptions): self
    {
        $this->itemsPerPageOptions = $itemsPerPageOptions;

        return $this;
    }

    /**
     * @param array<int, array<int|string, string>> $sortByOptions - array of [$cColumnName, $cDisplayTitle]
     * pairs to be offered as sorting options
     * @return $this
     */
    public function setSortByOptions(array $sortByOptions): self
    {
        $this->sortByOptions = $sortByOptions;

        return $this;
    }

    /**
     * @param int $n - number of items to be paginated
     * @return $this
     */
    public function setItemCount(int $n): self
    {
        $this->itemCount = $n;

        return $this;
    }

    /**
     * @param Collection<int, mixed>|array<mixed> $items - item array to be paginated and sorted
     * @return $this
     */
    public function setItemArray(array|Collection $items): self
    {
        $this->items = $items;
        $this->setItemCount(\count($items));

        return $this;
    }

    /**
     * @param int $n - -1 means: all items / 0 means: use first option of $nItemsPerPageOption_arr
     * @return $this
     */
    public function setDefaultItemsPerPage(int $n): self
    {
        $this->defaultItemsPerPage = $n;

        return $this;
    }

    public function setDefaultSortByDir(int $n): self
    {
        $this->defaultSortByDir = $n;

        return $this;
    }

    /**
     * Explicitly set the number of items per page. This overrides any custom selection.
     */
    public function setItemsPerPage(int $nItemsPerPage): self
    {
        $this->itemsPerPageExplicit = true;
        $this->itemsPerPage         = $nItemsPerPage;

        return $this;
    }

    /**
     * @param ?callable $func
     * @return $this
     */
    public function setSortFunction(?callable $func): self
    {
        $this->sortFunction = $func;

        return $this;
    }

    /**
     * Load parameters from GET, POST or SESSION store
     * @return $this
     */
    public function loadParameters(): self
    {
        $idx                = $this->id . '_nItemsPerPage';
        $fb                 = $this->defaultItemsPerPage === -1
            ? $this->defaultItemsPerPage
            : $this->itemsPerPageOptions[0];
        $this->itemsPerPage = $this->itemsPerPageExplicit
            ? $this->itemsPerPage
            : (int)($_GET[$idx] ?? $_POST[$idx] ?? $_SESSION[$idx] ?? $fb);
        $idx                = $this->id . '_nSortByDir';
        $this->sortByDir    = (int)($_GET[$idx] ?? $_POST[$idx] ?? $_SESSION[$idx] ?? $this->defaultSortByDir);
        $idx                = $this->id . '_nPage';
        $this->page         = (int)($_GET[$idx] ?? $_POST[$idx] ?? $_SESSION[$idx] ?? 0);

        return $this;
    }

    /**
     * Assemble the pagination. Create SQL LIMIT and ORDER BY clauses. Sort and slice item array if present
     * @return $this
     */
    public function assemble(): self
    {
        $this->loadParameters()
            ->storeParameters();

        if ($this->itemsPerPage === -1) {
            // Show all entries on a single page
            $this->pageCount      = 1;
            $this->page           = 0;
            $this->prevPage       = 0;
            $this->nextPage       = 0;
            $this->leftRangePage  = 0;
            $this->rightRangePage = 0;
            $this->firstPageItem  = 0;
            $this->pageItemCount  = $this->itemCount;
        } elseif ($this->itemsPerPage === 0) {
            // Set $nItemsPerPage to default if greater 0 or else to the first option in $nItemsPerPageOption_arr
            $nItemsPerPage        = $this->defaultItemsPerPage > 0
                ? $this->defaultItemsPerPage
                : $this->itemsPerPageOptions[0];
            $this->pageCount      = $nItemsPerPage > 0 ? (int)\ceil($this->itemCount / $nItemsPerPage) : 1;
            $this->page           = \max(0, \min($this->pageCount - 1, $this->page));
            $this->prevPage       = \max(0, \min($this->pageCount - 1, $this->page - 1));
            $this->nextPage       = \max(0, \min($this->pageCount - 1, $this->page + 1));
            $this->leftRangePage  = \max(0, $this->page - $this->dispPagesRadius);
            $this->rightRangePage = \min($this->pageCount - 1, $this->page + $this->dispPagesRadius);
            $this->firstPageItem  = $this->page * $nItemsPerPage;
            $this->pageItemCount  = \min($nItemsPerPage, $this->itemCount - $this->firstPageItem);
        } else {
            $this->pageCount      = $this->itemsPerPage > 0 ? (int)\ceil($this->itemCount / $this->itemsPerPage) : 1;
            $this->page           = \max(0, \min($this->pageCount - 1, $this->page));
            $this->prevPage       = \max(0, \min($this->pageCount - 1, $this->page - 1));
            $this->nextPage       = \max(0, \min($this->pageCount - 1, $this->page + 1));
            $this->leftRangePage  = \max(0, $this->page - $this->dispPagesRadius);
            $this->rightRangePage = \min($this->pageCount - 1, $this->page + $this->dispPagesRadius);
            $this->firstPageItem  = $this->page * $this->itemsPerPage;
            $this->pageItemCount  = \min($this->itemsPerPage, $this->itemCount - $this->firstPageItem);
        }

        $this->sortBy  = (int)($this->sortByDir / 2);
        $this->sortDir = $this->sortByDir % 2;

        if (isset($this->sortByOptions[$this->sortBy])) {
            $this->sortBySQL  = $this->sortByOptions[$this->sortBy][0];
            $this->sortDirSQL = $this->sortDir === 0 ? 'ASC' : 'DESC';
            $this->orderSQL   = $this->sortBySQL . ' ' . $this->sortDirSQL;
            $nSortFac         = $this->sortDir === 0 ? +1 : -1;
            $cSortBy          = $this->sortBySQL;
            if (\is_array($this->items)) {
                $func = $this->sortFunction;
                if ($func === null) {
                    $func = static function ($a, $b) use ($cSortBy, $nSortFac) {
                        $valueA = \is_string($a->$cSortBy)
                            ? \mb_convert_case($a->$cSortBy, \MB_CASE_LOWER)
                            : $a->$cSortBy;
                        $valueB = \is_string($b->$cSortBy)
                            ? \mb_convert_case($b->$cSortBy, \MB_CASE_LOWER)
                            : $b->$cSortBy;

                        return $valueA === $valueB ? 0 : ($valueA < $valueB ? -$nSortFac : +$nSortFac);
                    };
                }
                \usort($this->items, $func);
            }
        }
        $this->limitSQL = $this->firstPageItem . ',' . $this->pageItemCount;
        if (\is_array($this->items)) {
            $this->pageItems = \array_slice($this->items, $this->firstPageItem, $this->pageItemCount);
        } elseif ($this->items instanceof Collection) {
            $this->pageItems = $this->items->slice($this->firstPageItem, $this->pageItemCount);
        }

        return $this;
    }

    /**
     * Store the custom parameters back into the SESSION store
     * @return $this
     */
    public function storeParameters(): self
    {
        $_SESSION[$this->id . '_nItemsPerPage'] = $this->itemsPerPage;
        $_SESSION[$this->id . '_nSortByDir']    = $this->sortByDir;
        $_SESSION[$this->id . '_nPage']         = $this->page;

        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return int[]
     */
    public function getItemsPerPageOptions(): array
    {
        return $this->itemsPerPageOptions;
    }

    /**
     * @return array<int, array<int|string, string>>
     */
    public function getSortByOptions(): array
    {
        return $this->sortByOptions;
    }

    public function getLimitSQL(): string
    {
        return $this->limitSQL;
    }

    public function getOrderSQL(): string
    {
        return $this->orderSQL;
    }

    public function getItemCount(): int
    {
        return $this->itemCount;
    }

    public function getItemsPerPage(): int
    {
        return $this->itemsPerPage;
    }

    public function getSortBy(): int
    {
        return $this->sortBy;
    }

    public function getSortDirSQL(): int
    {
        return $this->sortDir;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPageCount(): int
    {
        return $this->pageCount;
    }

    public function getPrevPage(): int
    {
        return $this->prevPage;
    }

    public function getNextPage(): int
    {
        return $this->nextPage;
    }

    public function getLeftRangePage(): int
    {
        return $this->leftRangePage;
    }

    public function getRightRangePage(): int
    {
        return $this->rightRangePage;
    }

    public function getFirstPageItem(): int
    {
        return $this->firstPageItem;
    }

    public function getPageItemCount(): int
    {
        return $this->pageItemCount;
    }

    /**
     * @return array<mixed>|Collection<int, mixed>|null
     */
    public function getPageItems(): array|Collection|null
    {
        return $this->pageItems;
    }

    /**
     * @return 'ASC'|'DESC'|''
     */
    public function getSortDirSpecifier(): string
    {
        return $this->sortDirSQL;
    }

    public function getSortByCol(): string
    {
        return $this->sortBySQL;
    }

    public function getItemsPerPageOption(int $nIndex): ?int
    {
        return $this->itemsPerPageOptions[$nIndex];
    }

    public function getSortByDir(): int
    {
        return $this->sortByDir;
    }
}
