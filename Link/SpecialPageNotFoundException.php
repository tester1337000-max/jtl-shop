<?php

declare(strict_types=1);

namespace JTL\Link;

use RuntimeException;

/**
 * Class SpecialPageNotFoundException
 * @package JTL\Link
 */
class SpecialPageNotFoundException extends RuntimeException
{
    public function __construct(int $linkType)
    {
        parent::__construct(
            'Special page for link type ' . $linkType . '  could not be found. Please check the '
            . 'notifications in the admin backend and resolve all issues regarding missing special pages.',
            404
        );
    }
}
