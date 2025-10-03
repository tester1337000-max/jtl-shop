<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Step;

use Illuminate\Support\Collection;
use JTL\Plugin\Admin\Listing;
use JTL\Plugin\Admin\ListingItem;
use JTL\Plugin\Admin\Validation\LegacyPluginValidator;
use JTL\Plugin\Admin\Validation\PluginValidator;
use JTL\XMLParser;
use JTLShop\SemVer\Version;

final class CheckPluginMaxShopVersion extends AbstractStep
{
    public function getTitle(): string
    {
        return \__('Checking plugin versions...');
    }

    public function run(mixed $data = null): StepConfiguration
    {
        $this->startTiming();
        $cb = \is_callable($data) ? $data : [$this, 'getPluginCollection'];
        /** @var Collection<int, ListingItem> $collection */
        $collection = $cb();
        $filtered   = $this->getOldPlugins($collection);
        if ($filtered->count() > 0) {
            throw new StepFailedException(
                \sprintf(
                    \__('The following plugins are not compatible with the target version: %s.'),
                    $filtered->map(
                        fn(ListingItem $itm): string => \sprintf('%s (%s)', $itm->getName(), $itm->getMaxShopVersion())
                    )->implode(', ')
                )
            );
        }
        $this->progress->addInfo(\__('No incompatible plugins found.'));
        $this->stopTiming();

        return $this->progress;
    }

    /**
     * @return Collection<int, ListingItem>
     */
    private function getPluginCollection(): Collection
    {
        $listing = new Listing(
            $this->db,
            $this->cache,
            new LegacyPluginValidator($this->db, new XMLParser()),
            new PluginValidator($this->db, new XMLParser())
        );

        return $listing->getEnabled();
    }

    /**
     * @param Collection<int, ListingItem> $listing
     * @return Collection<int, ListingItem>
     */
    private function getOldPlugins(Collection $listing): Collection
    {
        $version0      = Version::parse('0.0.0');
        $targetVersion = $this->progress->targetVersion ?? $version0;

        return $listing->filter(
            fn(ListingItem $item): bool => $item->getMaxShopVersion()->greaterThan($version0)
                && $item->getMaxShopVersion()->smallerThan($targetVersion)
        );
    }
}
