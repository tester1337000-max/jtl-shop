<?php

declare(strict_types=1);

namespace JTL\License;

use JTL\License\Struct\ExsLicense;
use JTL\License\Struct\ReferencedPlugin;
use JTL\License\Struct\ReferencedTemplate;
use stdClass;

/**
 * Class Mapper
 * @package JTL\License
 */
readonly class Mapper
{
    public function __construct(private Manager $manager)
    {
    }

    /**
     * @return Collection<int, ExsLicense>
     */
    public function getCollection(): Collection
    {
        $cacheID = 'mapper_lic_collection';
        /** @var Collection<int, ExsLicense>|false $collection */
        $collection = $this->manager->getCache()->get($cacheID);
        if ($collection !== false) {
            return $collection;
        }
        $collection = new Collection();
        $data       = $this->manager->getLicenseData();
        if ($data === null) {
            return $collection;
        }
        foreach ($data->extensions as $extension) {
            $exsLicense = new ExsLicense($extension);
            $exsLicense->setQueryDate($data->timestamp);
            if ($exsLicense->getState() === ExsLicense::STATE_ACTIVE) {
                $this->setReference($exsLicense, $extension);
                $avail        = $exsLicense->getReleases()->getAvailable();
                $subscription = $exsLicense->getLicense()->getSubscription();
                if ($avail !== null && $subscription->isExpired()) {
                    $releaseDate    = $avail->getReleaseDate();
                    $expirationDate = $subscription->getValidUntil();
                    if ($releaseDate <= $expirationDate) {
                        $subscription->setCanBeUsed(true);
                    }
                }
            }
            $collection->push($exsLicense);
        }
        $this->manager->getCache()->set($cacheID, $collection, [\CACHING_GROUP_LICENSES]);

        return $collection;
    }

    /**
     * @throws \Exception
     */
    private function setReference(ExsLicense $esxLicense, stdClass $license): void
    {
        switch ($esxLicense->getType()) {
            case ExsLicense::TYPE_PLUGIN:
            case ExsLicense::TYPE_PORTLET:
                $plugin = new ReferencedPlugin();
                $plugin->initByExsID($this->manager->getDB(), $license, $esxLicense->getReleases());
                $esxLicense->setReferencedItem($plugin);
                break;
            case ExsLicense::TYPE_TEMPLATE:
                $template = new ReferencedTemplate();
                $template->initByExsID($this->manager->getDB(), $license, $esxLicense->getReleases());
                $esxLicense->setReferencedItem($template);
                break;
        }
    }
}
