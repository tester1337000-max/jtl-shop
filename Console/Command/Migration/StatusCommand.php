<?php

declare(strict_types=1);

namespace JTL\Console\Command\Migration;

use JTL\Console\Command\Command;
use JTL\Update\MigrationManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'migration:status',
    description: 'Show the status of each migration',
    hidden: false
)]
class StatusCommand extends Command
{
    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $list               = [];
        $manager            = new MigrationManager($this->db);
        $executedMigrations = $manager->getExecutedMigrations();
        foreach ($manager->getMigrations() as $key => $migration) {
            $list[] = (object)[
                'id'          => $migration->getId(),
                'name'        => $migration->getName(),
                'author'      => $migration->getAuthor(),
                'description' => $migration->getDescription(),
                'executed'    => \in_array($key, $executedMigrations, true)
            ];
        }
        $this->printMigrationTable($list);

        return Command::SUCCESS;
    }

    /**
     * @param \stdClass[] $list
     */
    protected function printMigrationTable(array $list): void
    {
        if (\count($list) === 0) {
            $this->getIO()->note('No migration found.');

            return;
        }
        $rows    = [];
        $headers = ['Migration', 'Description', 'Author', ''];
        foreach ($list as $item) {
            $rows[] = [
                $item->id,
                $item->description,
                $item->author,
                $item->executed ? '<info> ✔ </info>' : '<comment> • </comment>'
            ];
        }
        $this->getIO()->writeln('');
        $this->getIO()->table($headers, $rows);
    }
}
