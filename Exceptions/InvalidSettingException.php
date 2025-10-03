<?php

declare(strict_types=1);

namespace JTL\Exceptions;

use Exception;

/**
 * Class InvalidSettingException
 * @package JTL\Exceptions
 */
class InvalidSettingException extends Exception
{
    public function __construct(string $message)
    {
        parent::__construct('Einstellungsfehler: ' . $message);
    }
}
