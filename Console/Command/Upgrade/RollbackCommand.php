<?php

declare(strict_types=1);

namespace JTL\Console\Command\Upgrade;

use Exception;
use Illuminate\Support\Collection;
use JTL\Backend\Upgrade\Step\CreateFSBackup;
use JTL\Backend\Upgrade\Step\DownloadRelease;
use JTL\Backend\Upgrade\Step\LockRelease;
use JTL\Backend\Upgrade\Step\RollbackFactory;
use JTL\Backend\Upgrade\Step\StepConfiguration;
use JTL\Backend\Upgrade\Step\StepInterface;
use JTL\Console\Command\Command;
use JTL\Filesystem\Filesystem;
use JTL\Filesystem\LocalFilesystem;
use JTL\Shop;
use League\Flysystem\MountManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Functional\first;

#[AsCommand(
    name: 'upgrader:rollback',
    description: 'Restore previously created backup and rollback to previous version',
    hidden: false
)]
final class RollbackCommand extends Command
{
    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->addOption('id', 'i', InputOption::VALUE_REQUIRED, 'Select backup ID')
            ->addOption('skip-filesystem-backup', 'f', InputOption::VALUE_OPTIONAL, 'Skip filesystem backup')
            ->addOption('skip-database-backup', 'd', InputOption::VALUE_OPTIONAL, 'Skip database backup');
    }

    /**
     * @inheritdoc
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $available        = $this->getAvailableRollbacks();
        $allowedRollbacks = $available->map(static fn(\stdClass $item): int => (int)$item->id)->toArray();
        if ($available->count() === 0) {
            exit($this->fail('No backups available.'));
        }
        $id = (int)\trim($input->getOption('id') ?? '0');

        foreach ($available as $rollback) {
            $this->getIO()->write('[' . $rollback->id . '] ');
            $this->getIO()->write($rollback->version_from . ' -> ' . $rollback->version_to);
            $this->getIO()->newLine();
        }


        while (!\in_array($id, $allowedRollbacks, true)) {
            $id = $this->getIO()->choice('ID', $allowedRollbacks);
        }
        $input->setOption('id', $id);
        $dbBackup = \trim($input->getOption('skip-database-backup') ?? '');
        if ($dbBackup === '') {
            $dbBackup = $this->getIO()->confirm('Skip restore of database backup?', false, '/^(y|j)/i');
        }
        $input->setOption('skip-database-backup', $dbBackup);

        $fsBackup = \trim($input->getOption('skip-filesystem-backup') ?? '');
        if ($fsBackup === '') {
            $fsBackup = $this->getIO()->confirm('Skip file system backup?', false, '/^(y|j)/i');
        }
        $input->setOption('skip-filesystem-backup', $fsBackup);
    }

    /**
     * @return Collection<int, \stdClass>
     */
    private function getAvailableRollbacks(): Collection
    {
        return $this->db->getCollection('SELECT * from upgrade_log');
    }

    private function checkSkipFSBackup(InputInterface $input): bool
    {
        $fsBackup = $input->getOption('skip-filesystem-backup');
        if (\is_string($fsBackup)) {
            $fsBackup = (bool)\preg_match('/^[y|j]/i', $fsBackup);
        }

        return $fsBackup;
    }

    private function checkSkipDBBackup(InputInterface $input): bool
    {
        $dbBackup = $input->getOption('skip-database-backup');
        if (\is_string($dbBackup)) {
            $dbBackup = (bool)\preg_match('/^[y|j]/i', $dbBackup);
        }

        return $dbBackup;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fs            = Shop::Container()->get(Filesystem::class);
        $manager       = new MountManager([
            'root'    => Shop::Container()->get(LocalFilesystem::class),
            'upgrade' => $fs
        ]);
        $configuration = new StepConfiguration();
        $factory       = new RollbackFactory($configuration, $this->db, $this->cache, $manager);
        try {
            $factory->initWithID((int)$input->getOption('id'));
        } catch (Exception $e) {
            return $this->fail($e->getMessage());
        }
        try {
            $this->executeSteps(
                $factory->getSteps($this->checkSkipDBBackup($input), $this->checkSkipFSBackup($input)),
                $configuration
            );
        } catch (Exception $e) {
            /** @var LockRelease|null $lockRelease */
            $lockRelease = first(
                \array_filter($factory->getSteps(), static fn(StepInterface $s): bool => $s instanceof LockRelease)
            );
            $lockRelease?->run();

            return $this->fail($e->getMessage(), $configuration);
        }
        $this->getIO()->success(\sprintf('Successfully rolled back to version %s', $configuration->targetVersion));

        return Command::SUCCESS;
    }

    /**
     * @param StepInterface[] $steps
     * @throws Exception
     */
    private function executeSteps(array $steps, StepConfiguration $configuration): void
    {
        $io = $this->getIO();
        foreach ($steps as $step) {
            $configuration->logger->reset();
            // @TODO!!!!!!!!!!!!!!!!!
            if (\get_class($step) === CreateFSBackup::class) {
                $io->progress(
                    function ($mycb) use ($step, &$archive): void {
                        $config  = $step->getConfiguration();
                        $archive = $config->fsBackupFile;
                        $step->run($mycb);
                    },
                    'Creating backup archive [%bar%] %percent:3s%%'
                );
            } elseif (\get_class($step) === DownloadRelease::class) {
                $io->progress(
                    static function ($mycb) use ($step): void {
                        $cb = static function ($bytesTotal, $bytesDownloaded) use (&$mycb): void {
                            $mbTotal      = \number_format($bytesTotal / 1024 / 1024, 2);
                            $mbDownloaded = \number_format($bytesDownloaded / 1024 / 1024, 2);
                            if ($bytesTotal > 0) {
                                $mycb($bytesTotal, $bytesDownloaded, $mbDownloaded . 'MiB/' . $mbTotal . 'MiB');
                            }
                        };

                        $step->run($cb);
                    },
                    '%percent:3s%% [%bar%] 100%' . "\n%message%"
                );
            } else {
                $io->writeln($step->getTitle());
                $configuration->logger->reset();
                $step->run();
            }
            $this->logStep($configuration);
            $io->writeln(\sprintf('Time %ss', $step->getTiming()));
        }
    }

    private function logStep(StepConfiguration $configuration): void
    {
        $io = $this->getIO();
        $io->newLine();
        if ($io->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            foreach ($configuration->logger->getDebug() as $line) {
                $io->writeln($line);
            }
        }
        foreach ($configuration->logger->getInfo() as $message) {
            $io->info($message);
        }
    }

    private function fail(string $message, ?StepConfiguration $configuration = null): int
    {
        $io = $this->getIO();
        $io->error($message);
        foreach ($configuration?->logger->getErrors() ?? [] as $error) {
            $io->error($error);
        }

        return Command::FAILURE;
    }
}
