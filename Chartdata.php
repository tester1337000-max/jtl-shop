<?php

declare(strict_types=1);

namespace JTL;

use Exception;
use stdClass;

/**
 * Class Chartdata
 * @package JTL
 */
class Chartdata
{
    protected bool $bActive = false;

    protected ?stdClass $xAxis = null;

    /**
     * @var stdClass[]|null
     */
    protected ?array $series = null;

    protected ?string $xAxisJSON = null;

    protected ?string $seriesJSON = null;

    protected ?string $url = null;

    /**
     * @param array<string, mixed>|null $options
     */
    public function __construct(?array $options = null)
    {
        if (\is_array($options)) {
            $this->setOptions($options);
        }
    }

    public function __set(string $name, mixed $value): void
    {
        $method = 'set' . $name;
        if ($name === 'mapper' || !\method_exists($this, $method)) {
            throw new Exception('Invalid Query property');
        }
        $this->$method($value);
    }

    public function __get(string $name): mixed
    {
        $method = 'get' . $name;
        if ($name === 'mapper' || !\method_exists($this, $method)) {
            throw new Exception('Invalid Query property');
        }

        return $this->$method();
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options): self
    {
        $methods = \get_class_methods($this);
        foreach ($options as $key => $value) {
            $method = 'set' . \ucfirst($key);
            if (\in_array($method, $methods, true)) {
                $this->$method($value);
            }
        }

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $array   = [];
        $members = \array_keys(\get_object_vars($this));
        foreach ($members as $member) {
            $array[\mb_substr($member, 1)] = $this->$member;
        }

        return $array;
    }

    public function setActive(bool $active): self
    {
        $this->bActive = $active;

        return $this;
    }

    public function setAxis(stdClass $axis): self
    {
        $this->xAxis = $axis;

        return $this;
    }

    /**
     * @param stdClass[] $series
     */
    public function setSeries(array $series): self
    {
        $this->series = $series;

        return $this;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getActive(): ?bool
    {
        return $this->bActive;
    }

    public function getAxis(): ?stdClass
    {
        return $this->xAxis;
    }

    /**
     * @return stdClass[]|null
     */
    public function getSeries(): ?array
    {
        return $this->series;
    }

    public function getAxisJSON(): ?string
    {
        return $this->xAxisJSON;
    }

    public function getSeriesJSON(): ?string
    {
        return $this->seriesJSON;
    }

    /**
     * @throws \JsonException
     */
    public function memberToJSON(): self
    {
        $this->seriesJSON = \json_encode($this->series, \JSON_THROW_ON_ERROR);
        $this->xAxisJSON  = \json_encode($this->xAxis, \JSON_THROW_ON_ERROR);

        return $this;
    }
}
