<?php

declare(strict_types=1);

namespace JTL\Mail\Template;

/**
 * Class ProductTemplate
 *
 * @package JTL\Mail\Template
 */
class ProductTemplate extends AbstractTemplate
{
    protected function sanitizeData(mixed $data): object|null
    {
        return \is_object($data) ? (object)$data : null;
    }
}
