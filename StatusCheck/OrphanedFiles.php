<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Backend\FileCheck;
use JTL\Router\Route;

class OrphanedFiles extends AbstractStatusCheck
{
    protected bool $includeInServiceReport = true;

    public const CACHE_ID_ORPHANED_FILE_STRUCT = 'validOrphanedFilesStruct';

    private string $hash = 'validModifiedFileStruct';

    /**
     * checks the db-structure against 'admin/includes/shopmd5files/dbstruct_[shop-version].json'
     */
    public function isOK(): bool
    {
        $struct = $this->cache->get(self::CACHE_ID_ORPHANED_FILE_STRUCT);
        if (!\is_int($struct)) {
            $check   = new FileCheck();
            $files   = [];
            $stats   = 0;
            $csvFile = \PFAD_ROOT . \PFAD_ADMIN . \PFAD_INCLUDES . \PFAD_SHOPMD5
                . 'deleted_files_' . $check->getVersionString() . '.csv';
            $struct  = $check->validateCsvFile($csvFile, $files, $stats) === FileCheck::OK
                ? $stats
                : 1;
            $this->cache->set(self::CACHE_ID_ORPHANED_FILE_STRUCT, $struct, [\CACHING_GROUP_STATUS]);
        }
        $this->hash = \md5('validOrphanedFilesStruct_' . $struct);

        return $struct === 0;
    }

    public function getURL(): ?string
    {
        return $this->adminURL . Route::FILECHECK;
    }

    public function getTitle(): string
    {
        return \__('validOrphanedFilesTitle');
    }

    public function generateMessage(): void
    {
        $this->addNotification(\__('validModifiedFileStructMessage'), $this->hash);
    }
}
