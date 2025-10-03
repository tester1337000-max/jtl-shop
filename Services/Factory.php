<?php

declare(strict_types=1);

namespace JTL\Services;

use JTL\Backend\AdminAccount;
use JTL\Backend\AdminLoginConfig;
use JTL\Boxes\Factory as BoxFactory;
use JTL\Boxes\FactoryInterface as BoxFactoryInterface;
use JTL\Boxes\Renderer\DefaultRenderer;
use JTL\Cache\JTLCache;
use JTL\Cache\JTLCacheInterface;
use JTL\Consent\Manager;
use JTL\Consent\ManagerInterface;
use JTL\Consent\Statistics\Repositories\ConsentStatisticsRepository;
use JTL\Consent\Statistics\Services\ConsentStatisticsService;
use JTL\DB\DbInterface;
use JTL\DB\NiceDB;
use JTL\DB\Services\GcService;
use JTL\DB\Services\GcServiceInterface;
use JTL\Debug\JTLDebugBar;
use JTL\Filesystem\AdapterFactory;
use JTL\Filesystem\Filesystem;
use JTL\Filesystem\LocalFilesystem;
use JTL\FreeGift\Services\FreeGiftService;
use JTL\L10n\GetText;
use JTL\Mail\Hydrator\DefaultsHydrator;
use JTL\Mail\Mailer;
use JTL\Mail\Renderer\SmartyRenderer;
use JTL\Mail\Validator\MailValidator;
use JTL\Mapper\AdminLoginStatusMessageMapper;
use JTL\Mapper\AdminLoginStatusToLogLevel;
use JTL\Mapper\PluginState;
use JTL\Network\JTLApi;
use JTL\Nice;
use JTL\OPC\DB;
use JTL\OPC\Locker;
use JTL\OPC\PageDB;
use JTL\OPC\PageService;
use JTL\OPC\Service as OPCService;
use JTL\ProcessingHandler\NiceDBHandler;
use JTL\Services\JTL\AlertService;
use JTL\Services\JTL\AlertServiceInterface;
use JTL\Services\JTL\BoxService;
use JTL\Services\JTL\BoxServiceInterface;
use JTL\Services\JTL\CaptchaService;
use JTL\Services\JTL\CaptchaServiceInterface;
use JTL\Services\JTL\CountryService;
use JTL\Services\JTL\CountryServiceInterface;
use JTL\Services\JTL\CryptoService;
use JTL\Services\JTL\CryptoServiceInterface;
use JTL\Services\JTL\LinkService;
use JTL\Services\JTL\LinkServiceInterface;
use JTL\Services\JTL\PasswordService;
use JTL\Services\JTL\PasswordServiceInterface;
use JTL\Services\JTL\SimpleCaptchaService;
use JTL\Session\Frontend;
use JTL\Settings\Option\Globals;
use JTL\Settings\Settings;
use JTL\Shipping\Services\ShippingService;
use JTL\Shop;
use JTL\Shopsetting;
use JTL\Smarty\MailSmarty;
use JTL\Template\TemplateService;
use JTL\Template\TemplateServiceInterface;
use League\Flysystem\Config as FlysystemConfig;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\Visibility;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;

/**
 * Class Factory
 * @package JTL\Services
 */
class Factory
{
    public function createContainers(): DefaultServicesInterface
    {
        $container = new Container();
        $container->singleton(DbInterface::class, fn(): NiceDB => new NiceDB(\DB_HOST, \DB_USER, \DB_PASS, \DB_NAME));
        $container->singleton(JTLCacheInterface::class, JTLCache::class);
        $container->singleton(LinkServiceInterface::class, LinkService::class);
        $container->singleton(AlertServiceInterface::class, AlertService::class);
        $container->singleton(CryptoServiceInterface::class, CryptoService::class);
        $container->singleton(PasswordServiceInterface::class, PasswordService::class);
        $container->singleton(CountryServiceInterface::class, CountryService::class);
        $container->singleton(JTLDebugBar::class, static function (Container $container): JTLDebugBar {
            return new JTLDebugBar(
                $container->getDB()->getPDO(),
                Shopsetting::getInstance($container->getDB(), $container->getCache())->getAll()
            );
        });
        $container->singleton('BackendAuthLogger', static function (Container $container): LoggerInterface {
            $loggingConf = Shop::getSettingValue(\CONF_GLOBAL, 'admin_login_logger_mode');
            $handlers    = [];
            foreach ($loggingConf as $value) {
                if ($value === AdminLoginConfig::CONFIG_DB) {
                    $handlers[] = (new NiceDBHandler($container->getDB(), Level::Info->value))
                        ->setFormatter(new LineFormatter('%message%', null, true, true));
                } elseif ($value === AdminLoginConfig::CONFIG_FILE) {
                    $handlers[] = (new StreamHandler(\PFAD_LOGFILES . 'auth.log', Level::Info->value))
                        ->setFormatter(new LineFormatter(null, null, true, true));
                }
            }

            return new Logger('auth', $handlers, [new PsrLogMessageProcessor()]);
        });
        $container->singleton(LoggerInterface::class, static function (Container $container): LoggerInterface {
            $level = Settings::intValue(Globals::SYSLOG_LEVEL);
            if (!\in_array($level, Level::VALUES, true)) {
                $level = Level::Error->value;
            }
            $handler = (new NiceDBHandler($container->getDB(), $level))
                ->setFormatter(new LineFormatter('%message%', null, true, true));

            return new Logger('jtllog', [$handler], [new PsrLogMessageProcessor()]);
        });
        $container->alias(LoggerInterface::class, 'Logger');
        $container->bind(JTLApi::class, static function (Container $container) {
            return new JTLApi(Nice::getInstance($container->getDB(), $container->getCache()), $container->getCache());
        });
        $container->singleton(GcServiceInterface::class, GcService::class);
        $container->singleton(GetText::class);
        $container->singleton(OPCService::class);
        $container->singleton(PageService::class);
        $container->singleton(DB::class);
        $container->singleton(PageDB::class);
        $container->singleton(Locker::class);
        $container->bind(BoxFactoryInterface::class, static function (Container $container): BoxFactory {
            return new BoxFactory(Shopsetting::getInstance($container->getDB(), $container->getCache())->getAll());
        });
        $container->singleton(BoxServiceInterface::class, static function (Container $container): BoxService {
            $smarty = Shop::Smarty();

            return new BoxService(
                Shopsetting::getInstance($container->getDB(), $container->getCache())->getAll(),
                $container->getBoxFactory(),
                $container->getDB(),
                $container->getCache(),
                $smarty,
                new DefaultRenderer($smarty)
            );
        });
        $container->singleton(CaptchaServiceInterface::class, static function (): CaptchaService {
            return new CaptchaService(
                new SimpleCaptchaService(
                    !(Frontend::get('bAnti_spam_already_checked', false) || Frontend::getCustomer()->isLoggedIn())
                )
            );
        });
        $container->singleton(AdminAccount::class, static function (Container $container): AdminAccount {
            return new AdminAccount(
                $container->getDB(),
                $container->getBackendLogService(),
                new AdminLoginStatusMessageMapper(),
                new AdminLoginStatusToLogLevel(),
                $container->getGetText(),
                $container->getAlertService()
            );
        });
        $container->singleton(Filesystem::class, static function (): Filesystem {
            $factory = new AdapterFactory(Shop::getSettingSection(\CONF_FS));

            return new Filesystem(
                $factory->getAdapter(),
                [FlysystemConfig::OPTION_DIRECTORY_VISIBILITY => Visibility::PUBLIC]
            );
        });
        $container->singleton(LocalFilesystem::class, static function (): Filesystem {
            return new Filesystem(
                new LocalFilesystemAdapter(\PFAD_ROOT),
                [FlysystemConfig::OPTION_DIRECTORY_VISIBILITY => Visibility::PUBLIC]
            );
        });
        $container->bind(Mailer::class, static function (Container $container): Mailer {
            $db        = $container->getDB();
            $settings  = Shopsetting::getInstance($db, $container->getCache());
            $smarty    = new SmartyRenderer(new MailSmarty($db));
            $hydrator  = new DefaultsHydrator($smarty->getSmarty(), $db, $settings);
            $validator = new MailValidator($db, $settings->getAll());

            return new Mailer($hydrator, $smarty, $settings, $validator);
        });
        $container->singleton(ManagerInterface::class, static function (Container $container): Manager {
            $service = new ConsentStatisticsService(new ConsentStatisticsRepository($container->getDB()));

            return new Manager($container->getDB(), $container->getCache(), $service);
        });
        $container->singleton(
            TemplateServiceInterface::class,
            static function (Container $container): TemplateService {
                return new TemplateService($container->getDB(), $container->getCache());
            }
        );
        $container->singleton(PluginState::class);

        $container->bind(FreeGiftService::class);

        $container->singleton(ShippingService::class, fn(): ShippingService => new ShippingService());

        return $container;
    }
}
