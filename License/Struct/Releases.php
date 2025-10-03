<?php

declare(strict_types=1);

namespace JTL\License\Struct;

use stdClass;

/**
 * Class Releases
 * @package JTL\License\Struct
 */
class Releases
{
    private ?Release $latest = null;

    private ?Release $available = null;

    public function __construct(?stdClass $json = null)
    {
        if ($json !== null) {
            $this->fromJSON($json);
        }
    }

    public function fromJSON(stdClass $json): void
    {
        $this->setAvailable($this->createRelease($json->available ?? null));
        $this->setLatest($this->createRelease($json->latest ?? null));
    }

    private function createRelease(?stdClass $data = null): ?Release
    {
        if ($data === null) {
            return null;
        }

        return new Release($data);
    }

    public function getLatest(): ?Release
    {
        return $this->latest;
    }

    public function setLatest(?Release $latest): void
    {
        $this->latest = $latest;
    }

    public function getAvailable(): ?Release
    {
        return $this->available;
    }

    public function setAvailable(?Release $available): void
    {
        $this->available = $available;
    }
}
