<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\License\Manager;
use JTL\License\Mapper;
use JTL\Router\Route;

class ExpiredLicenses extends AbstractStatusCheck
{
    private string $hash = '';

    public function isOK(): bool
    {
        $mapper       = new Mapper(new Manager($this->db, $this->cache));
        $toBeExpired  = $mapper->getCollection()->getAboutToBeExpired()->count();
        $boundExpired = $mapper->getCollection()->getBoundExpired()->count();
        $this->hash   = \md5('hasLicenseExpirations_' . $toBeExpired . '_' . $boundExpired);

        return $toBeExpired === 0 && $boundExpired === 0;
    }

    public function getURL(): ?string
    {
        return $this->adminURL . Route::LICENSE;
    }

    public function getTitle(): string
    {
        return \__('hasLicenseExpirationsTitle');
    }

    public function generateMessage(): void
    {
        $this->addNotification(\__('hasLicenseExpirationsMessage'), $this->hash);
    }
}
