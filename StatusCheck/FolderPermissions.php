<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Backend\NotificationEntry;
use JTL\Router\Route;
use stdClass;
use Systemcheck\Platform\Filesystem;

class FolderPermissions extends AbstractStatusCheck
{
    protected bool $includeInServiceReport = true;

    public const CACHE_ID_FOLDER_PERMISSIONS = 'validFolderPermissions';

    protected int $messageType = NotificationEntry::TYPE_DANGER;

    private string $hash = 'validFolderPermissions';

    public function isOK(?string &$hash = null): bool
    {
        /** @var stdClass|false $struct */
        $struct = $this->cache->get(self::CACHE_ID_FOLDER_PERMISSIONS);
        if ($struct === false) {
            $filesystem = new Filesystem(\PFAD_ROOT);
            $filesystem->getFoldersChecked();
            $struct = $filesystem->getFolderStats();
            $this->cache->set(self::CACHE_ID_FOLDER_PERMISSIONS, $struct, [\CACHING_GROUP_STATUS]);
        }
        $this->hash = \md5($this->hash . '_' . $struct->nCountInValid);

        return $struct->nCountInValid === 0;
    }

    public function getURL(): ?string
    {
        return $this->adminURL . Route::PERMISSIONCHECK;
    }

    public function getTitle(): string
    {
        return \__('validFolderPermissionsTitle');
    }

    public function generateMessage(): void
    {
        $this->addNotification(\__('validFolderPermissionsMessage'), $this->hash);
    }
}
