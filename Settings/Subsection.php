<?php

declare(strict_types=1);

namespace JTL\Settings;

enum Subsection: string
{
    case GENERAL  = 'general';
    case THEME    = 'theme';
    case HEADER   = 'header';
    case MENU     = 'megamenu';
    case OVERVIEW = 'productlist';
    case PRODUCT  = 'productdetails';
    case SASS     = 'customsass';
    case FOOTER   = 'footer';
}
