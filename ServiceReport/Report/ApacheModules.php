<?php

declare(strict_types=1);

namespace JTL\ServiceReport\Report;

class ApacheModules implements ReportInterface
{
    /**
     * @return string[]
     */
    public function getData(): array
    {
        return \function_exists('apache_get_modules') ? \apache_get_modules() : [];
    }
}
