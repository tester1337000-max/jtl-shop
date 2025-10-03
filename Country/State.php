<?php

declare(strict_types=1);

namespace JTL\Country;

use JTL\MagicCompatibilityTrait;

/**
 * Class State
 * @package JTL\Country
 */
class State
{
    use MagicCompatibilityTrait;

    /**
     * @var array<string, string>
     */
    protected static array $mapping = [
        'kStaat'   => 'ID',
        'cLandIso' => 'CountryISO',
        'cName'    => 'Name',
        'cCode'    => 'ISO'
    ];

    public int $id = 0;

    public string $countryISO = '';

    public string $name = '';

    public string $iso = '';

    public function __construct()
    {
    }

    public function getID(): int
    {
        return $this->id;
    }

    public function setID(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getCountryISO(): string
    {
        return $this->countryISO;
    }

    public function setCountryISO(string $countryISO): self
    {
        $this->countryISO = $countryISO;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getISO(): string
    {
        return $this->iso;
    }

    public function setISO(string $iso): self
    {
        $this->iso = $iso;

        return $this;
    }
}
