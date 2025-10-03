<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Backend\NotificationEntry;
use JTL\Router\Route;

class SOAPSupport extends AbstractStatusCheck
{
    protected bool $includeInServiceReport = true;

    protected int $messageType = NotificationEntry::TYPE_INFO;

    public function isOK(): bool
    {
        return \extension_loaded('soap');
    }

    public function getURL(): ?string
    {
        return $this->adminURL . Route::CONFIG . '?kSektion=6';
    }

    public function getTitle(): string
    {
        return \__('ustIdMiasCheckTitle');
    }

    public function generateMessage(): void
    {
        $this->addNotification(\__('ustIdMiasCheckMessage'));
    }
}
