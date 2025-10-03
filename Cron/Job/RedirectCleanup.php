<?php

declare(strict_types=1);

namespace JTL\Cron\Job;

use JTL\Cron\Job;
use JTL\Cron\JobInterface;
use JTL\Cron\QueueEntry;

/**
 * Class RedirectCleanup
 * @package JTL\Cron\Job
 */
final class RedirectCleanup extends Job
{
    public const MAX_AGE_DAYS = 180;

    /**
     * @inheritdoc
     */
    public function start(QueueEntry $queueEntry): JobInterface
    {
        parent::start($queueEntry);
        $this->db->query('ANALYZE TABLE tredirect');
        $this->db->query('ANALYZE TABLE tredirectreferer');
        $this->db->queryPrepared(
            'DELETE
                FROM tredirect
                WHERE tredirect.cToUrl = \'\'
                    AND UNIX_TIMESTAMP() - (:days * 24 * 60 * 60) >
                        (SELECT MAX(dDate) 
                             FROM tredirectreferer
                             WHERE tredirectreferer.kRedirect = tredirect.kRedirect
                       )',
            ['days' => self::MAX_AGE_DAYS]
        );
        $this->db->query(
            'DELETE FROM tredirectreferer
                WHERE kRedirect NOT IN (SELECT kRedirect FROM tredirect)'
        );
        $this->setFinished(true);

        return $this;
    }
}
