<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Router\Route;
use JTL\Settings\Option\Overview;
use JTL\Settings\Settings;

class FulltextIndex extends AbstractStatusCheck
{
    protected bool $includeInServiceReport = true;

    public function isOK(): bool
    {
        $productIndex     = $this->db->getSingleObject(
            "SHOW INDEX
                    FROM tartikel
                    WHERE KEY_NAME = 'idx_tartikel_fulltext'"
        );
        $productLangIndex = $this->db->getSingleObject(
            "SHOW INDEX
                    FROM tartikelsprache
                    WHERE KEY_NAME = 'idx_tartikelsprache_fulltext'"
        );

        return Settings::stringValue(Overview::SEARCH_FULLTEXT) === 'N'
            || ($productIndex !== null && $productLangIndex !== null);
    }

    public function getURL(): ?string
    {
        return $this->adminURL . Route::SEARCHCONFIG;
    }

    public function getTitle(): string
    {
        return \__('hasFullTextIndexErrorTitle');
    }

    public function generateMessage(): void
    {
        $this->addNotification(
            \__('hasFullTextIndexErrorTitle'),
            \__('hasFullTextIndexErrorMessage')
        );
    }
}
