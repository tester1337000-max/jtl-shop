<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use Exception;
use JTL\Backend\Upgrade\Checker;
use JTL\Backend\Upgrade\Release\ReleaseCollection;
use JTL\Backend\Upgrade\Release\ReleaseDB;
use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Router\Route;

/**
 * @since 5.6.0
 */
final class ShopVersionUpgrade extends AbstractStatusCheck
{
    private Checker $checker;

    public function __construct(DbInterface $db, JTLCacheInterface $cache, string $adminURL)
    {
        parent::__construct($db, $cache, $adminURL);
        try {
            $this->checker = new Checker($this->db, new ReleaseCollection(new ReleaseDB($this->db)));
            $this->checker->check();
        } catch (Exception) {
        }
    }

    public function isOK(): bool
    {
        try {
            return $this->checker->hasUpgrade() === false;
        } catch (Exception) {
            return true;
        }
    }

    public function getURL(): string
    {
        return $this->adminURL . Route::UPGRADE;
    }

    public function getTitle(): string
    {
        return \__('hasShopVersionUpgradeTitle');
    }

    public function generateMessage(): void
    {
        $rel = $this->checker->getLatestUpgrade();
        if ($rel === null) {
            return;
        }
        $this->addNotification(
            \sprintf(
                \__('New shop version %s (%s) available.'),
                $rel->version,
                $rel->channel->value
            )
        );
    }
}
