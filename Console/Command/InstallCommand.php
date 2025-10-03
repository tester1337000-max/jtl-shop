<?php

declare(strict_types=1);

namespace JTL\Console\Command;

use Exception;
use JTL\Installation\VueInstaller;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Systemcheck\Tests\AbstractTest;

#[AsCommand(
    name: 'shop:install',
    description: 'Install shop',
    aliases: ['install'],
    hidden: false
)]
class InstallCommand extends Command
{
    protected int $steps = 6;

    protected int $currentStep = 1;

    protected string $currentUser = '';

    /**
     * @var string[]
     */
    protected static array $writeablePaths = [
        'admin/includes/emailpdfs',
        'admin/templates_c',
        'bilder/brandingbilder',
        'bilder/hersteller/klein',
        'bilder/hersteller/normal',
        'bilder/intern/shoplogo',
        'bilder/intern/trustedshops',
        'bilder/kategorien',
        'bilder/links',
        'bilder/merkmale/klein',
        'bilder/merkmale/normal',
        'bilder/merkmalwerte/klein',
        'bilder/merkmalwerte/normal',
        'bilder/news',
        'bilder/newsletter',
        'bilder/produkte/mini',
        'bilder/produkte/klein',
        'bilder/produkte/normal',
        'bilder/produkte/gross',
        'bilder/suchspecialoverlay/klein',
        'bilder/suchspecialoverlay/normal',
        'bilder/suchspecialoverlay/gross',
        'bilder/variationen/mini',
        'bilder/variationen/normal',
        'bilder/variationen/gross',
        'bilder/suchspecialoverlay/klein',
        'bilder/suchspecialoverlay/normal',
        'bilder/suchspecialoverlay/gross',
        'bilder/konfigurator/klein',
        'dbeS/logs',
        'dbeS/tmp',
        'export',
        'export/yatego',
        'includes/config.JTL-Shop.ini.php',
        'install/logs',
        'jtllogs',
        'media/',
        'media/image/product',
        'media/image/storage',
        'mediafiles/Bilder',
        'mediafiles/Musik',
        'mediafiles/Sonstiges',
        'mediafiles/Videos',
        'rss.xml',
        'shopinfo.xml',
        'templates_c',
        'uploads',
    ];

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->steps       = 6;
        $this->currentStep = 1;
        $this->currentUser = \trim(\getenv('USER') ?: \get_current_user());

        $this->addOption('shop-url', null, InputOption::VALUE_REQUIRED, 'Shop url')
            ->addOption('database-host', null, InputOption::VALUE_OPTIONAL, 'Database host')
            ->addOption('database-socket', null, InputOption::VALUE_OPTIONAL, 'Database socket')
            ->addOption('database-name', null, InputOption::VALUE_REQUIRED, 'Database name')
            ->addOption('database-user', null, InputOption::VALUE_REQUIRED, 'Database user')
            ->addOption('database-password', null, InputOption::VALUE_REQUIRED, 'Database password')
            ->addOption('admin-user', null, InputOption::VALUE_REQUIRED, 'Shop-Backend user', 'admin')
            ->addOption('admin-password', null, InputOption::VALUE_REQUIRED, 'Shop-Backend password', 'random')
            ->addOption('sync-user', null, InputOption::VALUE_REQUIRED, 'Wawi-Sync user', 'sync')
            ->addOption('sync-password', null, InputOption::VALUE_REQUIRED, 'Wawi-Sync password', 'random')
            ->addOption('install-demo-data', null, InputOption::VALUE_NONE, 'Install demo data?')
            ->addOption(
                'file-owner',
                null,
                InputOption::VALUE_REQUIRED,
                'Set file owner, needs root permissions',
                \sprintf('%s', $this->currentUser)
            )
            ->addOption(
                'file-group',
                null,
                InputOption::VALUE_REQUIRED,
                'Set file group, needs root permissions',
                \sprintf('%s', $this->currentUser)
            );
    }

    /**
     * @inheritdoc
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $io              = $this->getIO();
        $requiredOptions = [
            'shop-url',
            'database-host',
            'database-name',
            'database-user',
            'database-password',
            'admin-user',
            'admin-password',
            'sync-user',
            'sync-password',
        ];
        foreach ($requiredOptions as $option) {
            $value = $this->getOption($option);
            if ($value === null) {
                $def   = $this->getOptionDefinition($option);
                $value = $io->ask($def->getDescription(), $def->getDefault());
                $input->setOption($option, $value);
            }
        }
    }

    private function getRandomString(): string
    {
        return \bin2hex(\random_bytes(10));
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io              = $this->getIO();
        $uri             = $this->getOption('shop-url');
        $fileOwner       = $this->getOption('file-owner');
        $fileGroup       = $this->getOption('file-group');
        $dbHost          = $this->getOption('database-host');
        $dbSocket        = $this->getOption('database-socket');
        $dbName          = $this->getOption('database-name');
        $dbUser          = $this->getOption('database-user');
        $dbPass          = $this->getOption('database-password');
        $adminUser       = $this->getOption('admin-user');
        $adminPass       = $this->getOption('admin-password');
        $syncUser        = $this->getOption('sync-user');
        $syncPass        = $this->getOption('sync-password');
        $demoData        = $this->getOption('install-demo-data');
        $localFilesystem = new Filesystem(
            new LocalFilesystemAdapter(\PFAD_ROOT, null, \LOCK_EX, LocalFilesystemAdapter::SKIP_LINKS)
        );
        if ($adminPass === 'random') {
            $adminPass = $this->getRandomString();
        }
        if ($syncPass === 'random') {
            $syncPass = $this->getRandomString();
        }

        if ($uri !== null) {
            if ($scheme = \parse_url($uri, \PHP_URL_SCHEME)) {
                if (!\in_array($scheme, ['http', 'https'], true)) {
                    throw new Exception(\sprintf('Invalid Shop URL: "%s"', $uri));
                }
            } else {
                throw new Exception(\sprintf('Invalid Shop URL: "%s"', $uri));
            }
        }
        $parsedUri = \parse_url($uri);
        if ($parsedUri === false || !isset($parsedUri['scheme'], $parsedUri['host'])) {
            throw new Exception('Invalid host');
        }
        $uri = $parsedUri['scheme'] . '://' . $parsedUri['host']
            . (empty($parsedUri['port']) ? '' : ':' . $parsedUri['port'])
            . (empty($parsedUri['path']) ? '/' : $parsedUri['path']);
        \defined('URL_SHOP') || \define('URL_SHOP', \rtrim($uri, '/'));

        if (empty($dbHost) && empty($dbSocket)) {
            throw new Exception('Invalid database host or socket');
        }

        $io->setStep($this->currentStep++, $this->steps, 'Check if shop is installed');
        $installCheck = (new VueInstaller('installedcheck', [], true))->run();

        if ($installCheck['installed'] ?? false) {
            $io->warning('Shop is already installed');
            return Command::FAILURE;
        }
        $io->success('Shop can be installed');

        $io->setStep($this->currentStep++, $this->steps, 'DB credential check');
        $dbCredentials      = [
            'host'   => $dbHost,
            'socket' => $dbSocket,
            'name'   => $dbName,
            'user'   => $dbUser,
            'pass'   => $dbPass
        ];
        $dbCredentialsCheck = (new VueInstaller('credentialscheck', $dbCredentials, true))->run();

        if ($dbCredentialsCheck['error'] ?? false) {
            $io->error($dbCredentialsCheck['msg']);

            return Command::FAILURE;
        }
        $io->success('Credentials matched');

        $io->setStep($this->currentStep++, $this->steps, 'System check');
        $systemCheckResults = (new VueInstaller('systemcheck', ['db' => $dbCredentials], true))->run();
        $systemCheckFailed  = false;

        foreach ($systemCheckResults['testresults'] ?? [] as $resultGroup) {
            /** @var AbstractTest $test */
            foreach ($resultGroup as $test) {
                $result = $test->getResult();
                if ($result !== AbstractTest::RESULT_OK && $test->getIsOptional() === false) {
                    $systemCheckFailed = true;
                }
            }
        }
        if (isset($systemCheckResults['testresults'])) {
            $this->printSystemCheckTable(
                $systemCheckResults['testresults']['recommendations'] ?? [],
                $systemCheckResults['testresults']['requirements'] ?? []
            );
        }
        if ($systemCheckFailed) {
            $io->error('Failed');

            return Command::FAILURE;
        }
        $io->success('All requirements are met');

        $io->setStep($this->currentStep++, $this->steps, 'Setting permissions');
        if ($this->currentUser !== $fileOwner) {
            foreach ($localFilesystem->listContents(\PFAD_ROOT, true) as $item) {
                $path = $item->path();
                \chown(\PFAD_ROOT . $path, $fileOwner);
                \chgrp(\PFAD_ROOT . $path, $fileGroup);
            }
            \chown(\PFAD_ROOT, $fileOwner);
        }
        foreach (self::$writeablePaths as $path) {
            \chmod($path, 0777);
        }

        $io->success('Permissions updated');

        $dirCheck = (new VueInstaller('dircheck', [], true))->run();

        if (\in_array(false, $dirCheck['testresults'] ?? [], true)) {
            $this->printDirCheckTable($dirCheck['testresults'] ?? [], $localFilesystem);
            $io->error('File permissions are incorrect.');

            return Command::FAILURE;
        }

        $io->setStep($this->currentStep++, $this->steps, 'JTL-Shop install');

        $posts = [
            'db'    => $dbCredentials,
            'admin' => ['name' => $adminUser, 'pass' => $adminPass],
            'wawi'  => ['name' => $syncUser, 'pass' => $syncPass],
        ];

        $installed = (new VueInstaller('doinstall', $posts, true))->run();
        if ($installed['error'] ?? false) {
            $io->error(\implode(' | ', $installed['msg']));

            return Command::FAILURE;
        }
        $io->success('Successful installed');
        if ($demoData === true) {
            $ok = (new VueInstaller('installdemodata', $posts, true))->run();
            if (($ok['error'] ?? true) === false) {
                $io->success('Successfully added demo data');
            } else {
                $io->error('Could not add demo data');
            }
        }

        $io->writeln('  <info>Admin-Login</info>');
        $io->writeln('    Username <comment>' . $adminUser . '</comment>');
        $io->writeln('    Password <comment>' . $adminPass . '</comment>');
        $io->writeln('');
        $io->writeln('  <info>Sync-Login</info>');
        $io->writeln('    Username <comment>' . $syncUser . '</comment>');
        $io->writeln('    Password <comment>' . $syncPass . '</comment>');

        $io->setStep($this->currentStep++, $this->steps, 'Remove install dir and set new permissions for config file');

        if ($localFilesystem->fileExists('/install/install.php')) {
            $localFilesystem->deleteDirectory('/install');
        }
        if ($localFilesystem->fileExists('/includes/config.JTL-Shop.ini.php')) {
            \chmod('/includes/config.JTL-Shop.ini.php', 0644);
            \chown('/includes/config.JTL-Shop.ini.php', $fileOwner);
            \chgrp('/includes/config.JTL-Shop.ini.php', $fileGroup);
        }
        $io->success('Installation completed.');

        return Command::SUCCESS;
    }

    /**
     * @param AbstractTest[] $recommendations
     * @param AbstractTest[] $requirements
     */
    protected function printSystemCheckTable(array $recommendations, array $requirements = []): void
    {
        $rowsRecommended = [];
        $rowsRequired    = [];
        $headers         = ['Name', 'Requirement', 'Actual Value'];
        foreach ($recommendations as $recommendation) {
            $rowsRecommended[] = [
                $recommendation->getName(),
                $recommendation->getRequiredState(),
                $recommendation->getResult() === 0
                    ? '<info> ✔ </info>'
                    : '<comment> ' . $recommendation->getCurrentState() . ' </comment>'
            ];
        }
        foreach ($requirements as $requirement) {
            $rowsRequired[] = [
                $requirement->getName(),
                $requirement->getRequiredState(),
                $requirement->getResult() === 0
                    ? '<info> ✔ </info>'
                    : '<comment> ' . $requirement->getCurrentState() . ' </comment>'
            ];
        }
        $tableStyle = new TableStyle();
        $tableStyle->setPadType(\STR_PAD_BOTH);
        if (\count($rowsRequired) > 0) {
            $this->getIO()->writeln('Required');
            $this->getIO()->table($headers, $rowsRequired, ['style' => $tableStyle]);
        }
        if (\count($rowsRecommended) > 0) {
            $this->getIO()->writeln('Recommended');
            $this->getIO()->table($headers, $rowsRecommended, ['style' => $tableStyle]);
        }
    }

    /**
     * @param array<string, bool|int> $list
     */
    protected function printDirCheckTable(array $list, Filesystem $localFilesystem): void
    {
        $rows    = [];
        $headers = ['File/Dir', 'Correct permission', 'Permission'];
        foreach ($list as $path => $val) {
            $permission = $localFilesystem->visibility($path);
            $rows[]     = [$path, $val ? '<info> ✔ </info>' : '<comment> • </comment>', $permission];
        }
        $tableStyle = new TableStyle();
        $tableStyle->setPadType(\STR_PAD_BOTH);
        $this->getIO()->writeln('');
        $this->getIO()->table($headers, $rows, ['style' => $tableStyle]);
    }
}
