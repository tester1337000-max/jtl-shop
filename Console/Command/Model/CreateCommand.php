<?php

declare(strict_types=1);

namespace JTL\Console\Command\Model;

use DateTime;
use Exception;
use JTL\Console\Command\Command;
use JTL\Smarty\CLISmarty;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\Visibility;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'model:create',
    description: 'Create a new model for given table',
    hidden: false
)]
class CreateCommand extends Command
{
    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->addArgument('table', InputArgument::REQUIRED, 'Name of the table for that model')
            ->addArgument('target-dir', InputArgument::OPTIONAL, 'Shop installation dir', \PFAD_ROOT);
    }

    /**
     * @inheritdoc
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $tableName = \trim($input->getArgument('table') ?? '');
        $targetDir = \trim($input->getArgument('target-dir') ?? '');
        while ($tableName === null || \strlen($tableName) < 3) {
            $tableName = $this->getIO()->ask('Name of the table for that model');
        }
        $input->setArgument('table', $tableName);
        if (\strlen($targetDir) < 2) {
            $targetDir = $this->getIO()->ask('target-dir');
            $input->setArgument('target-dir', $targetDir);
        }
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io        = $this->getIO();
        $targetDir = $input->getArgument('target-dir') ?? \PFAD_ROOT;
        $tableName = $input->getArgument('table');
        try {
            $modelName = $this->writeDataModel($targetDir, $tableName);
        } catch (Exception $e) {
            $io->error('Error: ' . $e->getMessage());

            return Command::FAILURE;
        }
        $io->writeln(\sprintf('<info>Created DataModel:</info> <comment>%s</comment>', $modelName));

        return Command::SUCCESS;
    }

    protected function writeDataModel(string $targetDir, string $table): string
    {
        $datetime  = new DateTime('NOW');
        $table     = \strtolower($table);
        $modelName = 'T' . \ucfirst(\ltrim($table, 't')) . 'Model';
        $relPath   = 'models';
        $modelPath = $relPath . \DIRECTORY_SEPARATOR . $modelName . '.php';
        $tableDesc = [];
        $attribs   = $this->db->getPDO()->query('DESCRIBE ' . $table);
        $typeMap   = [
            'bool|boolean',
            'int|tinyint|smallint|mediumint|integer|bigint|decimal|dec',
            'float|double',
            'DateTime|date|datetime|timestamp',
            'DateInterval|time',
            'string|year|char|varchar|tinytext|text|mediumtext|enum',
        ];
        if ($attribs === false) {
            throw new Exception('Table ' . $table . ' not found');
        }
        foreach ($attribs as $attrib) {
            $dataType    = \preg_match('/^([a-zA-Z\d]+)/', $attrib['Type'], $hits) ? $hits[1] : $attrib['Type'];
            $tableDesc[] = (object)[
                'name'         => $attrib['Field'],
                'dataType'     => $dataType,
                'phpType'      => \array_reduce($typeMap, static function ($carry, $item) use ($dataType) {
                    if (!isset($carry) && \preg_match('/' . $item . '/', $dataType)) {
                        $carry = \explode('|', $item, 2)[0];
                    }

                    return $carry;
                }),
                'default'      => isset($attrib['Default'])
                    ? "self::cast('" . $attrib['Default'] . "', '" . $dataType . "')"
                    : 'null',
                'nullable'     => $attrib['Null'] === 'YES' ? 'true' : 'false',
                'isPrimaryKey' => $attrib['Key'] === 'PRI' ? 'true' : 'false',
            ];
        }
        $fileSystem = new Filesystem(
            new LocalFilesystemAdapter($targetDir),
            [Config::OPTION_DIRECTORY_VISIBILITY => Visibility::PUBLIC]
        );
        $content    = (new CLISmarty())->assign('tableName', $table)
            ->assign('modelName', $modelName)
            ->assign('created', $datetime->format(DateTime::RSS))
            ->assign('tableDesc', $tableDesc)
            ->fetch(__DIR__ . '/Template/model.class.tpl');

        $fileSystem->write($modelPath, $content);

        return $modelPath;
    }
}
