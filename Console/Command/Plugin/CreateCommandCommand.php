<?php

declare(strict_types=1);

namespace JTL\Console\Command\Plugin;

use DateTime;
use Exception;
use JTL\Console\Command\Command;
use JTL\Filesystem\LocalFilesystem;
use JTL\Plugin\Helper;
use JTL\Shop;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: 'plugin:command:create',
    description: 'Create new plugin command',
    hidden: false
)]
class CreateCommandCommand extends Command
{
    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->addArgument('plugin-id', InputArgument::REQUIRED, 'Plugin id')
            ->addArgument('command-name', InputArgument::REQUIRED, 'Command name, like \'CronCommand\'')
            ->addArgument('author', InputArgument::REQUIRED, 'Author');
    }

    /**
     * @inheritdoc
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $pluginID = \trim($input->getArgument('plugin-id') ?? '');
        $command  = \trim($input->getArgument('command-name') ?? '');
        $author   = \trim($input->getArgument('author') ?? '');
        while ($pluginID === null || \strlen($pluginID) < 3) {
            $pluginID = $this->getIO()->ask('PluginID');
        }
        $input->setArgument('plugin-id', $pluginID);
        if (\strlen($command) < 2) {
            $command = $this->getIO()->ask('Command name');
            $input->setArgument('command-name', $command);
        }
        if (\strlen($author) < 2) {
            $author = $this->getIO()->ask('Author');
            $input->setArgument('author', $author);
        }
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pluginID    = \trim($input->getArgument('plugin-id') ?? '');
        $commandName = \trim($input->getArgument('command-name') ?? '');
        $author      = \trim($input->getArgument('author') ?? '');
        try {
            $commandPath = $this->createFile($pluginID, $commandName, $author);
            $output->writeln("<info>Created command:</info> <comment>'" . $commandPath . "'</comment>");

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->getIO()->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * @throws \SmartyException
     * @throws Exception
     */
    protected function createFile(string $pluginID, string $commandName, string $author): string
    {
        if (empty(Helper::getIDByPluginID($pluginID))) {
            throw new Exception('There is no plugin for the given dir name.');
        }

        $datetime      = new DateTime('NOW');
        $relPath       = \PLUGIN_DIR . $pluginID . '/Commands';
        $migrationPath = $relPath . '/' . $commandName . '.php';
        $fileSystem    = Shop::Container()->get(LocalFilesystem::class);
        try {
            $fileSystem->createDirectory($relPath);
        } catch (Throwable) {
            throw new Exception('Cannot create dir ' . $relPath);
        }
        $content = Shop::Smarty()->assign('commandName', $commandName)
            ->assign('author', $author)
            ->assign('created', $datetime->format(DateTime::RSS))
            ->assign('pluginId', $pluginID)
            ->fetch(\PFAD_ROOT . 'includes/src/Console/Command/Plugin/Template/command.class.tpl');

        $fileSystem->write($migrationPath, $content);

        return $migrationPath;
    }
}
