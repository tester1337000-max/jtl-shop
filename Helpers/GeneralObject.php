<?php

declare(strict_types=1);

namespace JTL\Helpers;

use stdClass;

/**
 * Class GeneralObject
 * @package JTL\Helpers
 * @since 5.0.0
 */
class GeneralObject
{
    /**
     * @param int|string $index
     * @param mixed      $source
     * @return bool
     */
    public static function isCountable(mixed $index, mixed $source = null): bool
    {
        if (\is_object($source)) {
            return isset($source->$index) && \is_countable($source->$index);
        }

        return $source === null
            ? \is_countable($index)
            : isset($source[$index]) && \is_countable($source[$index]);
    }

    /**
     * @param string|int|array<mixed>|\Countable  $index
     * @param \Countable|array<mixed>|object|null $source
     * @return bool
     */
    public static function hasCount(mixed $index, mixed $source = null): bool
    {
        if (\is_object($source)) {
            return isset($source->$index) && \is_countable($source->$index) && \count($source->$index) > 0;
        }

        return $source === null
            ? \is_countable($index) && \count($index) > 0
            : isset($source[$index]) && \is_countable($source[$index]) && \count($source[$index]) > 0;
    }

    /**
     * @param array<mixed> $data
     * @former objectSort()
     * @since 5.0.0
     */
    public static function sortBy(array &$data, string $key, bool $toLower = false): void
    {
        $dataCount = \count($data);
        for ($i = $dataCount - 1; $i >= 0; $i--) {
            $swapped = false;
            for ($j = 0; $j < $i; $j++) {
                $dataJ  = $data[$j]->$key;
                $dataJ1 = $data[$j + 1]->$key;
                if ($toLower) {
                    $dataJ  = \mb_convert_case($dataJ, \MB_CASE_LOWER);
                    $dataJ1 = \mb_convert_case($dataJ1, \MB_CASE_LOWER);
                }
                if ($dataJ > $dataJ1) {
                    $tmp          = $data[$j];
                    $data[$j]     = $data[$j + 1];
                    $data[$j + 1] = $tmp;
                    $swapped      = true;
                }
            }
            if (!$swapped) {
                return;
            }
        }
    }

    /**
     * @param object|mixed $originalObj
     * @return ($originalObj is object ? stdClass : mixed)
     * @former kopiereMembers()
     * @since 5.0.0
     */
    public static function copyMembers(mixed $originalObj): mixed
    {
        if (!\is_object($originalObj)) {
            return $originalObj;
        }
        $obj = new stdClass();
        foreach (\array_keys(\get_object_vars($originalObj)) as $member) {
            $obj->$member = $originalObj->$member;
        }

        return $obj;
    }

    /**
     * @since 5.0.0
     */
    public static function memberCopy(object $src, ?object &$dest): void
    {
        if ($dest === null) {
            $dest = new stdClass();
        }
        foreach (\array_keys(\get_object_vars($src)) as $key) {
            if (!\is_object($src->$key) && !\is_array($src->$key)) {
                $dest->$key = $src->$key;
            }
        }
    }

    /**
     * @template T
     * @param T $object
     * @return T
     */
    public static function deepCopy($object)
    {
        return \unserialize(\serialize($object));
    }
}
