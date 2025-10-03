<?php

declare(strict_types=1);

namespace JTL\Console\Command\Migration;

use JTL\Console\Command\Command;
use JTL\Update\MigrationHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'migration:create',
    description: 'Create a new migration',
    hidden: false
)]
class CreateCommand extends Command
{
    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->addArgument('description', InputArgument::REQUIRED, 'Short migration description')
            ->addArgument('author', InputArgument::REQUIRED, 'Author');
    }

    /**
     * @inheritdoc
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $description = \trim($input->getArgument('description') ?? '');
        $author      = \trim($input->getArgument('author') ?? '');
        if (\strlen($description) < 5) {
            $description = $this->getIO()->ask('Short migration description');
            $input->setArgument('description', $description);
        }
        if (\strlen($author) < 2) {
            $author = $this->getIO()->ask('Migration author');
            $input->setArgument('author', $author);
        }
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $description   = \trim($input->getArgument('description') ?? '');
        $author        = \trim($input->getArgument('author') ?? '');
        $migrationPath = MigrationHelper::create($description, $author);

        $output->writeln("<info>Created Migration:</info> <comment>'" . $migrationPath . "'</comment>");

        return Command::SUCCESS;
    }
}
