<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use Illuminate\Support\Collection;
use JTL\Backend\LocalizationCheck\LocalizationCheckFactory;
use JTL\Backend\LocalizationCheck\Result;
use JTL\Language\LanguageHelper;
use JTL\Router\Route;

class Localization extends AbstractStatusCheck
{
    public function isOK(): bool
    {
        if (\SAFE_MODE === true) {
            return true;
        }
        $ok        = true;
        $languages = \collect(LanguageHelper::getAllLanguages(0, true, true));
        $factory   = new LocalizationCheckFactory($this->db, $languages);
        $results   = new Collection();
        foreach ($factory->getAllChecks() as $check) {
            $result  = new Result();
            $excess  = $check->getExcessLocalizations();
            $missing = $check->getItemsWithoutLocalization();
            $result->setLocation($check->getLocation());
            $result->setClassName(\get_class($check));
            $result->setExcessLocalizations($excess);
            $result->setMissingLocalizations($missing);
            if (($missing->count() > 0 || $excess->count() > 0)) {
                $ok = false;
            }
            $results->push($result);
        }
        $this->data = $results;

        return $ok;
    }

    public function getTitle(): string
    {
        return \__('Localizations');
    }

    public function getURL(): ?string
    {
        return $this->adminURL . Route::LOCALIZATION_CHECK;
    }

    public function generateMessage(): void
    {
    }
}
