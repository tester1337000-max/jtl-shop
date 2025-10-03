<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use InvalidArgumentException;
use JTL\DB\Migration\Info;
use JTL\DB\Migration\Structure;
use JTL\Router\Route;
use stdClass;

class DBStructure extends AbstractStatusCheck
{
    protected bool $includeInServiceReport = true;

    public const CACHE_ID_DATABASE_STRUCT = 'validDatabaseStruct';

    /**
     * checks the db-structure against 'admin/includes/shopmd5files/dbstruct_[shop-version].json'
     */
    public function isOK(): bool
    {
        $info   = new Info($this->db);
        $struct = new Structure($this->db, $this->cache, $info);
        /** @var array{current: array<string, stdClass>, original: array<string, array<int, string>>}|false $dbStruct */
        $dbStruct = $this->cache->get(self::CACHE_ID_DATABASE_STRUCT);
        if ($dbStruct === false) {
            try {
                $fileStruct = $struct->getDBFileStruct();
            } catch (InvalidArgumentException) {
                $fileStruct = [];
            }
            $dbStruct = [
                'current'  => $struct->getDBStruct(true),
                'original' => $fileStruct
            ];
            $this->cache->set(self::CACHE_ID_DATABASE_STRUCT, $dbStruct, [\CACHING_GROUP_STATUS]);
        }

        return \is_array($dbStruct['current'])
            && \is_array($dbStruct['original'])
            && \count($struct->compareDBStruct($dbStruct['original'], $dbStruct['current'])) === 0;
    }

    public function getURL(): ?string
    {
        return $this->adminURL . Route::DBCHECK;
    }

    public function getTitle(): string
    {
        return \__('validDatabaseStructTitle');
    }

    public function generateMessage(): void
    {
        $this->addNotification(\__('validDatabaseStructMessage'));
    }
}
