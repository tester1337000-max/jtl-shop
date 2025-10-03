<?php

declare(strict_types=1);

namespace JTL\Console\Command\DataObjects;

use JTL\Abstracts\AbstractDBRepository;
use PDOStatement;

class CreateDTORepository extends AbstractDBRepository
{
    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return '';
    }

    public function getTableDescription(string $tablename): false|PDOStatement
    {
        return $this->getDB()->getPDO()->query('DESCRIBE `' . $tablename . '`');
    }

    public function getAllTables(): false|PDOStatement
    {
        return $this->getDB()->getPDO()->query('SHOW TABLES');
    }
}
