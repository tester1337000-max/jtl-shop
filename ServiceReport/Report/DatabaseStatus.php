<?php

declare(strict_types=1);

namespace JTL\ServiceReport\Report;

use JTL\Update\DBManager;

class DatabaseStatus implements ReportInterface
{
    /**
     * @return array<string, \stdClass>
     */
    public function getData(): array
    {
        return DBManager::getStatus(\DB_NAME);
    }
}
