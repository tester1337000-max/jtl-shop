<?php

declare(strict_types=1);

namespace JTL\Backend\ShippingClassWizard;

use Illuminate\Support\Collection;

/**
 * Class Builder
 * @package JTL\Backend\ShippingClassWizard
 */
final class Builder
{
    /**
     * @var int[]
     */
    private array $shippingClassIds;

    /**
     * @param int[] $shippingClassIds
     */
    public function __construct(array $shippingClassIds)
    {
        $this->shippingClassIds = $shippingClassIds;
    }

    /**
     * @param Collection<int, string[]> $result
     * @return Collection<int, string>
     */
    private function makeResult(Collection $result): Collection
    {
        return $result
            ->map(static function (array $item): string {
                \sort($item);

                return \implode('-', $item);
            })->uniqueStrict()->sortBy(fn(string $value): string => $value);
    }

    /**
     * @param int[] $exclude
     * @return int[]
     */
    private function getShippingClassIds(array $exclude = []): array
    {
        return \array_diff($this->shippingClassIds, $exclude);
    }

    /**
     * @param array<mixed> $variants
     * @return array<mixed>
     */
    private function buildCombinations(array $variants): array
    {
        $variants = \array_unique($variants, \SORT_NUMERIC);
        \sort($variants);

        $res = \array_map(static fn($item): array => [$item], $variants);
        $st  = \count($variants) - 1;
        $of  = 0;
        while ($st > 0) {
            $c = \count($res);
            for ($i = 0; $i < $c - $of - 1; $i++) {
                foreach ($variants as $variant) {
                    if (!\in_array($variant, $res[$i + $of], true)) {
                        $new = \array_merge($res[$i + $of], [$variant]);
                        \sort($new);
                        if (!\in_array($new, $res, true)) {
                            $res[] = $new;
                        }
                    }
                }
            }
            $st--;
            $of = $c;
        }

        return $res;
    }

    /**
     * @param array<mixed> $merge
     * @param array<mixed> $combinations
     * @param bool         $exclusive
     * @return array<mixed>
     */
    private function mergeCombinations(array $merge, array $combinations, bool $exclusive = false): array
    {
        foreach (\array_keys($combinations) as $key) {
            $merged = \array_merge($combinations[$key], $merge);
            \sort($merged);
            $combinations[$key] = $merged;
        }

        if (!$exclusive) {
            \array_unshift($combinations, $merge);
        }

        return $combinations;
    }

    /**
     * @param array<mixed> $set
     * @return array<mixed>
     */
    private function buildAndCombinations(array $set): array
    {
        $res = [];
        $idx = [];
        $len = [];
        $st  = \count($set) - 1;

        $updateIdx = static function ($key, &$idx, $len) use (&$updateIdx) {
            if (++$idx[$key] >= $len[$key]) {
                $idx[$key] = 0;
                if ($key > 0) {
                    return $updateIdx($key - 1, $idx, $len);
                }

                return false;
            }

            return true;
        };

        foreach (\array_keys($set) as $key) {
            \sort($set[$key]);
            $idx[$key] = 0;
            $len[$key] = \count($set[$key]);
        }

        do {
            $ins = [];
            foreach ($set as $key => $item) {
                $ins[] = \is_array($item[$idx[$key]]) ? $item[$idx[$key]] : [$item[$idx[$key]]];
            }
            $res[] = \array_merge(...$ins);
        } while ($updateIdx($st, $idx, $len));

        return $res;
    }

    /**
     * @param array<mixed> $variationKeys
     * @param array<mixed> $variations
     * @return Collection<int, mixed>
     */
    private function buildAndCombinationsFromKey(array $variationKeys, array $variations): Collection
    {
        $result    = new Collection();
        $andCombis = $this->buildAndCombinations($variationKeys);

        foreach ($andCombis as $variationKey) {
            $combinations = [];
            foreach ($variationKey as $variation => $key) {
                $combinations[] = $variations[$variation][$key];
            }
            $combinations = \array_merge(...$combinations);
            sort($combinations);
            $result = $result->merge([$combinations]);
        }

        return $result;
    }

    /**
     * @return Collection<int, string>
     */
    public function combineAll(): Collection
    {
        return $this->makeResult(new Collection($this->buildCombinations($this->getShippingClassIds())));
    }

    /**
     * @param array<mixed> $definitions
     * @return Collection<int, string>
     */
    public function combineAllOr(array $definitions): Collection
    {
        $result = new Collection([]);

        foreach ($definitions as $definition) {
            $result = $result->merge(
                $this->mergeCombinations(
                    $definition,
                    $this->buildCombinations(
                        $this->getShippingClassIds($definition)
                    )
                )
            );
        }

        return $this->makeResult($result);
    }

    /**
     * @param array<mixed> $definitions
     * @return Collection<int, string>
     */
    public function combineAllAnd(array $definitions): Collection
    {
        $result    = new Collection([]);
        $andCombis = $this->buildAndCombinations($definitions);

        foreach ($andCombis as $combi) {
            $result = $result->merge(
                $this->mergeCombinations(
                    $combi,
                    $this->buildCombinations(
                        $this->getShippingClassIds($combi)
                    )
                )
            );
        }

        return $this->makeResult($result);
    }

    /**
     * @param array<mixed> $definitions
     * @return Collection<int, string>
     */
    public function combineSingleOr(array $definitions): Collection
    {
        $result     = new Collection([]);
        $keys       = \array_keys($definitions);
        $variations = $this->buildCombinations($keys);

        foreach ($variations as $variation) {
            $combinations = [];
            foreach ($variation as $key) {
                $combinations[] = $definitions[$key];
            }
            $combinations = \array_merge(...$combinations);
            \sort($combinations);
            $result = $result->merge([$combinations]);
        }

        return $this->makeResult($result);
    }

    /**
     * @param array<mixed> $definitions
     * @return Collection<int, string>
     */
    public function combineSingleAnd(array $definitions): Collection
    {
        $variations    = [];
        $variationKeys = [];

        foreach ($definitions as $definition) {
            $combination     = $this->buildCombinations($definition);
            $variationKeys[] = \array_keys($combination);
            $variations[]    = $combination;
        }

        return $this->makeResult($this->buildAndCombinationsFromKey($variationKeys, $variations));
    }

    /**
     * @param array<mixed> $definitions
     * @return Collection<int, string>
     */
    public function exclusiveOr(array $definitions): Collection
    {
        return $this->makeResult(new Collection($definitions));
    }

    /**
     * @param array<mixed> $definitions
     * @return Collection<int, string>
     */
    public function exclusiveAnd(array $definitions): Collection
    {
        $variations    = [];
        $variationKeys = [];

        foreach ($definitions as $definition) {
            $combination     = \array_map(static fn(int $item): array => [$item], $definition);
            $variationKeys[] = \array_keys($combination);
            $variations[]    = $combination;
        }

        return $this->makeResult($this->buildAndCombinationsFromKey($variationKeys, $variations));
    }

    /**
     * @param Collection<int, string> $toInvert
     * @return Collection<int, string>
     */
    public function invert(Collection $toInvert): Collection
    {
        return $this->combineAll()->diff($toInvert);
    }
}
