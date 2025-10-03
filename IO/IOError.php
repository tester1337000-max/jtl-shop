<?php

declare(strict_types=1);

namespace JTL\IO;

use JsonSerializable;

/**
 * Class IOError
 * @package JTL\IO
 */
class IOError implements JsonSerializable
{
    /**
     * @param string[] $errors
     */
    public function __construct(
        public string $message,
        public int $code = 500,
        public array $errors = [],
        public string $additional = ''
    ) {
    }

    /**
     * @return array<'error', array<string, int|string|string[]>>
     */
    public function jsonSerialize(): array
    {
        return [
            'error' => [
                'message'    => $this->message,
                'code'       => $this->code,
                'errors'     => $this->errors,
                'additional' => $this->additional
            ]
        ];
    }
}
