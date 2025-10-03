<?php

declare(strict_types=1);

namespace JTL\Console\Command\Backup;

use JTL\Console\Command\Command;
use JTL\Update\Updater;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'backup:db',
    description: 'Backup shop database',
    hidden: false
)]
class DatabaseCommand extends Command
{
    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setAliases(['database:backup'])
            ->addOption('compress', 'c', InputOption::VALUE_NONE, 'Enable (gzip) compression');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io       = $this->getIO();
        $compress = $this->getOption('compress');
        $updater  = new Updater($this->getDB());
        try {
            $file = $updater->createSqlDumpFile($compress);
            $updater->createSqlDump($file, $compress);
            $io->success('SQL-Dump "' . $file . '" created.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
