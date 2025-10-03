<?php

declare(strict_types=1);

namespace JTL;

/**
 * Class Staat
 * @package JTL
 */
class Staat
{
    public ?int $kStaat = null;

    public ?string $cLandIso = null;

    public ?string $cName = null;

    public ?string $cCode = null;

    /**
     * @param array<string, mixed>|null $options
     */
    public function __construct(?array $options = null)
    {
        if (\is_array($options)) {
            $this->setOptions($options);
        }
    }

    /**
     * @param array<string, mixed> $options
     * @return $this
     */
    public function setOptions(array $options): self
    {
        $methods = \get_class_methods($this);
        foreach ($options as $key => $value) {
            $method = 'set' . \ucfirst($key);
            if (\in_array($method, $methods, true) && \method_exists($this, $method)) {
                $this->$method($value);
            }
        }

        return $this;
    }

    public function getStaat(): ?int
    {
        return $this->kStaat;
    }

    public function getLandIso(): ?string
    {
        return $this->cLandIso;
    }

    public function getName(): ?string
    {
        return $this->cName;
    }

    public function getCode(): ?string
    {
        return $this->cCode;
    }

    public function setStaat(int|string $kStaat): self
    {
        $this->kStaat = (int)$kStaat;

        return $this;
    }

    public function setLandIso(string $cLandIso): self
    {
        $this->cLandIso = $cLandIso;

        return $this;
    }

    public function setName(string $name): self
    {
        $this->cName = $name;

        return $this;
    }

    public function setCode(string $cCode): self
    {
        $this->cCode = $cCode;

        return $this;
    }

    /**
     * @return self[]|null
     */
    public static function getRegions(string $iso): ?array
    {
        $countries = Shop::Container()->getDB()->selectAll('tstaat', 'cLandIso', $iso, '*', 'cName');
        if (\count($countries) === 0) {
            return null;
        }
        $states = [];
        foreach ($countries as $country) {
            $options = [
                'Staat'   => (int)$country->kStaat,
                'LandIso' => $country->cLandIso,
                'Name'    => $country->cName,
                'Code'    => $country->cCode,
            ];

            $states[] = new self($options);
        }

        return $states;
    }

    public static function getRegionByIso(string $code, string $countryISO = ''): ?Staat
    {
        $key2 = null;
        $val2 = null;
        if (\mb_strlen($countryISO) > 0) {
            $key2 = 'cLandIso';
            $val2 = $countryISO;
        }
        $data = Shop::Container()->getDB()->select('tstaat', 'cCode', $code, $key2, $val2);
        if ($data === null || $data->kStaat <= 0) {
            return null;
        }
        $options = [
            'Staat'   => (int)$data->kStaat,
            'LandIso' => $data->cLandIso,
            'Name'    => $data->cName,
            'Code'    => $data->cCode,
        ];

        return new self($options);
    }

    public static function getRegionByName(string $name): ?Staat
    {
        $data = Shop::Container()->getDB()->select('tstaat', 'cName', $name);
        if ($data === null || $data->kStaat <= 0) {
            return null;
        }
        $options = [
            'Staat'   => (int)$data->kStaat,
            'LandIso' => $data->cLandIso,
            'Name'    => $data->cName,
            'Code'    => $data->cCode,
        ];

        return new self($options);
    }
}
