<?php

declare(strict_types=1);

namespace JTL\License\Struct;

use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use stdClass;

/**
 * Class Subscription
 * @package JTL\License
 */
class Subscription
{
    private ?DateTime $validUntil = null;

    private bool $expired = false;

    private bool $canBeUsed = true;

    public function __construct(?stdClass $json = null)
    {
        if ($json !== null) {
            $this->fromJSON($json);
        }
    }

    public function fromJSON(stdClass $json): void
    {
        $this->setValidUntil($json->valid_until);
        $now = new DateTime();
        $this->setExpired($json->valid_until !== null && $this->getValidUntil() < $now);
        $this->setCanBeUsed(!$this->isExpired());
    }

    public function getValidUntil(): ?DateTime
    {
        return $this->validUntil;
    }

    public function setValidUntil(DateTime|string|null $validUntil): void
    {
        $this->validUntil = null;
        if ($validUntil === null) {
            return;
        }
        $this->validUntil = \is_string($validUntil)
            ? Carbon::createFromTimeString($validUntil, 'UTC')
                ->toDateTime()
                ->setTimezone(new DateTimeZone(\SHOP_TIMEZONE))
            : $validUntil;
    }

    public function getDaysRemaining(): int
    {
        if ($this->getValidUntil() === null) {
            return 0;
        }

        return (int)(new DateTime())->diff($this->getValidUntil())->format('%R%a');
    }

    public function isExpired(): bool
    {
        return $this->expired;
    }

    public function setExpired(bool $expired): void
    {
        $this->expired = $expired;
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
