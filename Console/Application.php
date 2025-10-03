<?php

declare(strict_types=1);

namespace JTL\Console;

use Illuminate\Support\Collection;
use JTL\Cache\JTLCacheInterface;
use JTL\Console\Command\Backup\DatabaseCommand;
use JTL\Console\Command\Backup\FilesCommand;
use JTL\Console\Command\Cache\ClearObjectCacheCommand;
use JTL\Console\Command\Cache\CreateImagesCommand;
use JTL\Console\Command\Cache\DbesTmpCommand;
use JTL\Console\Command\Cache\DeleteFileCacheCommand;
use JTL\Console\Command\Cache\DeleteTemplateCacheCommand;
use JTL\Console\Command\Cache\WarmCacheCommand;
use JTL\Console\Command\Command;
use JTL\Console\Command\Compile\LESSCommand;
use JTL\Console\Command\Compile\SASSCommand;
use JTL\Console\Command\DataObjects\CreateDTO as CreateDTOCommand;
use JTL\Console\Command\Generator\GenerateDemoDataCommand;
use JTL\Console\Command\InstallCommand;
use JTL\Console\Command\Mailtemplates\ResetCommand;
use JTL\Console\Command\Migration\CreateCommand;
use JTL\Console\Command\Migration\InnodbUtf8Command;
use JTL\Console\Command\Migration\MigrateCommand;
use JTL\Console\Command\Migration\StatusCommand;
use JTL\Console\Command\Model\CreateCommand as CreateModelCommand;
use JTL\Console\Command\Plugin\CreateCommandCommand;
use JTL\Console\Command\Plugin\CreateMigrationCommand;
use JTL\Console\Command\Plugin\ValidateCommand;
use JTL\Console\Command\Upgrade\RollbackCommand;
use JTL\Console\Command\Upgrade\UpgradeCommand;
use JTL\DB\DbInterface;
use JTL\Plugin\Admin\Listing;
use JTL\Plugin\Admin\ListingItem;
use JTL\Plugin\Admin\Validation\LegacyPluginValidator;
use JTL\Plugin\Admin\Validation\PluginValidator;
use JTL\XMLParser;
use JTLShop\SemVer\Version;
use RuntimeException;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class Application
 * @package JTL\Console
 */
class Application extends BaseApplication
{
    protected ?ConsoleIO $io = null;

    protected bool $devMode = false;

    protected bool $isInstalled = false;

    public function __construct(private readonly ?DbInterface $db, private readonly ?JTLCacheInterface $cache)
    {
        $this->devMode     = \APPLICATION_BUILD_SHA === '#DEV#';
        $this->isInstalled = \defined('BLOWFISH_KEY');
        parent::__construct('JTL-Shop', \APPLICATION_VERSION . ' - ' . ($this->devMode ? 'develop' : 'production'));
    }

    public function initPluginCommands(): void
    {
        if (!$this->isInstalled || \SAFE_MODE === true || $this->db === null || $this->cache === null) {
            return;
        }
        $version = $this->db->select('tversion', [], []);
        if (Version::parse($version->nVersion ?? '400')->smallerThan(Version::parse('500'))) {
            return;
        }
        $parser          = new XMLParser();
        $validator       = new LegacyPluginValidator($this->db, $parser);
        $modernValidator = new PluginValidator($this->db, $parser);
        $listing         = new Listing($this->db, $this->cache, $validator, $modernValidator);
        $this->addPluginCommand($listing->getAll()->filter(fn(ListingItem $i): bool => $i->isShop5Compatible()));
    }

    /**
     * @inheritdoc
     */
    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new ConsoleIO($input, $output, $this->getHelperSet());

        return parent::doRun($input, $output);
    }

    public function getIO(): ConsoleIO
    {
        return $this->io ?? throw new RuntimeException('IO not set');
    }

    /**
     * @param Collection<int, ListingItem> $compatible
     */
    private function addPluginCommand(Collection $compatible): void
    {
        if ($this->cache === null || $this->db === null) {
            return;
        }
        foreach ($compatible as $plugin) {
            if (!\is_dir($plugin->getPath() . 'Commands')) {
                continue;
            }
            $finder = Finder::create()
                ->ignoreVCS(false)
                ->ignoreDotFiles(false)
                ->in($plugin->getPath() . 'Commands');

            /** @var SplFileInfo $file */
            foreach ($finder->files() as $file) {
                /** @var class-string<Command> $class */
                $class = \sprintf(
                    'Plugin\\%s\\Commands\\%s',
                    $plugin->getDir(),
                    \str_replace('.' . $file->getExtension(), '', $file->getBasename())
                );
                if (!\class_exists($class)) {
                    throw new RuntimeException('Class "' . $class . '" does not exist');
                }
                $command = new $class();
                $command->setName($plugin->getPluginID() . ':' . $command->getName());
                if (\is_a($command, Command::class)) {
                    $command->setDB($this->db);
                    $command->setCache($this->cache);
                }
                $this->add($command);
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultCommands(): array
    {
        $cmds = parent::getDefaultCommands();

        if ($this->isInstalled) {
            $cmds[] = new MigrateCommand();
            $cmds[] = new StatusCommand();
            $cmds[] = new InnodbUtf8Command();
            $cmds[] = new DatabaseCommand();
            $cmds[] = new FilesCommand();
            $cmds[] = new DeleteTemplateCacheCommand();
            $cmds[] = new DeleteFileCacheCommand();
            $cmds[] = new DbesTmpCommand();
            $cmds[] = new ClearObjectCacheCommand();
            $cmds[] = new WarmCacheCommand();
            $cmds[] = new CreateImagesCommand();
            $cmds[] = new CreateModelCommand();
            $cmds[] = new LESSCommand();
            $cmds[] = new SASSCommand();
            $cmds[] = new ResetCommand();
            $cmds[] = new GenerateDemoDataCommand();
            $cmds[] = new CreateDTOCommand();
            if (\SHOW_UPGRADER === true) {
                $cmds[] = new UpgradeCommand();
                $cmds[] = new RollbackCommand();
            }
            if ($this->devMode) {
                $cmds[] = new CreateCommand();
            }
            if (\PLUGIN_DEV_MODE === true) {
                $cmds[] = new CreateMigrationCommand();
                $cmds[] = new CreateCommandCommand();
                $cmds[] = new ValidateCommand();
            }
        } else {
            $cmds[] = new InstallCommand();
        }
        \array_map(function ($command): void {
            if (!\is_a($command, Command::class)) {
                return;
            }
            if ($this->db !== null) {
                $command->setDB($this->db);
            }
            if ($this->cache !== null) {
                $command->setCache($this->cache);
            }
        }, $cmds);

        return $cmds;
    }

    /**
     * @return array<string, OutputFormatterStyle>
     */
    protected function createAdditionalStyles(): array
    {
        return [
            'plain'           => new OutputFormatterStyle(),
            'highlight'       => new OutputFormatterStyle('red'),
            'warning'         => new OutputFormatterStyle('black', 'yellow'),
            'verbose'         => new OutputFormatterStyle('white', 'magenta'),
            'info_inverse'    => new OutputFormatterStyle('white', 'blue'),
            'comment_inverse' => new OutputFormatterStyle('black', 'yellow'),
            'success_inverse' => new OutputFormatterStyle('black', 'green'),
            'white_invert'    => new OutputFormatterStyle('black', 'white'),
        ];
    }
}
