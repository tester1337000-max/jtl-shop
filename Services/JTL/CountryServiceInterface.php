<?php

declare(strict_types=1);

namespace JTL\Services\JTL;

use Illuminate\Support\Collection;
use JTL\Country\Country;

/**
 * Interface CountryServiceInterface
 * @package JTL\Services\JTL
 */
interface CountryServiceInterface
{
    /**
     * @return void
     */
    public function init(): void;

    /**
     * @return Collection<int, Country>
     */
    public function getCountrylist(): Collection;

    public function addCountryToList(Country $country): void;

    /**
     * @param string $iso
     * @return null|Country
     */
    public function getCountry(string $iso): ?Country;

    /**
     * @param string[] $ISOToFilter
     * @param bool     $getAllIfEmpty
     * @return Collection<int, Country>
     */
    public function getFilteredCountryList(array $ISOToFilter, bool $getAllIfEmpty = false): Collection;

    /**
     * @param string $countryName
     * @return null|string
     */
    public function getIsoByCountryName(string $countryName): ?string;

    /**
     * @param bool     $getEU
     * @param string[] $selectedCountries
     * @return \stdClass[]
     */
    public function getCountriesGroupedByContinent(bool $getEU = false, array $selectedCountries = []): array;

    /**
     * @return array<string, string>
     */
    public function getContinents(): array;
}
