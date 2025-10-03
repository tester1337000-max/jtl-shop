<?php

declare(strict_types=1);

namespace JTL\License\Struct;

use stdClass;

/**
 * Class Link
 * @package JTL\License
 */
class Link
{
    private string $href;

    private string $rel;

    private string $method = 'GET';

    public function __construct(?stdClass $json = null)
    {
        if ($json !== null) {
            $this->fromJSON($json);
        }
    }

    public function fromJSON(stdClass $json): void
    {
        $this->setHref($json->href);
        $this->setRel($json->rel);
        $this->setMethod($json->method ?? 'GET');
    }

    public function getHref(): string
    {
        return $this->href;
    }

    public function setHref(string $href): void
    {
        $this->href = $href;
    }

    public function getRel(): string
    {
        return $this->rel;
    }

    public function setRel(string $rel): void
    {
        $this->rel = $rel;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): void
    {
        $this->method = $method;
    }
}
