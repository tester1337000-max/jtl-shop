<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Router\Route;
use JTL\Shop;
use stdClass;

use function Functional\none;

class TwoFAHashes extends AbstractStatusCheck
{
    public function isOK(): bool
    {
        $passwordService = Shop::Container()->getPasswordService();
        $hashes          = $this->db->getObjects(
            'SELECT *
                FROM tadmin2facodes
                GROUP BY kAdminlogin'
        );

        return none($hashes, fn(stdClass $hash): bool => $passwordService->needsRehash($hash->cEmergencyCode));
    }

    public function getURL(): ?string
    {
        return $this->adminURL . Route::USERS;
    }

    public function getTitle(): string
    {
        return \__('needPasswordRehash2FATryTitle');
    }

    public function generateMessage(): void
    {
        $this->addNotification(\__('needPasswordRehash2FATryMessage'));
    }
}
