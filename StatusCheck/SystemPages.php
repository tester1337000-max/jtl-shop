<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Backend\NotificationEntry;
use JTL\Link\Admin\LinkAdmin;
use JTL\Router\Route;

class SystemPages extends AbstractStatusCheck
{
    protected int $messageType = NotificationEntry::TYPE_DANGER;

    private int $missingSystemPages = 0;

    public function isOK(): bool
    {
        $linkAdmin                = new LinkAdmin($this->db, $this->cache);
        $this->missingSystemPages = $linkAdmin->getMissingSystemPages()->count();

        return $this->missingSystemPages === 0;
    }

    public function getURL(): ?string
    {
        return $this->adminURL . Route::LINKS;
    }

    public function getTitle(): string
    {
        return \__('Missing special pages');
    }

    public function generateMessage(): void
    {
        $this->addNotification(\sprintf(\__('%d special pages are missing.'), $this->missingSystemPages));
    }
}
