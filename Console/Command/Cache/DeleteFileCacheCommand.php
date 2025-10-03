<?php

declare(strict_types=1);

namespace JTL\Console\Command\Cache;

use JTL\Console\Command\Command;
use JTL\Filesystem\LocalFilesystem;
use JTL\Shop;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: 'cache:file:delete',
    description: 'Delete file cache',
    aliases: ['cache:files:delete'],
    hidden: false
)]
class DeleteFileCacheCommand extends Command
{
    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getIO();
        $fs = Shop::Container()->get(LocalFilesystem::class);
        try {
            $fs->deleteDirectory('/templates_c/filecache/');
            $io->success('File cache deleted.');

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $io->warning('Could not delete: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
