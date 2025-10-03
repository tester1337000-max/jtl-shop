<?php

declare(strict_types=1);

namespace JTL\Services\JTL;

use Illuminate\Support\Collection;
use JTL\Cache\JTLCacheInterface;
use JTL\Country\Continent;
use JTL\Country\Country;
use JTL\Country\State;
use JTL\DB\DbInterface;
use JTL\Helpers\Text;
use JTL\Shop;
use ReflectionClass;
use ReflectionException;

/**
 * Class CountryService
 * @package JTL\Services\JTL
 */
class CountryService implements CountryServiceInterface
{
    /**
     * @var Collection<int, Country>
     */
    private Collection $countryList;

    public const CACHE_ID = 'serviceCountryList';

    public function __construct(private readonly DbInterface $db, private readonly JTLCacheInterface $cache)
    {
        $this->countryList = new Collection();
        $this->init();
    }

    public function init(): void
    {
        $languageID = Shop::getLanguageID();
        /** @var Collection<int, Country>|false $countries */
        $countries = $this->cache->get(self::CACHE_ID);
        if ($countries !== false) {
            $this->countryList = $countries->sortBy(static function (Country $country) use ($languageID): string {
                return Text::replaceUmlauts($country->getName($languageID));
            });

            return;
        }
        $countries       = $this->db->getObjects('SELECT * FROM tland');
        $shippingMethods = $this->db->getObjects('SELECT cLaender FROM tversandart');
        $possibleStates  = $this->db->getCollection('SELECT DISTINCT cLandIso FROM tstaat')
            ->pluck('cLandIso');
        if ($possibleStates === null) {
            return;
        }
        $possibleStates       = $possibleStates->toArray();
        $deliverableCountries = [];
        foreach ($shippingMethods as $shippingMethod) {
            $deliverableCountries = \array_unique(
                \array_merge(
                    $deliverableCountries,
                    \explode(' ', $shippingMethod->cLaender)
                )
            );
        }
        $languages = Shop::Lang()->getAllLanguages();
        foreach ($countries as $country) {
            $countryTMP = new Country($country->cISO, false, $languages);
            $countryTMP->setEU((int)$country->nEU)
                ->setContinent($country->cKontinent)
                ->setNameDE($country->cDeutsch)
                ->setNameEN($country->cEnglisch)
                ->setPermitRegistration((int)$country->bPermitRegistration === 1)
                ->setRequireStateDefinition((int)$country->bRequireStateDefinition === 1)
                ->setShippingAvailable(\in_array($countryTMP->getISO(), $deliverableCountries, true));
            if (\in_array($countryTMP->getISO(), $possibleStates, true)) {
                $countryTMP->setStates($this->getStates($countryTMP->getISO()));
            }
            $this->addCountryToList($countryTMP);
        }

        $this->countryList = $this->countryList->sortBy(static function (Country $country) use ($languageID): string {
            return Text::replaceUmlauts($country->getName($languageID));
        });

        $this->cache->set(self::CACHE_ID, $this->countryList, [\CACHING_GROUP_OBJECT]);
    }

    /**
     * @return Collection<int, Country>
     */
    public function getCountryList(): Collection
    {
        return $this->countryList;
    }

    public function addCountryToList(Country $country): void
    {
        $this->countryList->push($country);
    }

    /**
     * @inheritdoc
     */
    public function getCountry(string $iso): ?Country
    {
        return $this->getCountryList()->first(fn(Country $country): bool => $country->getISO() === \strtoupper($iso));
    }

    /**
     * @inheritdoc
     */
    public function getFilteredCountryList(array $ISOToFilter, bool $getAllIfEmpty = false): Collection
    {
        if ($getAllIfEmpty && empty($ISOToFilter)) {
            return $this->getCountryList();
        }
        $filterItems = \array_map('\strtoupper', $ISOToFilter);

        return $this->getCountryList()
            ->filter(fn(Country $country): bool => \in_array($country->getISO(), $filterItems, true));
    }

    /**
     * @inheritdoc
     */
    public function getIsoByCountryName(string $countryName): ?string
    {
        $name = \strtolower($countryName);
        /** @var Country|null $match */
        $match = $this->getCountryList()->first(static function (Country $country) use ($name): bool {
            foreach ($country->getNames() as $tmpName) {
                if (\strtolower($tmpName) === $name || $name === \strtolower($country->getNameDE())) {
                    return true;
                }
            }

            return false;
        });

        return $match?->getISO();
    }

    /**
     * @inheritdoc
     */
    public function getCountriesGroupedByContinent(bool $getEU = false, array $selectedCountries = []): array
    {
        $continentsTMP                = [];
        $continentsSelectedCountryTMP = [];
        $continents                   = [];
        /** @var Country $country */
        foreach ($this->getCountryList() as $country) {
            $countrySelected                           = \in_array($country->getISO(), $selectedCountries, true);
            $continentsTMP[$country->getContinent()][] = $country;
            if ($countrySelected) {
                $continentsSelectedCountryTMP[$country->getContinent()][] = $country;
            }
            if (!$getEU) {
                continue;
            }
            if ($country->isEU()) {
                $continentsTMP[\__('europeanUnion')][] = $country;
                if ($countrySelected) {
                    $continentsSelectedCountryTMP[\__('europeanUnion')][] = $country;
                }
            } elseif ($country->getContinent() === \__('Europa')) {
                $continentsTMP[\__('notEuropeanUnionEurope')][] = $country;
                if ($countrySelected) {
                    $continentsSelectedCountryTMP[\__('notEuropeanUnionEurope')][] = $country;
                }
            }
        }
        /** @var string $continent */
        foreach ($continentsTMP as $continent => $countries) {
            $continents[] = (object)[
                'name'                   => $continent,
                'countries'              => $countries,
                'countriesCount'         => \count($countries),
                'countriesSelectedCount' => \count($continentsSelectedCountryTMP[$continent] ?? []),
                'sort'                   => $this->getContinentSort($continent)
            ];
        }
        \usort($continents, static fn(\stdClass $a, \stdClass $b): int => $a->sort <=> $b->sort);

        return $continents;
    }

    public function getContinentSort(string $continent): int
    {
        return match ($continent) {
            \__('Europa')                 => 1,
            \__('europeanUnion')          => 2,
            \__('notEuropeanUnionEurope') => 3,
            \__('Asien')                  => 4,
            \__('Afrika')                 => 5,
            \__('Nordamerika')            => 6,
            \__('Suedamerika')            => 7,
            \__('Ozeanien')               => 8,
            \__('Antarktis')              => 9,
            default                       => 0,
        };
    }

    /**
     * @inheritdoc
     */
    public function getContinents(): array
    {
        $continents = [];
        try {
            $reflection = new ReflectionClass(Continent::class);
            $continents = $reflection->getConstants();
        } catch (ReflectionException $e) {
            Shop::Container()->getLogService()->notice($e->getMessage());
        }

        return $continents;
    }

    /**
     * @return State[]
     */
    private function getStates(string $iso): array
    {
        $states    = [];
        $countries = $this->db->selectAll('tstaat', 'cLandIso', $iso, '*', 'cName');
        foreach ($countries as $country) {
            $state = new State();
            $state->setID((int)$country->kStaat)
                ->setISO($country->cCode)
                ->setName($country->cName)
                ->setCountryISO($country->cLandIso);
            $states[] = $state;
        }

        return $states;
    }
}
