<?php

declare(strict_types=1);

namespace JTL\OPC;

/**
 * Class Locker
 * @package JTL\OPC
 */
class Locker
{
    public function __construct(protected PageDB $pageDB)
    {
    }

    /**
     * Try to lock draft to only be manipulated by this one user
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function lock(string $userName, Page $page): bool
    {
        if ($userName === '') {
            throw new \InvalidArgumentException('Name of the user that locks this page is empty.');
        }
        $lockedBy = $page->getLockedBy();
        $lockedAt = $page->getLockedAt();
        if ($lockedBy !== '' && $lockedBy !== $userName && $lockedAt !== null && \strtotime($lockedAt) + 60 > \time()) {
            return false;
        }
        $page->setLockedBy($userName)
            ->setLockedAt(\date('Y-m-d H:i:s'));

        $this->pageDB->saveDraftLockStatus($page);

        return true;
    }

    /**
     * Unlock this draft if it was locked
     *
     * @param Page $page
     * @throws \Exception
     */
    public function unlock(Page $page): void
    {
        $page->setLockedBy('');
        $this->pageDB->saveDraftLockStatus($page);
    }
}
