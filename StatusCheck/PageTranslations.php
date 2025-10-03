<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Backend\NotificationEntry;
use JTL\Link\Admin\LinkAdmin;
use JTL\Router\Route;

class PageTranslations extends AbstractStatusCheck
{
    protected int $messageType = NotificationEntry::TYPE_DANGER;

    private int $missingTranslations = 0;

    public function isOK(): bool
    {
        $linkAdmin                 = new LinkAdmin($this->db, $this->cache);
        $this->missingTranslations = $linkAdmin->getUntranslatedPageIDs()->count();

        return $this->missingTranslations === 0;
    }

    public function getURL(): ?string
    {
        return $this->adminURL . Route::LINKS;
    }

    public function getTitle(): string
    {
        return \__('Missing translations');
    }

    public function generateMessage(): void
    {
        $this->addNotification(
            \sprintf(\__('%d pages are not translated in all available languages.'), $this->missingTranslations)
        );
    }
}
