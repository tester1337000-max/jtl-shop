<?php

declare(strict_types=1);

namespace JTL\Services;

use JTL\Backend\AdminAccount;
use JTL\Boxes\FactoryInterface;
use JTL\Cache\JTLCacheInterface;
use JTL\Consent\ManagerInterface;
use JTL\DB\DbInterface;
use JTL\DB\Services\GcServiceInterface;
use JTL\Debug\JTLDebugBar;
use JTL\FreeGift\Services\FreeGiftService;
use JTL\L10n\GetText;
use JTL\Mail\Mailer;
use JTL\Mapper\PluginState;
use JTL\Network\JTLApi;
use JTL\OPC\DB;
use JTL\OPC\Locker;
use JTL\OPC\PageDB;
use JTL\OPC\PageService;
use JTL\OPC\Service;
use JTL\Services\JTL\AlertServiceInterface;
use JTL\Services\JTL\BoxServiceInterface;
use JTL\Services\JTL\CaptchaServiceInterface;
use JTL\Services\JTL\CountryServiceInterface;
use JTL\Services\JTL\CryptoServiceInterface;
use JTL\Services\JTL\LinkServiceInterface;
use JTL\Services\JTL\PasswordServiceInterface;
use JTL\Shipping\Services\ShippingService;
use JTL\Template\TemplateServiceInterface;
use Psr\Log\LoggerInterface;

/**
 * Class Container
 *
 * This class provides default services, that are provided by JTL-Shop core. Those Services are provided though a
 * separate interface for improving IntelliSense support for external and internal developers
 *
 * @package JTL\Services
 */
class Container extends ContainerBase implements DefaultServicesInterface
{
    /**
     * @inheritdoc
     */
    public function getDB(): DbInterface
    {
        return $this->get(DbInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function getPasswordService(): PasswordServiceInterface
    {
        return $this->get(PasswordServiceInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function getCryptoService(): CryptoServiceInterface
    {
        return $this->get(CryptoServiceInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function getBackendLogService(): LoggerInterface
    {
        return $this->get('BackendAuthLogger');
    }

    /**
     * @inheritdoc
     */
    public function getLogService(): LoggerInterface
    {
        return $this->get(LoggerInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function getDBServiceGC(): GcServiceInterface
    {
        return $this->get(GcServiceInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function getCache(): JTLCacheInterface
    {
        return $this->get(JTLCacheInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function getLinkService(): LinkServiceInterface
    {
        return $this->get(LinkServiceInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function getBoxFactory(): FactoryInterface
    {
        return $this->get(FactoryInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function getBoxService(): BoxServiceInterface
    {
        return $this->get(BoxServiceInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function getCaptchaService(): CaptchaServiceInterface
    {
        return $this->get(CaptchaServiceInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function getOPC(): Service
    {
        return $this->get(Service::class);
    }

    /**
     * @inheritdoc
     */
    public function getOPCPageService(): PageService
    {
        return $this->get(PageService::class);
    }

    /**
     * @inheritdoc
     */
    public function getOPCDB(): DB
    {
        return $this->get(DB::class);
    }

    /**
     * @inheritdoc
     */
    public function getOPCPageDB(): PageDB
    {
        return $this->get(PageDB::class);
    }

    /**
     * @inheritdoc
     */
    public function getOPCLocker(): Locker
    {
        return $this->get(Locker::class);
    }

    /**
     * @inheritdoc
     */
    public function getAlertService(): AlertServiceInterface
    {
        return $this->get(AlertServiceInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function getConsentManager(): ManagerInterface
    {
        return $this->get(ManagerInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function getGetText(): GetText
    {
        return $this->get(GetText::class);
    }

    /**
     * @inheritdoc
     */
    public function getAdminAccount(): AdminAccount
    {
        return $this->get(AdminAccount::class);
    }

    /**
     * @inheritdoc
     */
    public function getDebugBar(): JTLDebugBar
    {
        return $this->get(JTLDebugBar::class);
    }

    /**
     * @inheritdoc
     */
    public function getCountryService(): CountryServiceInterface
    {
        return $this->get(CountryServiceInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function getTemplateService(): TemplateServiceInterface
    {
        return $this->get(TemplateServiceInterface::class);
    }

    public function getMailer(): Mailer
    {
        return $this->get(Mailer::class);
    }

    public function getJTLAPI(): JTLApi
    {
        return $this->get(JTLApi::class);
    }

    public function getFreeGiftService(): FreeGiftService
    {
        return $this->get(FreeGiftService::class);
    }

    public function getShippingService(): ShippingService
    {
        return $this->get(ShippingService::class);
    }

    public function getPluginState(): PluginState
    {
        return $this->get(PluginState::class);
    }
}
