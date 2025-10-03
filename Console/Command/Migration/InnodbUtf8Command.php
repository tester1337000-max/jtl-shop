<?php

declare(strict_types=1);

namespace JTL\Console\Command\Migration;

use JTL\Console\Command\Command;
use JTL\DB\Migration\Check;
use JTL\DB\Migration\Info;
use JTL\DB\Migration\InnoDB;
use JTL\DB\Migration\Structure;
use JTL\Shop;
use stdClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Systemcheck\Platform\DBServerInfo;

#[AsCommand(
    name: 'migration:innodbutf8',
    description: 'Execute Innodb and UTF-8 migration',
    hidden: false
)]
class InnodbUtf8Command extends Command
{
    /**
     * @var string[]
     */
    private array $excludeTables = [];

    private int $errCounter = 0;

    private Check $check;

    private Info $info;

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->check = new Check($this->db);
        $this->info  = new Info($this->db);
        $structure   = new Structure($this->db, $this->cache, $this->info);
        $innodb      = new InnoDB($this->db, $this->info, $this->check, $structure, Shop::Container()->getGetText());

        $shopTables = \array_keys($structure->getDBFileStruct());
        $tableData  = $this->check->getNextTableNeedingMigration($this->excludeTables);
        while ($tableData !== null) {
            if ($this->errCounter > 20) {
                $this->getIO()->error('aborted due to too many errors');

                return BaseCommand::FAILURE;
            }

            if (!\in_array($tableData->TABLE_NAME, $shopTables, true)) {
                $this->excludeTables[] = $tableData->TABLE_NAME;
                $tableData             = $this->check->getNextTableNeedingMigration($this->excludeTables);
                continue;
            }

            $output->write('migrate ' . $tableData->TABLE_NAME . '... ');

            if ($this->info->isTableInUse($tableData)) {
                $tableData = $this->nextWithFailure($output, $tableData, false, 'already in use!');
                continue;
            }

            $this->prepareTable($tableData);
            $migrationState = $this->check->getNeededMigrationsForTable($tableData);
            if ($migrationState === Check::MIGRATE_NONE) {
                $tableData = $this->nextWithFailure($output, $tableData, true, 'skip');
                continue;
            }

            $migrate = true;
            if (($migrationState & Check::MIGRATE_TABLE) !== Check::MIGRATE_NONE) {
                $fkSQLs = $this->info->getForeignKeyStatements($tableData->TABLE_NAME);
                foreach ($fkSQLs->dropFK as $fkSQL) {
                    $this->db->executeExQuery($fkSQL);
                }
                $migrate = $this->db->executeExQuery($innodb->sqlMoveToInnoDB($tableData));
                foreach ($fkSQLs->createFK as $fkSQL) {
                    $this->db->executeExQuery($fkSQL);
                }
            }
            if ($migrate && ($migrationState & Check::MIGRATE_COLUMN) !== Check::MIGRATE_NONE) {
                $migrate = $this->db->executeExQuery($innodb->sqlConvertUTF8($tableData));
            }

            if (!$migrate) {
                $tableData = $this->nextWithFailure($output, $tableData);
                continue;
            }

            $this->releaseTable($tableData);
            $output->writeln('<info> ✔ </info>');

            $tableData = $this->check->getNextTableNeedingMigration($this->excludeTables);
        }

        if ($this->errCounter > 0) {
            $this->getIO()->warning('done with ' . $this->errCounter . ' errors');
        } else {
            $this->getIO()->success('all done');
        }

        return BaseCommand::SUCCESS;
    }

    private function prepareTable(stdClass $table): void
    {
        if ($this->info->getDBServerInfo()->isSupportedVersion() >= DBServerInfo::SUPPORTED) {
            return;
        }
        // If MySQL version is lower than 5.6 use alternative lock method
        // and delete all fulltext indexes because these are not supported
        $this->db->executeExQuery($this->info->addTableLockInfo($table));
        $fulltextIndizes = $this->info->getFulltextIndizes($table->TABLE_NAME);
        if ($fulltextIndizes) {
            foreach ($fulltextIndizes as $fulltextIndex) {
                /** @noinspection SqlResolve */
                $this->db->executeExQuery(
                    'ALTER TABLE `' . $table->TABLE_NAME . '`
                        DROP KEY `' . $fulltextIndex->INDEX_NAME . '`'
                );
            }
        }
    }

    private function releaseTable(stdClass $table): void
    {
        if ($this->info->getDBServerInfo()->isSupportedVersion() >= DBServerInfo::SUPPORTED) {
            return;
        }

        $this->db->executeExQuery($this->info->clearTableLockInfo($table));
    }

    private function nextWithFailure(
        OutputInterface $output,
        stdClass $table,
        bool $releaseTable = true,
        string $msg = 'failure!'
    ): ?stdClass {
        $this->errCounter++;
        $output->writeln('<error>' . $msg . '</error>');
        $this->excludeTables[] = $table->TABLE_NAME;
        if ($releaseTable) {
            $this->releaseTable($table);
        }

        return $this->check->getNextTableNeedingMigration($this->excludeTables);
    }
}
