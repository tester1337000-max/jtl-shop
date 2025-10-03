<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Step;

use JTL\Template\Admin\Listing;
use JTL\Template\Admin\ListingItem;
use JTL\Template\Admin\Validation\TemplateValidator;
use JTLShop\SemVer\Version;

final class CheckTemplateMaxShopVersion extends AbstractStep
{
    public function getTitle(): string
    {
        return \__('Checking plugin versions...');
    }

    public function run(mixed $data = null): StepConfiguration
    {
        $this->startTiming();
        $cb = \is_callable($data) ? $data : [$this, 'getActiveTemplate'];
        /** @var ListingItem|null $item */
        $item = $cb();
        if ($item === null) {
            throw new StepFailedException(\__('No active template found.'));
        }
        if ($item->getName() !== 'NOVA' && $this->isOldTemplate($item)) {
            throw new StepFailedException(
                \sprintf(
                    \__('The current template %s is not compatible with the target version: %s.'),
                    $item->getName(),
                    $item->getMaxShopVersion()
                )
            );
        }
        $this->progress->addInfo(\__('Template compatability ok.'));
        $this->stopTiming();

        return $this->progress;
    }

    private function getActiveTemplate(): ?ListingItem
    {
        return (new Listing($this->db, new TemplateValidator($this->db)))->getAll()
            ->first(static fn(ListingItem $item): bool => $item->isActive() === true);
    }

    private function isOldTemplate(ListingItem $item): bool
    {
        $version0      = Version::parse('0.0.0');
        $targetVersion = $this->progress->targetVersion ?? $version0;

        return $item->getMaxShopVersion()->greaterThan($version0)
            && $item->getMaxShopVersion()->smallerThan($targetVersion);
    }
}
