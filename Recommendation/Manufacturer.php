<?php

declare(strict_types=1);

namespace JTL\Recommendation;

use stdClass;

/**
 * Class Manufacturer
 * @package JTL\Recommendation
 */
class Manufacturer
{
    private string $name;

    private string $profileURL;

    public function __construct(stdClass $manufacturer)
    {
        $this->setName($manufacturer->company_name);
        $this->setProfileURL($manufacturer->profile_url);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getProfileURL(): string
    {
        return $this->profileURL;
    }

    public function setProfileURL(string $profileURL): void
    {
        $this->profileURL = $profileURL;
    }
}
