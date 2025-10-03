<?php

declare(strict_types=1);

namespace JTL;

/**
 * Class Plausi
 * @package JTL
 */
class Plausi
{
    /**
     * @var array<mixed>
     */
    protected array $xPostVar_arr = [];

    /**
     * @var array<mixed>
     */
    protected array $xPlausiVar_arr = [];

    /**
     * @return array<mixed>
     */
    public function getPostVar(): array
    {
        return $this->xPostVar_arr;
    }

    /**
     * @return array<mixed>
     */
    public function getPlausiVar(): array
    {
        return $this->xPlausiVar_arr;
    }

    /**
     * @param array<mixed>      $variables
     * @param array<mixed>|null $hasHTML
     */
    public function setPostVar(array $variables, ?array $hasHTML = null, bool $toEntities = false): bool
    {
        if (\count($variables) === 0) {
            return false;
        }
        if (\is_array($hasHTML)) {
            $excludeKeys = \array_fill_keys($hasHTML, 1);
            $filter      = \array_diff_key($variables, $excludeKeys);
            $excludes    = \array_intersect_key($variables, $excludeKeys);
            if ($toEntities) {
                \array_map(static fn($item): string => \htmlentities($item), $excludes);
            }
            $this->xPostVar_arr = \array_merge($variables, $filter, $excludes);
        } else {
            $this->xPostVar_arr = $variables;
        }

        return true;
    }

    /**
     * @param array<mixed> $variables
     */
    public function setPlausiVar(array $variables): bool
    {
        if (\count($variables) === 0) {
            return false;
        }
        $this->xPlausiVar_arr = $variables;

        return true;
    }

    /**
     * @param string|null $type
     * @param bool        $update
     * @return bool
     */
    public function doPlausi(?string $type = null, bool $update = false): bool
    {
        return false;
    }
}
