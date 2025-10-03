<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade;

enum Channel: string
{
    case STABLE        = 'STABLE';
    case BETA          = 'BETA';
    case ALPHA         = 'ALPHA';
    case BLEEDING_EDGE = 'BLEEDINGEDGE';
}
