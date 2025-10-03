<?php

declare(strict_types=1);

namespace JTL\Catalog;

use JTL\Shop;
use stdClass;

/**
 * Class UnitsOfMeasure
 * @package JTL\Catalog
 * @see http://unitsofmeasure.org/ucum.html
 */
class UnitsOfMeasure
{
    /**
     * ucum code to print mapping table
     *
     * @var array<string, string>
     */
    public static array $UCUMcodeToPrint = [
        'm'      => 'm',
        'mm'     => 'mm',
        'cm'     => 'cm',
        'dm'     => 'dm',
        '[in_i]' => '&Prime;',
        'km'     => 'km',
        'kg'     => 'kg',
        'mg'     => 'mg',
        'g'      => 'g',
        't'      => 't',
        'm2'     => 'm<sup>2</sup>',
        'mm2'    => 'mm<sup>2</sup>',
        'cm2'    => 'cm<sup>2</sup>',
        'L'      => 'l',
        'mL'     => 'ml',
        'dL'     => 'dl',
        'cL'     => 'cl',
        'm3'     => 'm<sup>3</sup>',
        'cm3'    => 'cm<sup>3</sup>'
    ];

    /**
     * @var array<string, null|array<int, string>>
     */
    public static array $conversionTable = [
        'mm'  => null,
        'cm'  => [10 => 'mm'],
        'dm'  => [10 => 'cm'],
        'm'   => [10 => 'dm'],
        'km'  => [1000 => 'm'],
        'mg'  => null,
        'g'   => [1000 => 'mg'],
        'kg'  => [1000 => 'g'],
        't'   => [1000 => 'kg'],
        'mL'  => null,
        'cm3' => [1 => 'mL'],
        'cL'  => [10 => 'cm3'],
        'dL'  => [10 => 'cL'],
        'L'   => [10 => 'dL'],
        'm3'  => [1000 => 'L'],
        'mm2' => null,
        'cm2' => [100 => 'mm2'],
        'm2'  => [1000 => 'cm2'],
    ];

    public static function getPrintAbbreviation(?string $ucumCode): string
    {
        return self::$UCUMcodeToPrint[$ucumCode] ?? '';
    }

    /**
     * @return array<int, object{kMassEinheit: int, cCode: string}&stdClass>
     */
    public static function getUnits(): array
    {
        /** @var array<int, object{kMassEinheit: int, cCode: string}&stdClass> $units */
        static $units = [];
        if (\count($units) > 0) {
            return $units;
        }
        $data = Shop::Container()->getDB()->getObjects(
            "SELECT kMassEinheit, cCode
                FROM tmasseinheit
                WHERE cCode IN ('" . \implode("', '", \array_keys(self::$UCUMcodeToPrint)) . "')"
        );
        foreach ($data as $unit) {
            $unit->kMassEinheit         = (int)$unit->kMassEinheit;
            $units[$unit->kMassEinheit] = (object)['kMassEinheit' => $unit->kMassEinheit, 'cCode' => $unit->cCode];
        }

        return $units;
    }

    public static function getUnit(int $id): ?stdClass
    {
        $units = self::getUnits();

        return $units[$id] ?? null;
    }

    /**
     * @return int|null
     */
    private static function iGetConversionFaktor(string $unitFrom, string $unitTo): mixed
    {
        $result = null;
        if (isset(self::$conversionTable[$unitFrom])) {
            $result = \key(self::$conversionTable[$unitFrom]);
            $nextTo = \current(self::$conversionTable[$unitFrom]);
            if ($nextTo !== false && $nextTo !== $unitTo) {
                $factor = self::iGetConversionFaktor($nextTo, $unitTo);
                $result = $factor === null ? null : $result * $factor;
            }
        }

        return $result;
    }

    public static function getConversionFaktor(string $unitFrom, string $unitTo): mixed
    {
        $result = self::iGetConversionFaktor($unitFrom, $unitTo);
        if ($result === null) {
            $result = self::iGetConversionFaktor($unitTo, $unitFrom);

            return $result === null ? null : 1 / $result;
        }

        return $result;
    }
}
