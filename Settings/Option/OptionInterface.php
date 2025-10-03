<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

interface OptionInterface
{
    public function getValue(): string;

    public function getSection(): Section;
}
