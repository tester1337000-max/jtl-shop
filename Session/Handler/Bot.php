<?php

declare(strict_types=1);

namespace JTL\Session\Handler;

use JTL\Shop;

/**
 * Class Bot
 * @package JTL\Session\Handler
 */
class Bot extends JTLDefault
{
    protected string $sessionID;

    public function __construct(private readonly bool $doSave = false)
    {
        $this->sessionID = \session_id() ?: ('bot' . \md5(\uniqid('', true)));
    }

    /**
     * @inheritdoc
     */
    public function open(string $path, string $name): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function read(string $id): string|false
    {
        $sessionData = '';
        if ($this->doSave === true) {
            $sessionData = Shop::Container()->getCache()->get($this->sessionID) ?: '';
        }

        return $sessionData;
    }

    /**
     * @inheritdoc
     */
    public function write(string $id, string $data): bool
    {
        if ($this->doSave === true) {
            Shop::Container()->getCache()->set($this->sessionID, $data, [\CACHING_GROUP_CORE]);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function destroy(string $id): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function gc(int $max_lifetime): int|false
    {
        return 0;
    }
}
