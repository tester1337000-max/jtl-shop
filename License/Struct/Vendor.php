<?php

declare(strict_types=1);

namespace JTL\License\Struct;

use stdClass;

/**
 * Class Vendor
 * @package JTL\License
 */
class Vendor
{
    private string $name;

    private string $href;

    public function __construct(?stdClass $json = null)
    {
        if ($json !== null) {
            $this->fromJSON($json);
        }
    }

    public function fromJSON(stdClass $json): void
    {
        $this->setName($json->name);
        $this->setHref($json->href);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getHref(): string
    {
        return $this->href;
    }

    public function setHref(string $href): void
    {
        $this->href = $href;
    }
}
