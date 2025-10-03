<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum Consent: string implements OptionInterface
{
    case DO_SHOW     = 'consent_manager_active';
    case SHOW_BANNER = 'consent_manager_show_banner';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::CONSENT;
    }
}
