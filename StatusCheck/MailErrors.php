<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Mail\MailService;
use JTL\Router\Route;

class MailErrors extends AbstractStatusCheck
{
    protected bool $includeInServiceReport = true;

    public function __construct(
        DbInterface $db,
        JTLCacheInterface $cache,
        string $adminURL,
        private readonly MailService $mailService = new MailService(),
    ) {
        parent::__construct($db, $cache, $adminURL);
    }

    public function isOK(): bool
    {
        return $this->mailService->getErroneousMailsCount() === 0;
    }

    public function getURL(): ?string
    {
        return $this->adminURL . Route::EMAILHISTORY . '?tab=mailsError';
    }

    public function getTitle(): string
    {
        return \__('hasMailsErrorTitle');
    }

    public function generateMessage(): void
    {
        $this->addNotification(\__('hasMailsErrorMessage'));
    }
}
