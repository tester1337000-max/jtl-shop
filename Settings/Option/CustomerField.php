<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum CustomerField: string implements OptionInterface
{
    case DO_SHOW = 'kundenfeld_anzeigen';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::CUSTOMERFIELD;
    }
}
