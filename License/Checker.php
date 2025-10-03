<?php

declare(strict_types=1);

namespace JTL\License;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Events\Dispatcher;
use JTL\License\Struct\ExpiredExsLicense;
use JTL\License\Struct\ExsLicense;
use JTL\Plugin\Admin\StateChanger;
use JTL\Plugin\Helper as PluginHelper;
use JTL\Plugin\PluginLoader;
use JTL\Plugin\State;
use JTL\Shop;
use JTL\Template\BootChecker;
use Psr\Log\LoggerInterface;

/**
 * Class Checker
 * @package JTL\License
 */
readonly class Checker
{
    public function __construct(
        private LoggerInterface $logger,
        private DbInterface $db,
        private JTLCacheInterface $cache
    ) {
    }

    /**
     * @return Collection<int, ExsLicense>
     */
    public function getUpdates(Mapper $mapper): Collection
    {
        return $mapper->getCollection()->getUpdateableItems();
    }

    public function handleExpiredLicenses(Manager $manager): void
    {
        $collection = (new Mapper($manager))->getCollection();
        $this->notifyPlugins($collection);
        $this->notifyTemplates($collection);
        $this->handleExpiredPluginTestLicenses($collection);
    }

    /**
     * @return Collection<int, ExsLicense>
     */
    public function getLicenseViolations(Mapper $mapper): Collection
    {
        $collection = $this->getPluginsWithoutLicense($mapper->getCollection()->getLicenseViolations());
        $tplLicense = $this->getTemplatesWithoutLicense();
        if ($tplLicense !== null && \is_a($tplLicense, ExpiredExsLicense::class)) {
            $collection->add($tplLicense);
        }

        return $collection;
    }

    /**
     * @param Collection<int, ExsLicense> $items
     * @return Collection<int, ExsLicense>
     */
    private function getPluginsWithoutLicense(Collection $items): Collection
    {
        $plugins = $this->db->selectAll('tplugin', ['bExtension', 'nStatus'], [1, 2]);
        $loader  = new PluginLoader($this->db, $this->cache);
        foreach ($plugins as $dataItem) {
            $plugin     = $loader->loadFromObject($dataItem, Shop::getLanguageCode());
            $exsLicense = $plugin->getLicense()->getExsLicense();
            if ($exsLicense !== null && \is_a($exsLicense, ExpiredExsLicense::class)) {
                $items->add($exsLicense);
            }
        }

        return $items;
    }

    /**
     * @return ExsLicense|null
     */
    private function getTemplatesWithoutLicense(): ?ExsLicense
    {
        return Shop::Container()->getTemplateService()->getActiveTemplate()->getExsLicense();
    }

    /**
     * @param Collection<int, ExsLicense> $collection
     */
    private function notifyTemplates(Collection $collection): void
    {
        /** @var ExsLicense $license */
        foreach ($collection->getTemplates()->getDedupedActiveExpired() as $license) {
            $this->logger->info(\sprintf('License for template %s is expired.', $license->getID()));
            $bootstrapper = BootChecker::bootstrap($license->getID());
            $bootstrapper?->licenseExpired($license);
        }
    }

    /**
     * @param Collection<int, ExsLicense> $collection
     */
    private function notifyPlugins(Collection $collection): void
    {
        $dispatcher = Dispatcher::getInstance();
        $loader     = new PluginLoader($this->db, $this->cache);
        /** @var ExsLicense $license */
        foreach ($collection->getPlugins()->getDedupedActiveExpired() as $license) {
            $this->logger->info(\sprintf('License for plugin %s is expired.', $license->getID()));
            $id = $license->getReferencedItem()?->getInternalID() ?? 0;
            if (($p = PluginHelper::bootstrap($id, $loader)) !== null) {
                $p->boot($dispatcher);
                $p->licenseExpired($license);
            }
        }
    }

    /**
     * @param Collection<int, ExsLicense> $collection
     */
    private function handleExpiredPluginTestLicenses(Collection $collection): void
    {
        $expired = $collection->getDedupedExpiredBoundTests()
            ->filter(fn(ExsLicense $e): bool => $e->getType() === ExsLicense::TYPE_PLUGIN);
        if ($expired->count() === 0) {
            return;
        }
        $stateChanger = new StateChanger($this->db, $this->cache);
        /** @var ExsLicense $license */
        foreach ($expired as $license) {
            $ref = $license->getReferencedItem();
            if ($ref === null || $ref->getInternalID() === 0 || $ref->isActive() === false) {
                continue;
            }
            $this->logger->warning('Plugin {id} disabled due to expired test license.', ['id' => $license->getID()]);
            $stateChanger->deactivate($ref->getInternalID(), State::LICENSE_KEY_INVALID);
        }
    }
}
