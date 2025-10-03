<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Backend\NotificationEntry;
use JTL\Router\Route;
use JTL\Update\Updater;

class PendingUpdates extends AbstractStatusCheck
{
    protected int $messageType = NotificationEntry::TYPE_DANGER;

    protected bool $stopFurtherChecks = true;

    public function isOK(): bool
    {
        return !(new Updater($this->db))->hasPendingUpdates();
    }

    public function getURL(): ?string
    {
        return $this->adminURL . Route::DBUPDATER;
    }

    public function getTitle(): string
    {
        return \__('hasPendingUpdatesTitle');
    }

    public function generateMessage(): void
    {
        $this->addNotification(\__('hasPendingUpdatesMessage'));
    }
}
