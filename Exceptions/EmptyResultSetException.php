<?php

declare(strict_types=1);

namespace JTL\Exceptions;

use Exception;

/**
 * Class EmptyResultSetException
 * @package JTL\Exceptions
 */
class EmptyResultSetException extends Exception
{
    public function __construct(string $message)
    {
        parent::__construct(\str_replace(\PFAD_ROOT, '', $this->file) . ': ' . $message);
    }
}
