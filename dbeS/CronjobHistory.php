<?php

declare(strict_types=1);

namespace JTL\dbeS;

/**
 * Class CronjobHistory
 * @package JTL\dbeS
 */
class CronjobHistory
{
    public function __construct(
        public string $cExportformat,
        public string $cDateiname,
        public int $nDone,
        public string $cLastStartDate
    ) {
    }
}
