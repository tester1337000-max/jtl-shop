<?php

declare(strict_types=1);

namespace JTL\Console\Command\Cache;

use JTL\Console\Command\Command;
use JTL\Filesystem\LocalFilesystem;
use JTL\Shop;
use League\Flysystem\FileAttributes;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: 'cache:dbes:delete',
    description: 'Delete dbeS cache',
    hidden: false
)]
class DbesTmpCommand extends Command
{
    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getIO();
        $fs = Shop::Container()->get(LocalFilesystem::class);
        try {
            /** @var FileAttributes $item */
            foreach ($fs->listContents('dbeS/tmp/')->toArray() as $item) {
                if ($item->isDir()) {
                    $fs->deleteDirectory($item->path());
                } else {
                    $fs->delete($item->path());
                }
            }
            $io->success('dbeS tmp cache deleted.');

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $io->warning('Could not delete: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
