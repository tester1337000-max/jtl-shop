<?php

declare(strict_types=1);

namespace JTL\dbeS\Job;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;

/**
 * Class CategoryUpdate
 * @package JTL\dbeS\Job
 */
class CategoryUpdate implements JobInterface
{
    /**
     * @inheritdoc
     */
    public function __construct(private readonly DbInterface $db, private readonly JTLCacheInterface $cache)
    {
    }

    /**
     * @inheritdoc
     */
    public function run(): void
    {
        $lft           = 1;
        $parentNodeIDs = $this->db->getInts(
            'SELECT DISTINCT tkategorie.kOberKategorie
                FROM tkategorie
                LEFT JOIN tkategorie parent ON parent.kKategorie = tkategorie.kOberKategorie
                WHERE parent.kKategorie IS NULL
                ORDER BY tkategorie.kOberKategorie',
            'kOberKategorie'
        );
        foreach ($parentNodeIDs as $id) {
            $lft = $this->rebuildCategoryTree($id, $lft);
        }
        $this->cache->flushTags([\CACHING_GROUP_CATEGORY]);
    }

    public function rebuildCategoryTree(int $parentID, int $left, int $level = 0): int
    {
        // the right value of this node is the left value + 1
        $right = $left + 1;
        // get all children of this node
        $result = $this->db->getInts(
            'SELECT kKategorie
                FROM tkategorie
                WHERE kOberKategorie = :pid
                ORDER BY nSort, cName',
            'kKategorie',
            ['pid' => $parentID]
        );
        foreach ($result as $id) {
            $right = $this->rebuildCategoryTree($id, $right, $level + 1);
        }
        // we've got the left value, and now that we've processed the children of this node we also know the right value
        $this->db->update(
            'tkategorie',
            'kKategorie',
            $parentID,
            (object)[
                'lft'    => $left,
                'rght'   => $right,
                'nLevel' => $level,
            ]
        );
        // return the right value of this node + 1
        return $right + 1;
    }
}
