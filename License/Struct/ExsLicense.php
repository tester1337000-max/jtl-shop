<?php

declare(strict_types=1);

namespace JTL\License\Struct;

use DateTime;
use stdClass;

/**
 * Class ExsLicense
 * @package JTL\License
 */
class ExsLicense
{
    public const TYPE_PLUGIN = 'plugin';

    public const TYPE_TEMPLATE = 'template';

    public const TYPE_PORTLET = 'portlet';

    public const STATE_ACTIVE = 1;

    public const STATE_UNBOUND = 0;

    private string $id;

    private string $type;

    private string $name;

    private string $exsid;

    private Vendor $vendor;

    private License $license;

    private Releases $releases;

    /**
     * @var Link[]
     */
    private array $links;

    private DateTime $queryDate;

    /**
     * @var self::STATE_*
     */
    private int $state = self::STATE_UNBOUND;

    private ?ReferencedItemInterface $referencedItem = null;

    private InAppParent $parent;

    private bool $isInApp = false;

    private bool $hasSubscription = false;

    private bool $hasLicense = false;

    private bool $canBeUsed = true;

    public function __construct(?stdClass $json = null)
    {
        if ($json !== null) {
            $this->fromJSON($json);
        }
    }

    public function fromJSON(stdClass $json): void
    {
        $this->setID($json->id);
        $this->setType($json->type ?? self::TYPE_PLUGIN);
        $this->setName($json->name);
        $this->setExsID($json->exsid);
        if (isset($json->license)) {
            $this->setLicense(new License($json->license));
            $this->setHasLicense($this->getLicense()->getValidUntil() !== null);
            $this->setHasSubscription($this->getLicense()->getSubscription()->getValidUntil() !== null);
        }
        $this->setVendor(new Vendor($json->vendor));
        if (\is_array($json->releases)) {
            $json->releases = null; // the api sends an empty array instead of an object when there are none...
        }
        $this->releases = new Releases($json->releases);
        foreach ($json->links as $link) {
            $this->links[] = new Link($link);
        }
        if (isset($json->license->metas->in_app)) {
            $this->setParent(new InAppParent($json->license->metas->in_app));
            $this->setIsInApp(true);
        } else {
            $this->setParent(new InAppParent());
        }
        $this->check();
    }

    private function check(): void
    {
        $license             = $this->getLicense();
        $licenseExpired      = $license->isExpired();
        $subscriptionExpired = $license->getSubscription()->isExpired();
        if ($licenseExpired || $subscriptionExpired) {
            if ($license->getType() === License::TYPE_TEST) {
                $this->canBeUsed = false;
                return;
            }
            $release = $this->getReleases()->getAvailable();
            if ($release === null) {
                $this->canBeUsed = false;
                return;
            }
            if ($licenseExpired) {
                $this->canBeUsed = $license->getValidUntil() >= $release->getReleaseDate();
            } elseif ($subscriptionExpired) {
                $this->canBeUsed = $license->getSubscription()->getValidUntil() >= $release->getReleaseDate();
            }
        }
    }

    public function getID(): string
    {
        return $this->id;
    }

    public function setID(string $id): void
    {
        $this->id = $id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getExsID(): string
    {
        return $this->exsid;
    }

    public function setExsID(string $exsid): void
    {
        $this->exsid = $exsid;
    }

    public function getVendor(): Vendor
    {
        return $this->vendor;
    }

    public function setVendor(Vendor $vendor): void
    {
        $this->vendor = $vendor;
    }

    public function getLicense(): License
    {
        return $this->license;
    }

    public function setLicense(License $license): void
    {
        $this->license = $license;
        if ($license->isBound()) {
            $this->setState(self::STATE_ACTIVE);
        }
    }

    public function getReleases(): Releases
    {
        return $this->releases;
    }

    public function setReleases(Releases $releases): void
    {
        $this->releases = $releases;
    }

    /**
     * @return Link[]
     */
    public function getLinks(): array
    {
        return $this->links;
    }

    /**
     * @param Link[] $links
     */
    public function setLinks(array $links): void
    {
        $this->links = $links;
    }

    public function getQueryDate(): DateTime
    {
        return $this->queryDate;
    }

    public function setQueryDate(DateTime|string $queryDate): void
    {
        $this->queryDate = \is_string($queryDate) ? new DateTime($queryDate) : $queryDate;
    }

    public function getState(): int
    {
        return $this->state;
    }

    /**
     * @param self::STATE_* $state
     */
    public function setState(int $state): void
    {
        $this->state = $state;
    }

    public function getReferencedItem(): ?ReferencedItemInterface
    {
        return $this->referencedItem;
    }

    public function setReferencedItem(?ReferencedItemInterface $referencedItem): void
    {
        $this->referencedItem = $referencedItem;
        if (
            $referencedItem !== null
            && $this->canBeUsed === true
            && ($this->getLicense()->isExpired() || $this->getLicense()->getSubscription()->isExpired())
        ) {
            $avail = $this->getReleases()->getAvailable();
            $inst  = $referencedItem->getInstalledVersion();
            if ($avail !== null && $inst !== null && $inst->greaterThan($avail->getVersion())) {
                $this->canBeUsed = false;
            }
        }
    }

    public function getParent(): InAppParent
    {
        return $this->parent;
    }

    public function setParent(InAppParent $parent): void
    {
        $this->parent = $parent;
    }

    public function isInApp(): bool
    {
        return $this->isInApp;
    }

    public function setIsInApp(bool $isInApp): void
    {
        $this->isInApp = $isInApp;
    }

    public function hasSubscription(): bool
    {
        return $this->hasSubscription;
    }

    public function setHasSubscription(bool $hasSubscription): void
    {
        $this->hasSubscription = $hasSubscription;
    }

    public function hasLicense(): bool
    {
        return $this->hasLicense;
    }

    public function setHasLicense(bool $hasLicense): void
    {
        $this->hasLicense = $hasLicense;
    }

    public function canBeUsed(): bool
    {
        return $this->canBeUsed;
    }

    public function setCanBeUsed(bool $canBeUsed): void
    {
        $this->canBeUsed = $canBeUsed;
    }
}
