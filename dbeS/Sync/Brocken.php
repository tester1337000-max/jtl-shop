<?php

declare(strict_types=1);

namespace JTL\dbeS\Sync;

use JTL\dbeS\Starter;
use JTL\Helpers\Text;
use stdClass;

/**
 * Class Brocken
 *
 * @package JTL\dbeS\Sync
 */
final class Brocken extends AbstractSync
{
    public function handle(Starter $starter): void
    {
        $input = Text::filterXSS($starter->getPostData('b'));
        $data  = $this->db->getSingleObject(
            'SELECT cBrocken
                FROM tbrocken
                ORDER BY dErstellt DESC
                LIMIT 1'
        );
        if ($data === null || empty($data->cBrocken)) {
            $data            = new stdClass();
            $data->cBrocken  = $input;
            $data->dErstellt = 'NOW()';
            $this->db->insert('tbrocken', $data);
        } elseif ($data->cBrocken !== $input && \strlen($data->cBrocken) > 0) {
            $this->db->update(
                'tbrocken',
                'cBrocken',
                $data->cBrocken,
                (object)['cBrocken' => $input, 'dErstellt' => 'NOW()']
            );
        }
        $this->cache->flushTags([\CACHING_GROUP_CORE]);
    }
}
