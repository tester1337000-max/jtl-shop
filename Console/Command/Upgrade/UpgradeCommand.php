<?php

declare(strict_types=1);

namespace JTL\Console\Command\Upgrade;

use Exception;
use JTL\Backend\Upgrade\Channel;
use JTL\Backend\Upgrade\PluginUpgrader;
use JTL\Backend\Upgrade\Release\Release;
use JTL\Backend\Upgrade\Release\ReleaseCollection;
use JTL\Backend\Upgrade\Release\ReleaseDB;
use JTL\Backend\Upgrade\Step\CreateFSBackup;
use JTL\Backend\Upgrade\Step\DownloadRelease;
use JTL\Backend\Upgrade\Step\Factory;
use JTL\Backend\Upgrade\Step\LockRelease;
use JTL\Backend\Upgrade\Step\StepConfiguration;
use JTL\Backend\Upgrade\Step\StepInterface;
use JTL\Console\Command\Command;
use JTL\Filesystem\Filesystem;
use JTL\Filesystem\LocalFilesystem;
use JTL\License\Manager;
use JTL\License\Struct\ExsLicense;
use JTL\Plugin\InstallCode;
use JTL\Shop;
use League\Flysystem\MountManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Functional\first;
use function Functional\map;

#[AsCommand(
    name: 'upgrader:upgrade',
    description: 'Upgrade base system',
    hidden: false
)]
final class UpgradeCommand extends Command
{
    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->addOption(
            'channel',
            'c',
            InputOption::VALUE_REQUIRED,
            'Select channel (stable, beta, alpha, bleedingedge)',
        )
            ->addOption('release', 'r', InputOption::VALUE_REQUIRED, 'Select release ID')
            ->addOption('filesystembackup', 'f', InputOption::VALUE_OPTIONAL, 'Create file system backup?')
            ->addOption('ignore-plugin-updates', 'i', InputOption::VALUE_NONE, 'Ignore plugin updates')
            ->addOption('install-plugin-updates', 'p', InputOption::VALUE_NONE, 'Install plugin updates')
            ->addOption('databasebackup', 'd', InputOption::VALUE_OPTIONAL, 'Create database backup?');
    }

    /**
     * @inheritdoc
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $channel = \trim($input->getOption('channel') ?? '');
        $valid   = map(Channel::cases(), static fn(Channel $channel) => \strtolower($channel->value));
        while (!\in_array($channel, $valid, true)) {
            $channel = $this->getIO()->choice('Channel', $valid, 'stable');
        }
        $input->setOption('channel', $channel);
        $allowedReleases = [];
        $releases        = new ReleaseCollection(new ReleaseDB($this->db));
        try {
            $availableReleases = $releases->getReleases(Channel::from(\strtoupper($channel)));
        } catch (Exception $e) {
            exit($this->fail($e->getMessage()));
        }
        if (\count($availableReleases) === 0) {
            exit($this->fail('Currently no releases available in this channel.'));
        }
        /** @var Release $release */
        foreach ($availableReleases as $release) {
            $this->getIO()->write((string)$release->version);
            $this->getIO()->newLine();
            $allowedReleases[] = (string)$release->version;
        }
        $release = \trim($input->getOption('release') ?? '');
        while (!\in_array($release, $allowedReleases, true)) {
            $release = $this->getIO()->choice('Release', $allowedReleases);
        }
        $input->setOption('release', $release);

        $dbBackup = \trim($input->getOption('databasebackup') ?? '');
        if ($dbBackup === '') {
            $dbBackup = $this->getIO()->confirm('Create database backup?', true, '/^(y|j)/i');
        }
        $input->setOption('databasebackup', $dbBackup);

        $fsBackup = \trim($input->getOption('filesystembackup') ?? '');
        if ($fsBackup === '') {
            $fsBackup = $this->getIO()->confirm('Create file system backup?', true, '/^(y|j)/i');
        }
        $input->setOption('filesystembackup', $fsBackup);
    }

    private function checkCreateFSBackup(InputInterface $input): bool
    {
        $fsBackup = $input->getOption('filesystembackup');
        if (\is_string($fsBackup)) {
            $fsBackup = (bool)\preg_match('/^[y|j]/i', $fsBackup);
        }

        return $fsBackup;
    }

    private function checkCreateDBBackup(InputInterface $input): bool
    {
        $dbBackup = $input->getOption('databasebackup');
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
        $collection    = new ReleaseCollection(new ReleaseDB($this->db));
        $factory       = new Factory($configuration, $this->db, $this->cache, $manager, $collection);
        $manager       = new Manager($this->db, $this->cache);
        try {
            $factory->initWithReleaseString((string)$input->getOption('release'));
        } catch (Exception $e) {
            return $this->fail($e->getMessage());
        }
        if ($this->checkPluginUpdates($input, new PluginUpgrader($this->db, $this->cache, $manager)) === 'quit') {
            return Command::SUCCESS;
        }
        try {
            $this->executeSteps(
                $factory->getSteps($this->checkCreateFSBackup($input), $this->checkCreateDBBackup($input)),
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
        $this->getIO()->success(\sprintf('Successfully upgraded to version %s', $configuration->targetVersion));

        return Command::SUCCESS;
    }

    private function checkPluginUpdates(InputInterface $input, PluginUpgrader $upgrader): string
    {
        $pluginUpdates = $upgrader->getPluginUpdates();
        if ($pluginUpdates->count() === 0) {
            return 'skip';
        }
        $action = 'skip';
        $io     = $this->getIO();
        $io->info('Plugin updates available:');
        /** @var ExsLicense $pluginUpdate */
        foreach ($pluginUpdates as $pluginUpdate) {
            $io->writeln(' * ' . $pluginUpdate->getName());
        }
        if ($input->getOption('install-plugin-updates') === true) {
            $action = 'update';
        } elseif ($input->getOption('ignore-plugin-updates') === false) {
            $action = $io->choice('Skip, quit or update plugins?', ['skip', 'quit', 'update'], 'skip');
        }
        if ($action === 'update') {
            $result = $upgrader->updatePlugins(
                $pluginUpdates->map(fn(ExsLicense $lic): ?string => $lic->getReferencedItem()?->getID())->toArray()
            );
            $this->printPluginUpdateTable($result);
        }

        return $action;
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

    /**
     * @param array<string, int> $result
     */
    private function printPluginUpdateTable(array $result): void
    {
        $rows = [];
        foreach ($result as $item => $status) {
            $rows[] = [
                $item,
                $status === InstallCode::OK
                    ? '<info> ✔ </info>'
                    : '<error> ⚠ </error>'
            ];
        }
        $this->getIO()->table(['Plugin', 'Status'], $rows);
    }
}
