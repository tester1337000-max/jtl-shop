<?php

declare(strict_types=1);

namespace JTL\Exceptions;

use Exception;

/**
 * Class InvalidInputException
 * @package JTL\Exceptions
 */
class InvalidInputException extends Exception
{
    public function __construct(string $message, string $origInput = '')
    {
        parent::__construct($message . ' (' . $origInput . ')');
    }
}
