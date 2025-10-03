<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Router\Route;

class Profiler extends AbstractStatusCheck
{
    protected bool $includeInServiceReport = true;

    public function isOK(?string &$hash = null): bool
    {
        return \JTL\Profiler::getIsActive() === 0;
    }

    public function getTitle(): string
    {
        return \__('hasActiveProfilerTitle');
    }

    public function getURL(): string
    {
        return $this->adminURL . Route::PROFILER;
    }

    public function generateMessage(): void
    {
        $this->addNotification(\__('hasActiveProfilerMessage'));
    }
}
