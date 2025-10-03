<?php

declare(strict_types=1);

namespace JTL\Console\Command\Plugin;

use JTL\Console\Command\Command;
use JTL\Plugin\MigrationHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'plugin:migration:create',
    description: 'Create new plugin migration',
    hidden: false
)]
class CreateMigrationCommand extends Command
{
    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setDefinition(
            new InputDefinition([
                new InputOption('plugin-dir', null, InputOption::VALUE_REQUIRED, 'Plugin dir name'),
                new InputOption('description', null, InputOption::VALUE_REQUIRED, 'Short migration description'),
                new InputOption('author', null, InputOption::VALUE_REQUIRED, 'Author')
            ])
        );
    }

    /**
     * @inheritdoc
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $pluginDir   = \trim($input->getOption('plugin-dir') ?? '');
        $description = \trim($input->getOption('description') ?? '');
        $author      = \trim($input->getOption('author') ?? '');
        while ($pluginDir === null || \strlen($pluginDir) < 3) {
            $pluginDir = $this->getIO()->ask('Plugin dir');
        }
        while ($pluginDir === null || \strlen($description) < 1) {
            $description = $this->getIO()->ask('Description');
        }
        while ($author === null || \strlen($author) < 1) {
            $author = $this->getIO()->ask('Author');
        }
        $input->setOption('plugin-dir', $pluginDir);
        $input->setOption('description', $description);
        $input->setOption('author', $author);
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pluginDir   = \trim($input->getOption('plugin-dir') ?? '');
        $description = \trim($input->getOption('description') ?? '');
        $author      = \trim($input->getOption('author') ?? '');
        try {
            $migrationPath = MigrationHelper::create($pluginDir, $description, $author);
            $output->writeln("<info>Created Migration:</info> <comment>'" . $migrationPath . "'</comment>");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->getIO()->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
