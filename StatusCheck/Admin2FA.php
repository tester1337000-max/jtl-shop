<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Router\Route;

class Admin2FA extends AbstractStatusCheck
{
    public function isOK(): bool
    {
        $cnt = $this->db->getSingleInt(
            'SELECT COUNT(*) AS cnt
                FROM tadminlogin
                WHERE kAdminlogingruppe = 1
                  AND b2FAauth = 0
                  AND bAktiv = 1',
            'cnt'
        );

        return $cnt === 0;
    }

    public function getURL(): ?string
    {
        return $this->adminURL . Route::USERS;
    }

    public function getTitle(): string
    {
        return \__('adminsWithoutTwoFATitle');
    }

    public function generateMessage(): void
    {
        $this->addNotification(description: \__('adminsWithoutTwoFADesc'));
    }
}
