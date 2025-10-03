<?php

declare(strict_types=1);

namespace JTL\Traits;

/**
 * @package JTL\Traits
 */
trait FloatingPointTrait
{
    public function isZero(float $value): bool
    {
        return $value > -PHP_FLOAT_EPSILON && $value < PHP_FLOAT_EPSILON;
    }
}
