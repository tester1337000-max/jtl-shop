<?php

declare(strict_types=1);

namespace JTL\License\Struct;

use stdClass;

/**
 * Class InAppParent
 * @package JTL\License\Struct
 */
class InAppParent
{
    private ?string $name = null;

    private ?string $exsid = null;

    public function __construct(?stdClass $json = null)
    {
        if ($json !== null && isset($json->parent_id)) {
            $this->setName($json->parent_name);
            $this->setExsID($json->parent_id);
        }
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getExsID(): ?string
    {
        return $this->exsid;
    }

    public function setExsID(?string $exsid): void
    {
        $this->exsid = $exsid;
    }
}
