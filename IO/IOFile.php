<?php

declare(strict_types=1);

namespace JTL\IO;

use JsonSerializable;

/**
 * Class IOFile
 * @package JTL\IO
 */
class IOFile implements JsonSerializable
{
    public function __construct(public string $filename, public string $mimetype)
    {
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return [
            'filename' => $this->filename,
            'mimetype' => $this->mimetype
        ];
    }
}
