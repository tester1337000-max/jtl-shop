<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum Cron: string implements OptionInterface
{
    case FREQUENCY = 'cron_freq';
    case TYPE      = 'cron_type';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::CRON;
    }
}
