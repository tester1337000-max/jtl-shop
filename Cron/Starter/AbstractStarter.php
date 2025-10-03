<?php

declare(strict_types=1);

namespace JTL\Cron\Starter;

/**
 * Class AbstractStarter
 * @package JTL\Cron\Starter
 */
abstract class AbstractStarter implements StarterInterface
{
    /**
     * timeout in ms
     */
    protected int $timeout = 150;

    /**
     * @var non-empty-string
     */
    protected string $url;

    /**
     * @inheritdoc
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * @inheritdoc
     */
    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }

    /**
     * @inheritdoc
     */
    public function getURL(): string
    {
        return $this->url;
    }

    /**
     * @inheritdoc
     */
    public function setURL(string $url): void
    {
        $this->url = $url;
    }
}
