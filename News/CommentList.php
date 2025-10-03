<?php

declare(strict_types=1);

namespace JTL\News;

use Illuminate\Support\Collection;
use JTL\DB\DbInterface;

use function Functional\first;
use function Functional\group;
use function Functional\map;

/**
 * Class CommentList
 * @package JTL\News
 * @implements ItemListInterface<Comment>
 */
final class CommentList implements ItemListInterface
{
    private DbInterface $db;

    private int $newsID;

    /**
     * @var Collection<int, Comment>
     */
    private Collection $items;

    public function __construct(DbInterface $db)
    {
        $this->db    = $db;
        $this->items = new Collection();
    }

    /**
     * @return Collection<int, Comment>
     */
    public function createItems(array $itemIDs, bool $activeOnly = true): Collection
    {
        if (\count($itemIDs) === 0) {
            return $this->items;
        }
        $itemIDs = \array_map('\intval', $itemIDs);
        $data    = $this->db->getObjects(
            'SELECT tnewskommentar.*, t.title
                FROM tnewskommentar
                JOIN tnewssprache t 
                    ON t.kNews = tnewskommentar.kNews
                WHERE kNewsKommentar IN (' . \implode(',', $itemIDs) . ')'
            . ($activeOnly ? ' AND nAktiv = 1 ' : '') . '
                GROUP BY tnewskommentar.kNewsKommentar
                ORDER BY tnewskommentar.dErstellt DESC'
        );
        $items   = map(
            group($data, fn(\stdClass $e): int => (int)$e->kNewsKommentar),
            function ($e, $commentID): Comment {
                $l = new Comment($this->db);
                $l->setID($commentID);
                $l->map($e);
                $l->setNewsTitle(first($e)->title);

                return $l;
            }
        );
        foreach ($items as $item) {
            $this->items->push($item);
        }

        return $this->items;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function createItemsByNewsItem(int $newsID): Collection
    {
        $this->newsID = $newsID;
        $data         = $this->db->getObjects(
            'SELECT *
                FROM tnewskommentar
                WHERE kNews = :nid
                    AND nAktiv = 1
                    ORDER BY tnewskommentar.dErstellt DESC',
            ['nid' => $this->newsID]
        );
        $items        = map(
            group($data, fn(\stdClass $e): int => (int)$e->kNewsKommentar),
            function (array $e, int $commentID): Comment {
                $l = new Comment($this->db);
                $l->setID($commentID);
                $l->map($e);

                return $l;
            }
        );
        foreach ($items as $item) {
            $this->items->push($item);
        }

        return $this->items;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function filter(bool $active): Collection
    {
        return $this->items->filter(fn(Comment $e): bool => $e->isActive() === $active);
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function getCommentsCount(string $whatcount = 'parent'): int
    {
        $parent = 0;
        $child  = 0;
        foreach ($this->items as $comment) {
            if ($comment->getParentCommentID() === 0) {
                $parent++;
            } else {
                $child++;
            }
        }

        return $whatcount === 'parent' ? $parent : $child;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getThreadedItems(): Collection
    {
        foreach ($this->items as $comment) {
            foreach ($this->items as $child) {
                if ($comment->getID() === $child->getParentCommentID()) {
                    $comment->setChildComment($child);
                }
            }
        }
        foreach ($this->items as $key => $comment) {
            if ($comment->getParentCommentID() > 0) {
                unset($this->items[$key]);
            }
        }

        return $this->items;
    }

    /**
     * @param Collection<int, Comment> $items
     */
    public function setItems(Collection $items): void
    {
        $this->items = $items;
    }

    /**
     * @inheritdoc
     */
    public function addItem(mixed $item): void
    {
        $this->items->push($item);
    }

    public function getDB(): DbInterface
    {
        return $this->db;
    }

    public function setDB(DbInterface $db): void
    {
        $this->db = $db;
    }

    public function getNewsID(): int
    {
        return $this->newsID;
    }

    public function setNewsID(int $newsID): void
    {
        $this->newsID = $newsID;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $res       = \get_object_vars($this);
        $res['db'] = '*truncated*';

        return $res;
    }
}
