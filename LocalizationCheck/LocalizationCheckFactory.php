<?php

declare(strict_types=1);

namespace JTL\Backend\LocalizationCheck;

use Illuminate\Support\Collection;
use JTL\DB\DbInterface;
use JTL\Language\LanguageModel;

/**
 * Interface Localization
 * @package JTL\Backend\LocalizationCheck
 */
readonly class LocalizationCheckFactory
{
    /**
     * @param Collection<int, LanguageModel> $activeLanguages
     */
    public function __construct(private DbInterface $db, private Collection $activeLanguages)
    {
    }

    /**
     * @return class-string<LocalizationCheckInterface>[]
     */
    public function getAvailable(): array
    {
        return [
            Attributes::class,
            Categories::class,
            Characteristics::class,
            CharacteristicValues::class,
            ConfigGroups::class,
            ConfigItems::class,
            CustomerGroups::class,
            Manufacturers::class,
            Packagings::class,
            PaymentMethods::class,
            Products::class,
            ShippingFees::class,
            ShippingMethods::class,
            UnitsOfMeasurement::class,
            Uploads::class,
            Varcombi::class,
            VarcombiValues::class,
            Warehouses::class,
        ];
    }

    /**
     * @param class-string<LocalizationCheckInterface> $className
     * @return LocalizationCheckInterface|null
     */
    public function getCheckByClassName(string $className): ?LocalizationCheckInterface
    {
        if (\in_array($className, $this->getAvailable(), true)) {
            return new $className($this->db, $this->activeLanguages);
        }

        return null;
    }

    /**
     * @return LocalizationCheckInterface[]
     */
    public function getAllChecks(): array
    {
        $res = [];
        /** @var class-string<LocalizationCheckInterface> $type */
        foreach ($this->getAvailable() as $type) {
            $res[] = new $type($this->db, $this->activeLanguages);
        }

        return $res;
    }
}
