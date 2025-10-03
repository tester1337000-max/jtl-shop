<?php

declare(strict_types=1);

namespace JTL;

/**
 * Class PlausiTrennzeichen
 * @package JTL
 */
class PlausiTrennzeichen extends Plausi
{
    /**
     * @inheritdoc
     */
    public function doPlausi(?string $type = null, bool $update = false): bool
    {
        if (\count($this->xPostVar_arr) === 0) {
            return false;
        }
        foreach ([\JTL_SEPARATOR_WEIGHT, \JTL_SEPARATOR_LENGTH, \JTL_SEPARATOR_AMOUNT] as $unit) {
            // Anzahl Dezimalstellen
            $idx = 'nDezimal_' . $unit;
            if (!isset($this->xPostVar_arr[$idx])) {
                $this->xPlausiVar_arr[$idx] = 1;
            }
            // Dezimaltrennzeichen
            $idx = 'cDezZeichen_' . $unit;
            if (!isset($this->xPostVar_arr[$idx]) || \mb_strlen($this->xPostVar_arr[$idx]) === 0) {
                $this->xPlausiVar_arr[$idx] = 1;
            }
            // Tausendertrennzeichen
            $idx = 'cTausenderZeichen_' . $unit;
            if (!isset($this->xPostVar_arr[$idx])) {
                $this->xPlausiVar_arr[$idx] = 1;
            }
        }

        return false;
    }
}
