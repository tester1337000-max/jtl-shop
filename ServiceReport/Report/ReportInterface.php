<?php

declare(strict_types=1);

namespace JTL\ServiceReport\Report;

interface ReportInterface
{
    /**
     * @return array<mixed>
     */
    public function getData(): array;
}
