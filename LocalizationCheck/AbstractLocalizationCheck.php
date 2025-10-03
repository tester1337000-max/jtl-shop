<?php

declare(strict_types=1);

namespace JTL\Backend\LocalizationCheck;

use Illuminate\Support\Collection;
use JTL\DB\DbInterface;
use JTL\Language\LanguageModel;

/**
 * Class AbstractLocalizationCheck
 * @package JTL\Backend\LocalizationCheck
 */
abstract class AbstractLocalizationCheck implements LocalizationCheckInterface
{
    /**
     * @var Collection<int, string>
     */
    protected Collection $activeLanguageCodes;

    /**
     * @var Collection<int, int>
     */
    protected Collection $activeLanguageIDs;

    /**
     * @var Collection<int, LanguageModel>
     */
    protected Collection $nonDefaultLanguages;

    /**
     * @param Collection<int, LanguageModel> $activeLanguages
     */
    public function __construct(protected DbInterface $db, protected Collection $activeLanguages)
    {
        $this->activeLanguageIDs   = $activeLanguages->map(fn(LanguageModel $mdl) => $mdl->getId());
        $this->activeLanguageCodes = $activeLanguages->map(fn(LanguageModel $mdl): string => $mdl->getCode());
        $this->nonDefaultLanguages = $this->activeLanguages->filter(
            fn(LanguageModel $mdl): bool => !$mdl->isShopDefault()
        );
    }

    public function getDB(): DbInterface
    {
        return $this->db;
    }

    public function setDB(DbInterface $db): void
    {
        $this->db = $db;
    }

    /**
     * @return Collection<int, LanguageModel>
     */
    public function getActiveLanguages(): Collection
    {
        return $this->activeLanguages;
    }

    /**
     * @param Collection<int, LanguageModel> $activeLanguages
     */
    public function setActiveLanguages(Collection $activeLanguages): void
    {
        $this->activeLanguages = $activeLanguages;
    }

    /**
     * @return Collection<int, int>
     */
    public function getActiveLanguageIDs(): Collection
    {
        return $this->activeLanguageIDs;
    }

    /**
     * @param Collection<int, int> $activeLanguageIDs
     */
    public function setActiveLanguageIDs(Collection $activeLanguageIDs): void
    {
        $this->activeLanguageIDs = $activeLanguageIDs;
    }

    /**
     * @return Collection<int, string>
     */
    public function getActiveLanguageCodes(): Collection
    {
        return $this->activeLanguageCodes;
    }

    /**
     * @param Collection<int, string> $activeLanguageCodes
     */
    public function setActiveLanguageCodes(Collection $activeLanguageCodes): void
    {
        $this->activeLanguageCodes = $activeLanguageCodes;
    }

    /**
     * @return Collection<int, LanguageModel>
     */
    public function getNonDefaultLanguages(): Collection
    {
        return $this->nonDefaultLanguages;
    }

    /**
     * @param Collection<int, LanguageModel> $nonDefaultLanguages
     */
    public function setNonDefaultLanguages(Collection $nonDefaultLanguages): void
    {
        $this->nonDefaultLanguages = $nonDefaultLanguages;
    }
}
