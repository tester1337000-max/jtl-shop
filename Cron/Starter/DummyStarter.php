<?php

declare(strict_types=1);

namespace JTL\Cron\Starter;

/**
 * Class DummyStarter
 * @package JTL\Cron\Starter
 */
class DummyStarter extends AbstractStarter
{
    /**
     * @inheritdoc
     */
    public function start(): bool
    {
        return true;
    }
}
