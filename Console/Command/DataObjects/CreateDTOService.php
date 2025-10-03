<?php

declare(strict_types=1);

namespace JTL\Console\Command\DataObjects;

use DateTime;
use Exception;
use JTL\Abstracts\AbstractService;
use JTL\Smarty\CLISmarty;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\Visibility;
use stdClass;

class CreateDTOService extends AbstractService
{
    public function __construct(protected readonly CreateDTORepository $repository)
    {
    }

    protected function getRepository(): CreateDTORepository
    {
        return $this->repository;
    }

    /**
     * @throws FilesystemException
     */
    public function execute(string $tableName, string $targetDir): string
    {
        try {
            $mappedColumns = $this->getColumnMappingFromRESTModels();
            $modelName     = $this->writeDataModel($targetDir, $tableName, $mappedColumns);
        } catch (Exception $e) {
            throw new \RuntimeException('Error: ' . $e->getMessage());
        }

        return $modelName;
    }

    /**
     * @param string[] $typeMap
     * @param mixed    $tmpType
     * @return mixed
     */
    public function getPhpTypeFromArray(array $typeMap, mixed $tmpType): mixed
    {
        return \array_reduce($typeMap, callback: static function ($carry, $item) use ($tmpType) {
            if (!isset($carry) && \preg_match("/$item/", $tmpType)) {
                $carry = \explode('|', $item, 2)[0];
            }

            return $carry;
        });
    }

    /**
     * @param array<string, array{count: int, tables: string[]}> $foundColumns
     * @param string                                             $field
     * @param string                                             $showtable
     * @return array<string, array{count: int, tables: string[]}>
     */
    public function getFoundColumns(array $foundColumns, string $field, string $showtable): array
    {
        if (isset($foundColumns[$field]) && !isset($foundColumns[$field]['count'])) {
            $foundColumns[$field]['count']  = 1;
            $foundColumns[$field]['tables'] = [$showtable];
        } else {
            $foundColumns[$field]['count']++;
            $foundColumns[$field]['tables'][] = $showtable;
        }

        return $foundColumns;
    }

    /**
     * @param string[]|string                      $line
     * @param array<string, array<string, string>> $columnList
     * @param string                               $tableName
     * @param mixed                                $file
     * @return array<string, array<string, string>>
     */
    public function getColumnList(array|string $line, array $columnList, string $tableName, mixed $file): array
    {
        if (!\is_string($line) || !\str_contains($line, '$attributes[')) {
            return $columnList;
        }
        $prepared       = \str_replace('$attributes[\'', '', $line);
        $mappedName     = \trim(\substr($prepared, 0, (int)(\strpos($prepared, '\''))));
        $columnPrepared = \trim(
            \str_replace(['=DataAttribute::create(\'', $mappedName . '\']'], '', $prepared)
        );
        if (\str_contains($columnPrepared, '\'') !== false && !\str_contains($columnPrepared, '->')) {
            $columnName = \trim(\substr($columnPrepared, 0, ((int)\strpos($columnPrepared, '\''))));
            /** @var string $idx */
            $idx = $tableName !== ''
                ? $tableName
                : \str_replace('Model.php', '', $file);

            $columnList[$idx][$columnName] = $mappedName;
        }

        return $columnList;
    }

    /**
     * @param string[]|string $line
     * @param string          $tableName
     * @return string|null
     */
    public function getTableName(array|string $line, string $tableName): ?string
    {
        if ($tableName === '' && (\is_string($line) && \str_contains($line, 'iongetTableName()'))) {
            $tableNamePos = \strpos($line, 'return\'');
            $linePos      = \strpos($line, '\'');
            if ($tableNamePos === false || $linePos === false) {
                return $tableName;
            }
            $prepTableName = \str_replace(
                'return\'',
                '',
                \trim(\substr($line, $tableNamePos, $linePos))
            );
            $linePos       = \strpos($prepTableName, '\'');
            if ($linePos === false) {
                return $tableName;
            }
            $tableName = \substr($prepTableName, 0, $linePos);
        }

        return $tableName;
    }

    /**
     * @param string   $line
     * @param resource $fileHandle
     * @return string|string[]
     */
    public function getLine(string $line, $fileHandle): string|array
    {
        while (!\str_contains($line, ';') && !\str_contains($line, '}') && $line !== '@') {
            $line .= \fgets($fileHandle);
            if ($line === '') {
                $line = '@';
            }
            $line = \str_replace(["\r\n", "\n", \chr(13), ' '], '', $line);
        }

        return $line;
    }

    /**
     * @param string[] $attrib
     */
    public function getTableDesc(array $attrib, string $dataType, string $phpType, string $castMap): stdClass
    {
        return (object)[
            'name'         => $attrib['Field'],
            'dataType'     => $dataType,
            'phpType'      => $phpType,
            'default'      => isset($attrib['Default'])
                ? "self::cast('" . $attrib['Default'] . "', '" . $dataType . "')"
                : 'null',
            'nullable'     => $attrib['Null'] === 'YES' ? 'true' : 'false',
            'isPrimaryKey' => $attrib['Key'] === 'PRI' ? 'true' : 'false',
            'paramTypes'   => $castMap
        ];
    }

    /**
     * @param string[] $typeMap
     */
    public function getPhpType(array $typeMap, string $dataType): string
    {
        return \array_reduce($typeMap, callback: static function ($carry, $item) use ($dataType) {
            if (!isset($carry) && \preg_match("/$item/", $dataType)) {
                $carry = \explode('|', $item, 2)[0];
            }

            return $carry;
        });
    }

    /**
     * @param string                                             $showtable
     * @param array<string, array{count: int, tables: string[]}> $foundColumns
     * @param string[]                                           $hits
     * @param string[]                                           $typeMap
     * @return array{array<string, array{count: int, tables: string[]}>, string[]}
     */
    public function getColHits(string $showtable, array $foundColumns, array $hits, array $typeMap): array
    {
        $showAttributes = $this->getRepository()->getTableDescription($showtable);
        foreach ($showAttributes ?: [] as $showAttribute) {
            $foundColumns[$showAttribute['Field']]['name']     = $showAttribute['Field'];
            $foundColumns                                      = $this->getFoundColumns(
                $foundColumns,
                $showAttribute['Field'],
                $showtable
            );
            $foundColumns[$showAttribute['Field']]['dataType'] =
                \preg_match('/^([a-zA-Z\d]+)/', $showAttribute['Type'], $hits) ? $hits[1] : $showAttribute['Type'];
            $tmpType                                           = $foundColumns[$showAttribute['Field']]['dataType'];
            $foundColumns[$showAttribute['Field']]['phpType']  =
                $this->getPhpTypeFromArray($typeMap, $tmpType);
        }

        return [$foundColumns, $hits];
    }

    /**
     * @param array<string, array<string, string>> $mappedColumns
     * @throws FilesystemException
     * @throws \SmartyException
     */
    protected function writeDataModel(string $targetDir, string $table, array $mappedColumns): string
    {
        $tables          = $this->getRepository()->getAllTables();
        $datetime        = new DateTime('NOW');
        $table           = \strtolower($table);
        $deprefixedTable = $table;
        if (\str_starts_with($deprefixedTable, 't')) {
            $deprefixedTable = \substr($deprefixedTable, 1);
        }
        $modelName    = \ucfirst($deprefixedTable) . 'DataObject';
        $modelPath    = $modelName . '.php';
        $tableDesc    = [];
        $attribs      = $this->getRepository()->getTableDescription($table);
        $typeMap      = [
            'bool|boolean|tinyint',
            'int|smallint|mediumint|integer|bigint|decimal|dec',
            'float|double',
            'string|year|char|varchar|tinytext|text|mediumtext|enum',
            'DateTime|date|datetime|timestamp',
        ];
        $castMap      = [
            'bool'     => 'int|bool|string',
            'int'      => 'int|string',
            'DateTime' => 'string',
            'string'   => 'string',
            'float'    => 'float',
        ];
        $foundColumns = [];
        if ($tables !== false && $attribs !== false) {
            $hits = [];
            foreach ($tables as $showTable) {
                if (!\str_starts_with($showTable[0], 't') && !\str_starts_with($showTable[0], 'l')) {
                    continue;
                }
                [$foundColumns, $hits] = $this->getColHits($showTable[0], $foundColumns, $hits, $typeMap);
                foreach ($attribs as $attrib) {
                    $dataType    = (string)\preg_match('/^([a-zA-Z\d]+)/', $attrib['Type'], $hits)
                        ? $hits[1]
                        : $attrib['Type'];
                    $phpType     = $this->getPhpType($typeMap, $dataType);
                    $tableDesc[] = $this->getTableDesc($attrib, $dataType, $phpType, $castMap[$phpType]);
                }
            }
        }
        $content    = (new CLISmarty())->assign('tableName', $table)
            ->assign('modelName', $modelName)
            ->assign('created', $datetime->format(DateTime::RSS))
            ->assign('tableDesc', $tableDesc)
            ->assign('mapping', $mappedColumns[$table])
            ->fetch(__DIR__ . '/Template/dataobject.class.tpl');
        $fileSystem = new Filesystem(
            new LocalFilesystemAdapter($targetDir),
            [Config::OPTION_DIRECTORY_VISIBILITY => Visibility::PUBLIC]
        );

        $fileSystem->write($modelPath, $content);

        return $modelPath;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function getColumnMappingFromRESTModels(): array
    {
        $dir   = \PFAD_ROOT . \PFAD_INCLUDES . '/src/REST/Models';
        $files = \scandir($dir);
        if (!\is_array($files)) {
            return [];
        }
        $columnList = [];
        $curDir     = \getcwd();
        \chdir($dir);
        foreach ($files as $file) {
            if (\in_array($file, ['.', '..'])) {
                continue;
            }
            $fileHandle = \fopen($file, 'rb');
            if ($fileHandle === false) {
                continue;
            }
            $tableName = '';
            while (!\feof($fileHandle)) {
                $line       = '';
                $line       = $this->getLine($line, $fileHandle);
                $tableName  = $this->getTableName($line, $tableName ?? '');
                $columnList = $this->getColumnList($line, $columnList, $tableName ?? '', $file);
            }

            \fclose($fileHandle);
        }
        if (!empty($curDir)) {
            \chdir($curDir);
        }

        return $columnList;
    }
}
