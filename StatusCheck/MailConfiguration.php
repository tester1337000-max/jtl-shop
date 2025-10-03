<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Backend\NotificationEntry;
use JTL\Router\Route;
use JTL\Settings\Option\Email;
use JTL\Settings\Settings;

class MailConfiguration extends AbstractStatusCheck
{
    protected bool $includeInServiceReport = true;

    protected int $messageType = NotificationEntry::TYPE_DANGER;

    private string $hash = '';

    public function isOK(): bool
    {
        $method     = Settings::stringValue(Email::MAIL_METHOD);
        $this->hash = \md5('hasInsecureMailConfig_' . $method);

        return $method !== 'smtp' || !empty(\trim(Settings::stringValue(Email::SMTP_ENCRYPTION)));
    }

    public function getURL(): ?string
    {
        return $this->adminURL . Route::CONFIG . '?kSektion=3';
    }

    public function getTitle(): string
    {
        return \__('hasInsecureMailConfigTitle');
    }

    public function generateMessage(): void
    {
        $this->addNotification(\__('hasInsecureMailConfigMessage'), $this->hash);
    }
}
