<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Backend\NotificationEntry;
use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;

class AbstractStatusCheck implements StatusCheckInterface
{
    protected ?NotificationEntry $notification = null;

    protected bool $stopFurtherChecks = false;

    protected bool $includeInServiceReport = false;

    protected int $messageType = NotificationEntry::TYPE_WARNING;

    protected mixed $data = null;

    public function __construct(
        protected readonly DbInterface $db,
        protected readonly JTLCacheInterface $cache,
        protected readonly string $adminURL
    ) {
    }

    public function isOK(): bool
    {
        return false;
    }

    public function generateMessage(): void
    {
    }

    public function stopFurtherChecks(): bool
    {
        return $this->stopFurtherChecks;
    }

    public function includeInServiceReport(): bool
    {
        return $this->includeInServiceReport;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function getURL(): ?string
    {
        return null;
    }

    public function getTitle(): string
    {
        return '';
    }

    protected function addNotification(?string $description = null, ?string $hash = null, ?string $title = null): void
    {
        $this->notification = new NotificationEntry(
            $this->messageType,
            $title ?? $this->getTitle(),
            $description,
            $this->getURL(),
            $hash
        );
    }

    public function getNotification(): ?NotificationEntry
    {
        return $this->notification;
    }
}
