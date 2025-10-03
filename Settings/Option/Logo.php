<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum Logo: string implements OptionInterface
{
    case SHOP_LOGO = 'shop_logo';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::LOGO;
    }
}
