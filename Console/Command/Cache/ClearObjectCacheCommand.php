<?php

declare(strict_types=1);

namespace JTL\Console\Command\Cache;

use JTL\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'cache:clear',
    description: 'Clear object cache',
    hidden: false
)]
class ClearObjectCacheCommand extends Command
{
    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getIO();
        if ($this->getCache()->flushAll()) {
            $io->success('Object cache cleared.');

            return Command::SUCCESS;
        }
        $io->warning('Could not clear object cache.');

        return Command::FAILURE;
    }
}
