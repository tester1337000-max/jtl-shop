<?php

declare(strict_types=1);

namespace JTL\Backend\LocalizationCheck;

use Illuminate\Support\Collection;

/**
 * Class Result
 * @package JTL\Backend\LocalizationCheck
 */
class Result
{
    /**
     * @var class-string<LocalizationCheckInterface>
     */
    private string $className;

    private string $location;

    /**
     * @var Collection<int, Item>
     */
    private Collection $excessLocalizations;

    /**
     * @var Collection<int, Item>
     */
    private Collection $missingLocalizations;

    /**
     * @return class-string<LocalizationCheckInterface>
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * @param class-string<LocalizationCheckInterface> $className
     */
    public function setClassName(string $className): void
    {
        $this->className = $className;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function setLocation(string $location): void
    {
        $this->location = $location;
    }

    /**
     * @return Collection<int, Item>
     */
    public function getExcessLocalizations(): Collection
    {
        return $this->excessLocalizations;
    }

    /**
     * @param Collection<int, Item> $excessLocalizations
     */
    public function setExcessLocalizations(Collection $excessLocalizations): void
    {
        $this->excessLocalizations = $excessLocalizations;
    }

    /**
     * @return Collection<int, Item>
     */
    public function getMissingLocalizations(): Collection
    {
        return $this->missingLocalizations;
    }

    /**
     * @param Collection<int, Item> $missingLocalizations
     */
    public function setMissingLocalizations(Collection $missingLocalizations): void
    {
        $this->missingLocalizations = $missingLocalizations;
    }

    public function getErrorCount(): int
    {
        return $this->missingLocalizations->count() + $this->excessLocalizations->count();
    }

    public function hasPassed(): bool
    {
        return $this->getErrorCount() === 0;
    }
}
