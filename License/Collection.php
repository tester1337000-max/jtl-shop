<?php

declare(strict_types=1);

namespace JTL\License;

use JTL\License\Struct\ExsLicense;
use JTL\License\Struct\License;

/**
 * Class Collection
 * @package JTL\License
 * @template TKey of array-key
 * @template TValue
 * @extends \Illuminate\Support\Collection<TKey, TValue>
 */
class Collection extends \Illuminate\Support\Collection
{
    /**
     * @return Collection<int, ExsLicense>
     */
    public function getActive(): self
    {
        return $this->getBound();
    }

    /**
     * @return Collection<int, ExsLicense>
     */
    public function getBound(): self
    {
        return $this->filter(fn(ExsLicense $e): bool => $e->getState() === ExsLicense::STATE_ACTIVE);
    }

    /**
     * @return Collection<int, ExsLicense>
     */
    public function getUnbound(): self
    {
        return $this->filter(fn(ExsLicense $e): bool => $e->getState() === ExsLicense::STATE_UNBOUND);
    }

    public function getForItemID(string $itemID): ?ExsLicense
    {
        $matches = $this->getBound()->filter(fn(ExsLicense $e): bool => $e->getID() === $itemID)
            ->sort(fn(ExsLicense $e): int => $e->getLicense()->getType() === License::TYPE_PROD ? -1 : 1);
        if ($matches->count() > 1) {
            foreach ($matches as $exs) {
                $license = $exs->getLicense();
                if ($license->isExpired() === false && $license->getSubscription()->isExpired() === false) {
                    return $exs;
                }
            }
        }

        return $matches->first();
    }

    public function getForExsID(string $exsID): ?ExsLicense
    {
        $matches = $this->getBound()->filter(fn(ExsLicense $e): bool => $e->getExsID() === $exsID)
            ->sort(fn(ExsLicense $e): int => $e->getLicense()->getType() === License::TYPE_PROD ? -1 : 1);
        if ($matches->count() > 1) {
            // when there are multiple bound exs licenses, try to choose one that isn't expired yet
            /** @var ExsLicense $exs */
            foreach ($matches as $exs) {
                $license = $exs->getLicense();
                if ($license->isExpired() === false && $license->getSubscription()->isExpired() === false) {
                    return $exs;
                }
            }
        }

        return $matches->first();
    }

    public function getForLicenseKey(string $licenseKey): ?ExsLicense
    {
        return $this->first(fn(ExsLicense $e): bool => $e->getLicense()->getKey() === $licenseKey);
    }

    /**
     * @return Collection<int, ExsLicense>
     */
    public function getActiveExpired(): self
    {
        return $this->getBoundExpired()->filter(static function (ExsLicense $e): bool {
            $ref = $e->getReferencedItem();

            return $ref !== null && $ref->isActive();
        });
    }

    /**
     * @return Collection<int, ExsLicense>
     */
    public function getDedupedActiveExpired(): self
    {
        return $this->getActiveExpired()->filter(fn(ExsLicense $e): bool => $e === $this->getForExsID($e->getExsID()));
    }

    /**
     * @return Collection<int, ExsLicense>
     */
    public function getBoundExpired(): self
    {
        return $this->getBound()->filter(static function (ExsLicense $e): bool {
            $ref = $e->getReferencedItem();

            return $ref !== null
                && ($e->getLicense()->isExpired() || $e->getLicense()->getSubscription()->isExpired());
        });
    }

    /**
     * @return Collection<int, ExsLicense>
     */
    public function getLicenseViolations(): self
    {
        return $this->getDedupedActiveExpired()->filter(fn(ExsLicense $e): bool => !$e->canBeUsed());
    }

    /**
     * @return Collection<int, ExsLicense>
     */
    public function getExpiredActiveTests(): self
    {
        return $this->getExpiredBoundTests();
    }

    /**
     * @return Collection<int, ExsLicense>
     */
    public function getExpiredBoundTests(): self
    {
        return $this->getBoundExpired()
            ->filter(fn(ExsLicense $e): bool => $e->getLicense()->getType() === License::TYPE_TEST);
    }

    /**
     * @return Collection<int, ExsLicense>
     */
    public function getDedupedExpiredBoundTests(): self
    {
        return $this->getExpiredBoundTests()
            ->filter(fn(ExsLicense $e): bool => $e === $this->getForExsID($e->getExsID()));
    }

    /**
     * @return Collection<int, ExsLicense>
     */
    public function getPlugins(): self
    {
        return $this->filter(static function (ExsLicense $e): bool {
            return $e->getType() === ExsLicense::TYPE_PLUGIN || $e->getType() === ExsLicense::TYPE_PORTLET;
        });
    }

    /**
     * @return Collection<int, ExsLicense>
     */
    public function getTemplates(): self
    {
        return $this->filter(static fn(ExsLicense $e): bool => $e->getType() === ExsLicense::TYPE_TEMPLATE);
    }

    /**
     * @return Collection<int, ExsLicense>
     */
    public function getPortlets(): self
    {
        return $this->filter(fn(ExsLicense $e): bool => $e->getType() === ExsLicense::TYPE_PORTLET);
    }

    /**
     * @return Collection<int, ExsLicense>
     */
    public function getInstalled(): self
    {
        return $this->getBound()->filter(fn(ExsLicense $e): bool => $e->getReferencedItem() !== null);
    }

    /**
     * @return Collection<int, ExsLicense>
     */
    public function getUpdateableItems(): self
    {
        return $this->getBound()->getInstalled()->filter(static function (ExsLicense $e): bool {
            return $e->getReferencedItem()?->hasUpdate() === true;
        });
    }

    /**
     * @return Collection<int, ExsLicense>
     */
    public function getExpired(): self
    {
        return $this->getBound()->filter(static function (ExsLicense $e): bool {
            return $e->getLicense()->isExpired() || $e->getLicense()->getSubscription()->isExpired();
        });
    }

    /**
     * @return Collection<int, ExsLicense>
     */
    public function getAboutToBeExpired(int $days = 28): self
    {
        return $this->getBound()->filter(static function (ExsLicense $e) use ($days): bool {
            $license = $e->getLicense();

            return (!$license->isExpired()
                    && $license->getDaysRemaining() > 0
                    && $license->getDaysRemaining() < $days)
                || (!$license->getSubscription()->isExpired()
                    && $license->getSubscription()->getDaysRemaining() > 0
                    && $license->getSubscription()->getDaysRemaining() < $days
                );
        });
    }
}
