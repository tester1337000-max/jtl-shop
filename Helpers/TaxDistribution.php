<?php

declare(strict_types=1);

namespace JTL\Helpers;

use InvalidArgumentException;
use JTL\Cart\CartItem;

readonly class TaxDistribution
{
    /**
     * @param int[]   $taxRateIDs
     * @param float[] $taxRates
     * @param float[] $netDistribution
     * @param float[] $grossDistribution
     */
    public function __construct(
        public array $taxRateIDs,
        public array $taxRates,
        public array $netDistribution,
        public array $grossDistribution,
    ) {
        $lastOffset    = (int)\array_key_last($taxRateIDs);
        $allSameLength = (
                $lastOffset
                + (int)\array_key_last($taxRates)
                + (int)\array_key_last($netDistribution)
                + (int)\array_key_last($grossDistribution)
            ) / 4;
        if ($allSameLength !== $lastOffset) {
            throw new InvalidArgumentException('All arrays must have the same length');
        }
    }

    /**
     * @param CartItem[] $cartItems
     * @param int[]      $allowedItemTypes
     * @return self
     */
    public static function initFromCartItems(
        array $cartItems,
        array $allowedItemTypes = [\C_WARENKORBPOS_TYP_ARTIKEL],
        string $countryISO = ''
    ): self {
        $taxRateIDs        = [];
        $taxRates          = [];
        $netDistribution   = [];
        $grossDistribution = [];
        $totalNetValue     = 0.0;
        $totalGrossValue   = 0.0;
        foreach ($cartItems as $cartItem) {
            if (\in_array($cartItem->nPosTyp, $allowedItemTypes, true) === false) {
                continue;
            }
            $taxRateID = $cartItem->kSteuerklasse;
            $taxRate   = CartItem::getTaxRate($cartItem, $countryISO);
            $netValue  = (float)$cartItem->fPreis * (float)$cartItem->nAnzahl;
            if ($netValue <= 0) {
                continue;
            }
            $grossValue                    = $netValue * (1 + ($taxRate / 100));
            $totalNetValue                 += $netValue;
            $totalGrossValue               += $grossValue;
            $taxRateIDs[$taxRateID]        = $taxRateID;
            $taxRates[$taxRateID]          = $taxRate;
            $netDistribution[$taxRateID]   = ($netDistribution[$taxRateID] ?? 0.0) + $netValue;
            $grossDistribution[$taxRateID] = ($grossDistribution[$taxRateID] ?? 0.0) + $grossValue;
        }
        foreach ($taxRateIDs as $taxRateID) {
            $netDistribution[$taxRateID]   = $netDistribution[$taxRateID] / $totalNetValue * 100;
            $grossDistribution[$taxRateID] = $grossDistribution[$taxRateID] / $totalGrossValue * 100;
        }

        return new self($taxRateIDs, $taxRates, $netDistribution, $grossDistribution);
    }

    public function calculateNet(float $grossPrice, string $taxationMethod = 'HS'): float
    {
        return $this->convertBetweenNetAndGross($grossPrice, false, $taxationMethod);
    }

    public function calculateGross(float $netPrice, string $taxationMethod = 'HS'): float
    {
        return $this->convertBetweenNetAndGross($netPrice, true, $taxationMethod);
    }

    public function getTaxRateID(string $taxationMethod): int
    {
        return (int)match ($taxationMethod) {
            'US'    => \array_search(
                $this->netDistribution !== []
                    ? \max($this->netDistribution)
                    : 0,
                $this->netDistribution,
                true,
            ),
            'HS'    => \array_search(
                $this->taxRates !== []
                    ? \max($this->taxRates)
                    : 0,
                $this->taxRates,
                true,
            ),
            default => 0,
        };
    }

    /**
     * @param float[] $distribution
     * @return object{taxRate: float, share: float}[]
     */
    private function getTaxesDependingOnMethod(string $taxationMethod, array $distribution): array
    {
        if ($this->taxRates === []) {
            return [(object)[
                'taxRate' => (float)Tax::getSalesTax(1),
                'share'   => 100.0,
            ]];
        }
        return match ($taxationMethod) {
            'US'    => $this->predominantTaxRate($distribution),
            'HS'    => $this->highestTaxRate(),
            default => $this->proportionalTaxRates($distribution),
        };
    }

    private function convertBetweenNetAndGross(float $price, bool $toGross, string $taxationMethod): float
    {
        $result       = 0.0;
        $distribution = $this->getTaxesDependingOnMethod(
            $taxationMethod,
            $toGross ? $this->netDistribution : $this->grossDistribution
        );
        foreach ($distribution as $distributionItem) {
            /** @var object{taxRate: float, share: float} $distributionItem */
            $result += $toGross
                ? (($distributionItem->share * $price) / 100) * (1 + ($distributionItem->taxRate / 100))
                : (($distributionItem->share * $price) / 100) / (1 + ($distributionItem->taxRate / 100));
        }

        return $result;
    }

    /**
     * @param float[] $distribution
     * @return object{taxRate: float, share: float}[]
     */
    private function predominantTaxRate(array $distribution): array
    {
        if ($distribution === []) {
            return [(object)[
                'taxRate' => 0.0,
                'share'   => 100.0,
            ]];
        }
        $result            = [];
        $predominantRateID = \array_search(
            \max($distribution),
            $distribution,
            true,
        );
        foreach ($this->taxRates as $taxRateID => $taxRate) {
            if ($taxRateID !== $predominantRateID) {
                continue;
            }
            $result[] = (object)[
                'taxRate' => $taxRate,
                'share'   => 100.0,
            ];
        }

        return $result;
    }

    /**
     * @return object{taxRate: float, share: float}[]
     */
    private function highestTaxRate(): array
    {
        if ($this->taxRates === []) {
            return [(object)[
                'taxRate' => 0.0,
                'share'   => 100.0,
            ]];
        }
        $result      = [];
        $highestRate = \max($this->taxRates);
        foreach ($this->taxRates as $taxRate) {
            if ($taxRate !== $highestRate) {
                continue;
            }
            $result[] = (object)[
                'taxRate' => $taxRate,
                'share'   => 100.0,
            ];
        }

        return $result;
    }

    /**
     * @param float[] $distribution
     * @return object{taxRate: float, share: float}[]
     */
    private function proportionalTaxRates(array $distribution): array
    {
        $result = [];
        foreach ($this->taxRates as $taxRateID => $taxRate) {
            $result[] = (object)[
                'taxRate' => $taxRate,
                'share'   => $distribution[$taxRateID],
            ];
        }

        return $result;
    }
}
