<?php

declare(strict_types=1);

namespace JTL\Update;

use JTL\Shop;
use stdClass;

/**
 * Class DBManager
 * @package JTL\Update
 */
class DBManager
{
    /**
     * @return string[]
     */
    public static function getTables(): array
    {
        return Shop::Container()->getDB()->getCollection(
            "SHOW FULL TABLES 
                WHERE Table_type='BASE TABLE'"
        )->map(fn(stdClass $ele) => \current((array)$ele))->all();
    }

    /**
     * @return array<string, stdClass>
     */
    public static function getColumns(string $table): array
    {
        if (!\in_array($table, self::getTables(), true)) {
            return [];
        }
        $list    = [];
        $columns = Shop::Container()->getDB()->getObjects(\sprintf('SHOW FULL COLUMNS FROM `%s`', $table));
        foreach ($columns as $column) {
            $column->Type_info            = self::parseType($column->Type);
            $list[(string)$column->Field] = $column;
        }

        return $list;
    }

    /**
     * @return array<string, object{Index_type: 'INDEX', Columns: stdClass[]}&stdClass>
     */
    public static function getIndexes(string $table): array
    {
        if (!\in_array($table, self::getTables(), true)) {
            return [];
        }
        $list    = [];
        $indexes = Shop::Container()->getDB()->getObjects(\sprintf('SHOW INDEX FROM `%s`', $table));
        foreach ($indexes as $index) {
            $container = (object)[
                'Index_type' => 'INDEX',
                'Columns'    => []
            ];
            if (!isset($list[$index->Key_name])) {
                $list[(string)$index->Key_name] = $container;
            }
            $list[(string)$index->Key_name]->Columns[$index->Column_name] = $index;
        }
        foreach ($list as $item) {
            if (\count($item->Columns) === 0) {
                continue;
            }
            /** @var stdClass $column */
            $column = \reset($item->Columns);
            if ($column->Key_name === 'PRIMARY') {
                $item->Index_type = 'PRIMARY';
            } elseif ($column->Index_type === 'FULLTEXT') {
                $item->Index_type = 'FULLTEXT';
            } elseif ((int)$column->Non_unique === 0) {
                $item->Index_type = 'UNIQUE';
            }
        }

        return $list;
    }

    /**
     * @return ($table is null ? array<string, stdClass> : stdClass)
     */
    public static function getStatus(string $database, ?string $table = null): array|stdClass
    {
        $database = Shop::Container()->getDB()->escape($database);
        if ($table !== null) {
            /** @var stdClass $status */
            $status = Shop::Container()->getDB()->getSingleObject(
                \sprintf(
                    'SHOW TABLE STATUS 
                        FROM `%s`
                        WHERE name = :tbl',
                    $database
                ),
                ['tbl' => $table]
            );

            return $status;
        }
        $list   = [];
        $status = Shop::Container()->getDB()->getObjects(
            \sprintf(
                'SHOW TABLE STATUS 
                    FROM `%s`',
                $database
            )
        );
        foreach ($status as $s) {
            $list[(string)$s->Name] = $s;
        }

        return $list;
    }

    public static function parseType(string $type): stdClass
    {
        $result = (object)[
            'Name'     => null,
            'Size'     => null,
            'Unsigned' => false
        ];
        $types  = \explode(' ', $type);
        if (isset($types[1]) && $types[1] === 'unsigned') {
            $result->Unsigned = true;
        }
        if (\preg_match('/([a-z]+)(?:\((.*)\))?/', $types[0], $m)) {
            $result->Size = 0;
            $result->Name = $m[1];
            if (isset($m[2])) {
                $size         = \explode(',', $m[2]);
                $size         = \count($size) === 1 ? $size[0] : $size;
                $result->Size = $size;
            }
        }

        return $result;
    }
}
