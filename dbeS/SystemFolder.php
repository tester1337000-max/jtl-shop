<?php

declare(strict_types=1);

namespace JTL\dbeS;

/**
 * Class SystemFolder
 * @package JTL\dbeS
 */
class SystemFolder
{
    /**
     * @param SystemFolder[] $oSubFolders
     */
    public function __construct(
        public string $cBaseName = '',
        public string $cBasePath = '',
        public array $oSubFolders = []
    ) {
    }
}
