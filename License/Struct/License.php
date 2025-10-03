<?php

declare(strict_types=1);

namespace JTL\License\Struct;

use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use stdClass;

/**
 * Class License
 * @package JTL\License
 */
class License
{
    public const TYPE_FREE = 'free';

    public const TYPE_PROD = 'prod';

    public const TYPE_DEV = 'dev';

    public const TYPE_TEST = 'test';

    public const TYPE_NONE = 'none';

    private string $key;

    private string $type;

    private DateTime $created;

    private ?DateTime $validUntil = null;

    private Subscription $subscription;

    private bool $expired = false;

    private bool $isBound = false;

    public function __construct(?stdClass $json = null)
    {
        if ($json !== null) {
            $this->fromJSON($json);
        }
    }

    public function fromJSON(stdClass $json): void
    {
        if (!isset($json->subscription) || $json->subscription === 'null') {
            $json->subscription = null;
        }
        $this->setKey($json->key);
        $this->setType($json->type);
        $this->setCreated($json->created);
        $this->setValidUntil($json->valid_until);
        $this->setSubscription(new Subscription($json->subscription));
        $this->setIsBound($json->is_bound);
        if ($this->getValidUntil() !== null) {
            $now = new DateTime();
            $this->setExpired($this->getValidUntil() < $now);
        }
        if ($this->getType() === self::TYPE_DEV) {
            $this->setValidUntil(null);
            $this->getSubscription()->setValidUntil(null);
        }
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): void
    {
        $this->key = $key;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }

    public function setCreated(DateTime|string $created): void
    {
        $this->created = \is_string($created) ? new DateTime($created) : $created;
    }

    public function getSubscription(): Subscription
    {
        return $this->subscription;
    }

    public function setSubscription(Subscription $subscription): void
    {
        $this->subscription = $subscription;
    }

    public function getValidUntil(): ?DateTime
    {
        return $this->validUntil;
    }

    public function setValidUntil(DateTime|string|null $validUntil): void
    {
        if ($validUntil !== null) {
            $this->validUntil = \is_string($validUntil)
                ? Carbon::createFromTimeString($validUntil, 'UTC')
                    ->toDateTime()
                    ->setTimezone(new DateTimeZone(\SHOP_TIMEZONE))
                : $validUntil;
        }
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

    public function isBound(): bool
    {
        return $this->isBound;
    }

    public function setIsBound(bool $isBound): void
    {
        $this->isBound = $isBound;
    }
}
