<?php

declare(strict_types=1);

// Define global functions used during tests
if (!function_exists('__')) {
    function __($msgid): string
    {
        return $msgid . '_translated';
    }
}
