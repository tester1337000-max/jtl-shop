<?php

declare(strict_types=1);

namespace JTL\Country;

use JTL\Language\LanguageModel;
use JTL\MagicCompatibilityTrait;
use JTL\Shop;

/**
 * Class Country
 * @package JTL\Country
 */
class Country
{
    use MagicCompatibilityTrait;

    /**
     * @var array<string, string>
     */
    protected static array $mapping = [
        'nEU'        => 'EU',
        'cDeutsch'   => 'Name',
        'cEnglisch'  => 'Name',
        'cKontinent' => 'Continent',
        'cISO'       => 'ISO',
        'cName'      => 'Name'
    ];

    private string $iso;

    private int $eu = 0;

    private ?string $continent = null;

    /**
     * @var array<int, string>
     */
    private array $names = [];

    /**
     * for backwards compatibility cDeutsch
     */
    private string $nameDE = '';

    /**
     * for backwards compatibility cEnglisch
     */
    private string $nameEN = '';

    private bool $shippingAvailable = false;

    private bool $permitRegistration = false;

    private bool $requireStateDefinition = false;

    /**
     * @var State[]
     */
    private array $states = [];

    /**
     * @param LanguageModel[]|null $languages
     */
    public function __construct(string $iso, bool $initFromDB = false, ?array $languages = null)
    {
        $this->setISO($iso);
        foreach ($languages ?? Shop::Lang()->getAllLanguages() as $lang) {
            $this->setName($lang);
        }
        if ($initFromDB) {
            $this->initFromDB();
        }
    }

    private function initFromDB(): void
    {
        $db          = Shop::Container()->getDB();
        $countryData = $db->select('tland', 'cISO', $this->getISO());
        if ($countryData === null) {
            return;
        }
        $this->setContinent($countryData->cKontinent)
            ->setEU((int)$countryData->nEU)
            ->setNameDE($countryData->cDeutsch)
            ->setNameEN($countryData->cEnglisch)
            ->setPermitRegistration((int)$countryData->bPermitRegistration === 1)
            ->setRequireStateDefinition((int)$countryData->bRequireStateDefinition === 1)
            ->setShippingAvailable(
                $db->getSingleInt(
                    'SELECT COUNT(*) AS cnt 
                          FROM tversandart
                          WHERE cLaender LIKE :iso',
                    'cnt',
                    ['iso' => '%' . $this->getISO() . '%']
                ) > 0
            );
    }

    public function getNameForLangISO(string $langISO): string
    {
        return \locale_get_display_region('sl-Latn-' . $this->getISO() . '-nedis', $langISO) ?: '';
    }

    public function isEU(): bool
    {
        return $this->getEU() === 1;
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

    public function getEU(): int
    {
        return $this->eu;
    }

    public function setEU(int $eu): self
    {
        $this->eu = $eu;

        return $this;
    }

    public function getContinent(): string
    {
        $name = $this->continent ?? '';

        return isset($_SESSION['AdminAccount']) ? \__($name) : Shop::Lang()->get($name);
    }

    public function setContinent(string $continent): self
    {
        $this->continent = $continent;

        return $this;
    }

    public function getName(?int $idx = null): string
    {
        return isset($_SESSION['AdminAccount']->language)
            ? $this->getNameForLangISO($_SESSION['AdminAccount']->language)
            : $this->names[$idx ?? Shop::getLanguageID()] ?? '';
    }

    public function setName(LanguageModel $lang): self
    {
        $this->names[$lang->getId()] = $this->getNameForLangISO($lang->getIso639());

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getNames(): array
    {
        return $this->names;
    }

    /**
     * @param array<int, string> $names
     * @return Country
     */
    public function setNames(array $names): self
    {
        $this->names = $names;

        return $this;
    }

    public function getNameDE(): string
    {
        return $this->nameDE;
    }

    public function setNameDE(string $nameDE): self
    {
        $this->nameDE = $nameDE;

        return $this;
    }

    public function getNameEN(): string
    {
        return $this->nameEN;
    }

    public function setNameEN(string $nameEN): self
    {
        $this->nameEN = $nameEN;

        return $this;
    }

    public function isShippingAvailable(): bool
    {
        return $this->shippingAvailable;
    }

    public function setShippingAvailable(bool $shippingAvailable): self
    {
        $this->shippingAvailable = $shippingAvailable;

        return $this;
    }

    public function isPermitRegistration(): bool
    {
        return $this->permitRegistration;
    }

    public function setPermitRegistration(bool $permitRegistration): self
    {
        $this->permitRegistration = $permitRegistration;

        return $this;
    }

    public function isRequireStateDefinition(): bool
    {
        return $this->requireStateDefinition;
    }

    public function setRequireStateDefinition(bool $requireStateDefinition): self
    {
        $this->requireStateDefinition = $requireStateDefinition;

        return $this;
    }

    /**
     * @return State[]
     */
    public function getStates(): array
    {
        return $this->states;
    }

    /**
     * @param State[] $states
     * @return Country
     */
    public function setStates(array $states): self
    {
        $this->states = $states;

        return $this;
    }
}
