<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum PriceHistory: string implements OptionInterface
{
    case DO_USE = 'preisverlauf_anzeigen';
    case MONTHS = 'preisverlauf_anzahl_monate';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::PRICEHISTORY;
    }
}
