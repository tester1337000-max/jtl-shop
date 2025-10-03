<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum Blocklist: string implements OptionInterface
{
    case DO_USE = 'blacklist_benutzen';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::BLOCKLIST;
    }
}
