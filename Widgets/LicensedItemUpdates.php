<?php

declare(strict_types=1);

namespace JTL\Widgets;

use JTL\Backend\AuthToken;
use JTL\Backend\Permissions;
use JTL\License\Manager;
use JTL\License\Mapper;
use JTL\License\Struct\ExsLicense;
use JTL\License\Struct\License;
use JTL\License\Struct\Release;
use JTL\Shop;

/**
 * Class LicensedItemUpdates
 * @package JTL\Widgets
 */
class LicensedItemUpdates extends AbstractWidget
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $manager       = new Manager($this->getDB(), Shop::Container()->getCache());
        $mapper        = new Mapper($manager);
        $token         = AuthToken::getInstance($this->getDB());
        $data          = $manager->getLicenseData();
        $collection    = $mapper->getCollection();
        $testLicenses  = $collection->filter(static function (ExsLicense $exsLicense): bool {
            return $exsLicense->getLicense()->getType() === License::TYPE_TEST;
        });
        $lastPurchases = $collection->sort(static function (ExsLicense $a, ExsLicense $b): int {
            return $b->getLicense()->getCreated() <=> $a->getLicense()->getCreated();
        })->slice(0, 3);
        $updates       = $collection->getUpdateableItems();
        $securityFixes = 0;
        $updates->each(static function (ExsLicense $exsLicense) use (&$securityFixes): void {
            $avail = $exsLicense->getReleases()->getAvailable();
            if ($avail !== null && ($avail->includesSecurityFixes() || $avail->getType() === Release::TYPE_SECURITY)) {
                ++$securityFixes;
            }
        });

        $this->getSmarty()->assign('hasAuth', $token->isValid())
            ->assign('lastUpdate', $data->timestamp ?? null)
            ->assign('lastPurchases', $lastPurchases)
            ->assign('licenses', $collection)
            ->assign('aboutToExpire', $collection->getAboutToBeExpired())
            ->assign('expirations', $collection->getExpired()->count())
            ->assign('securityFixes', $securityFixes)
            ->assign('testLicenses', $testLicenses)
            ->assign('licenseItemUpdates', $updates);

        $this->setPermission(Permissions::LICENSE_MANAGER);
    }

    /**
     * @inheritdoc
     */
    public function getContent(): string
    {
        return $this->getSmarty()->fetch('tpl_inc/widgets/licensedItemUpdates.tpl');
    }
}
