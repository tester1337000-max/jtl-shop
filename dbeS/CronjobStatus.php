<?php

declare(strict_types=1);

namespace JTL\dbeS;

/**
 * Class CronjobStatus
 * @package JTL\dbeS
 */
class CronjobStatus
{
    public function __construct(
        public int $kCron,
        public string $cExportformat,
        public string $cStartDate,
        public int $nRepeat,
        public int $nDone,
        public int $nOverall,
        public ?string $cLastStartDate,
        public ?string $cNextStartDate
    ) {
    }
}
