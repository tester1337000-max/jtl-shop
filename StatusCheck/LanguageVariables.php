<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Language\LanguageHelper;
use JTL\Router\Route;

class LanguageVariables extends AbstractStatusCheck
{
    public function isOK(): bool
    {
        return \count(LanguageHelper::getInstance()->getMissingLang()) === 0;
    }

    public function getURL(): ?string
    {
        return $this->adminURL . Route::LANGUAGE . '#notfound';
    }

    public function getTitle(): string
    {
        return \__('getMissingLanguageVariablesTitle');
    }

    public function generateMessage(): void
    {
        $this->addNotification(\__('getMissingLanguageVariablesMessage'));
    }
}
