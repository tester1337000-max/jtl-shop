<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Router\Route;
use stdClass;

class OrphanedCategories extends AbstractStatusCheck
{
    protected bool $includeInServiceReport = true;

    /**
     * @return stdClass[]
     */
    public function getOrphanedCategories(): array
    {
        $this->data = $this->db->getObjects(
            'SELECT kKategorie, cName
                FROM tkategorie
                WHERE kOberkategorie > 0
                    AND kOberkategorie NOT IN (SELECT DISTINCT kKategorie FROM tkategorie)'
        );

        return $this->data;
    }

    public function isOK(): bool
    {
        return \count($this->getOrphanedCategories()) === 0;
    }

    public function getTitle(): string
    {
        return \__('orphanedCategories');
    }

    public function getURL(): string
    {
        return $this->adminURL . Route::CATEGORYCHECK;
    }

    public function generateMessage(): void
    {
    }
}
