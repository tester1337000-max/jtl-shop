<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade;

use Exception;
use Illuminate\Support\Collection;
use JTL\Backend\Upgrade\Release\Release;
use JTL\Backend\Upgrade\Release\ReleaseCollection;
use JTL\Backend\Upgrade\Release\ReleaseDB;
use JTL\Backend\Upgrade\Step\BackupSpecialFiles;
use JTL\Backend\Upgrade\Step\Factory;
use JTL\Backend\Upgrade\Step\LockRelease;
use JTL\Backend\Upgrade\Step\RollbackFactory;
use JTL\Backend\Upgrade\Step\StepConfiguration;
use JTL\Backend\Upgrade\Step\StepInterface;
use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Filesystem\Filesystem;
use JTL\Filesystem\LocalFilesystem;
use JTL\IO\IOError;
use JTL\Session\Backend;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use JTLShop\SemVer\Version;
use League\Flysystem\MountManager;
use stdClass;

use function Functional\first;

final readonly class UpgradeIO
{
    public function __construct(private JTLSmarty $smarty, private DbInterface $db, private JTLCacheInterface $cache)
    {
        Shop::Container()->getGetText()->loadAdminLocale('pages/upgrade');
    }

    /**
     * @return array<string, string|Release[]>
     */
    public function updateChannelIO(string $channel): array
    {
        Channels::updateActiveChannel(Channel::from(\strtoupper($channel)));

        return $this->render();
    }

    public function restoreFile(string $file): stdClass|IOError
    {
        $step = new BackupSpecialFiles(
            new StepConfiguration(),
            $this->db,
            $this->cache,
            new MountManager(['root' => Shop::Container()->get(Filesystem::class)])
        );
        try {
            $step->restore($file);
            $this->assignDiffData();

            return (object)['diff' => $this->smarty->fetch('tpl_inc/upgrade_diff.tpl')];
        } catch (Exception $exception) {
            return $this->fail($exception->getMessage(), new StepConfiguration());
        }
    }

    /**
     * @param array<string, mixed>|null $progressData
     */
    public function doUpgrade(
        int $step,
        int $requestedID,
        ?array $progressData = null,
        bool $createFSBackup = true,
        bool $createDBBackup = true,
        bool $updatePlugins = true
    ): stdClass|IOError {
        Backend::set('isUpdating', true);
        $progress = new StepConfiguration();
        if ($progressData !== null) {
            $progress->fromArray($progressData);
        }
        $manager = new MountManager([
            'root'    => Shop::Container()->get(LocalFilesystem::class),
            'upgrade' => Shop::Container()->get(Filesystem::class)
        ]);
        $factory = new Factory(
            $progress,
            $this->db,
            $this->cache,
            $manager,
            new ReleaseCollection(new ReleaseDB($this->db))
        );
        $factory->initWithReleaseID($requestedID);
        $steps       = $factory->getSteps($createFSBackup, $createDBBackup, $updatePlugins);
        $currentStep = $steps[$step];
        try {
            $progress = $currentStep->run();
            $time     = $currentStep->getTiming();
        } catch (Exception $e) {
            /** @var LockRelease|null $lockRelease */
            $lockRelease = first(\array_filter($steps, static fn(StepInterface $s): bool => $s instanceof LockRelease));
            $lockRelease?->run();

            return $this->fail($e->getMessage(), $progress);
        }
        $this->smarty->assign('diffData');
        if (isset($steps[$step + 1])) {
            $nextTitle = $steps[$step + 1]->getTitle();
        } else {
            Backend::getInstance()->reHash((string)$progress->targetVersion);
            $nextTitle = \sprintf(\__('Successfully upgraded to version %s.'), (string)$progress->targetVersion);
            $this->assignDiffData();
        }
        $this->smarty->assign('logs', $progress->logger->getInfo())
            ->assign('errors', $progress->logger->getErrors())
            ->assign('warnings', $progress->logger->getWarnings())
            ->assign('debug', $progress->logger->getDebug())
            ->assign('appVersion', \APPLICATION_VERSION)
            ->assign('dbVersion', Shop::getShopDatabaseVersion());

        return (object)[
            'finished'    => $progress->finished,
            'version '    => \APPLICATION_VERSION,
            'passed'      => true,
            'tests'       => [],
            'title'       => \__('Upgrade'),
            'nextTitle'   => $nextTitle,
            'step'        => $step,
            'nextStep'    => $step + 1,
            'requestedID' => $requestedID,
            'progress'    => $progress,
            'time'        => $time,
            'maxSteps'    => \count($steps),
            'diff'        => $this->smarty->fetch('tpl_inc/upgrade_diff.tpl'),
            'log'         => $this->smarty->fetch('tpl_inc/upgrade_log.tpl'),
        ];
    }

    private function assignDiffData(): void
    {
        $data = [];
        foreach (BackupSpecialFiles::SPECIAL_FILES as $file) {
            $original = \PFAD_ROOT . $file . '.bak';
            $new      = \PFAD_ROOT . $file;
            if (\sha1_file($original) === \sha1_file($new)) {
                continue;
            }
            $data[] = [
                'file'     => $file,
                'original' => \file_get_contents($original),
                'new'      => \file_get_contents($new)
            ];
        }
        $this->smarty->assign('diffData', $data);
    }

    /**
     * @param array<string, mixed>|null $progressData
     */
    public function doRollback(int $step, int $requestedID, ?array $progressData = null): stdClass|IOError
    {
        Backend::set('isUpdating', true);
        $progress = new StepConfiguration();
        if ($progressData !== null) {
            $progress->fromArray($progressData);
        }
        $manager = new MountManager([
            'root'    => Shop::Container()->get(LocalFilesystem::class),
            'upgrade' => Shop::Container()->get(Filesystem::class)
        ]);
        $factory = new RollbackFactory(
            $progress,
            $this->db,
            $this->cache,
            $manager
        );
        $factory->initWithID($requestedID);
        $steps = $factory->getSteps();
        try {
            $currentStep = $steps[$step] ?? throw new \RuntimeException(\__('Step not found.'));
            $progress    = $currentStep->run();
            $time        = $currentStep->getTiming();
        } catch (Exception $e) {
            /** @var LockRelease|null $lockRelease */
            $lockRelease = first(\array_filter($steps, static fn(StepInterface $s): bool => $s instanceof LockRelease));
            $lockRelease?->run();

            return $this->fail($e->getMessage(), $progress);
        }
        if (isset($steps[$step + 1])) {
            $nextTitle = $steps[$step + 1]->getTitle();
        } else {
            Backend::getInstance()->reHash((string)$progress->targetVersion);
            $nextTitle = \sprintf(\__('Successfully rolled back to version %s.'), (string)$progress->targetVersion);
        }
        $this->smarty->assign('logs', $progress->logger->getInfo())
            ->assign('errors', $progress->logger->getErrors())
            ->assign('warnings', $progress->logger->getWarnings())
            ->assign('debug', $progress->logger->getDebug())
            ->assign('appVersion', \APPLICATION_VERSION)
            ->assign('dbVersion', Shop::getShopDatabaseVersion());

        return (object)[
            'finished'    => $progress->finished,
            'version '    => \APPLICATION_VERSION,
            'passed'      => true,
            'tests'       => [],
            'title'       => \__('roll back'),
            'nextTitle'   => $nextTitle,
            'step'        => $step,
            'nextStep'    => $step + 1,
            'requestedID' => $requestedID,
            'progress'    => $progress,
            'time'        => $time,
            'maxSteps'    => \count($steps),
            'log'         => $this->smarty->fetch('tpl_inc/upgrade_log.tpl'),
        ];
    }

    public function fail(string $message, StepConfiguration $progress): IOError
    {
        $progress->logger->error($message);
        $this->smarty->assign('logs', $progress->logger->getInfo())
            ->assign('errors', $progress->logger->getErrors())
            ->assign('warnings', $progress->logger->getWarnings())
            ->assign('debug', $progress->logger->getDebug());

        return new IOError($message, 500, [], $this->smarty->fetch('tpl_inc/upgrade_log.tpl'));
    }

    /**
     * @return array<string, string|Release[]>
     */
    public function render(): array
    {
        $activeChannel = Channels::getActiveChannel($this->db);
        $release       = new ReleaseCollection(new ReleaseDB($this->db));
        try {
            $filtered = $release->getReleases($activeChannel);
        } catch (Exception) {
            $filtered = new Collection();
        }
        $this->smarty->assign('channels', Channels::getAvailableChannels())
            ->assign('activeChannel', $activeChannel)
            ->assign('availableVersions', $filtered)
            ->assign('changelogURL', 'https://changelog.jtl-software.de/systems/jtl/shop/')
            ->assign('currentVersion', Version::parse(\APPLICATION_VERSION));

        return [
            'channels' => $this->smarty->fetch('tpl_inc/upgrade_channels.tpl'),
            'upgrades' => $this->smarty->fetch('tpl_inc/upgrade_upgrades.tpl'),
            'filtered' => \json_encode($filtered->all(), \JSON_THROW_ON_ERROR)
        ];
    }
}
