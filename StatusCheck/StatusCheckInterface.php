<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Backend\NotificationEntry;

interface StatusCheckInterface
{
    public function isOK(): bool;

    public function generateMessage(): void;

    public function stopFurtherChecks(): bool;

    public function includeInServiceReport(): bool;

    public function getData(): mixed;

    public function getURL(): ?string;

    public function getTitle(): string;

    public function getNotification(): ?NotificationEntry;
}
