<?php

declare(strict_types=1);

namespace JTL\Model;

use DateInterval;
use DateTime;
use Exception;

/**
 * Class ModelHelper
 * @package App\Models
 */
final class ModelHelper
{
    private static function formatDateTime(DateTime|string|null $value, string $format = 'Y-m-d H:i:s'): ?string
    {
        if ($value === null) {
            return null;
        }
        if (\is_string($value)) {
            return self::fromStrToDateTime($value)?->format($format);
        }
        if (\is_a($value, DateTime::class)) {
            return $value->format($format);
        }

        return null;
    }

    public static function fromDateTimeToStr(DateTime|string|null $value): ?string
    {
        return self::formatDateTime($value);
    }

    public static function fromStrToDateTime(
        DateTime|string|null $value,
        DateTime|string|null $default = null
    ): ?DateTime {
        if (($value === null && $default === null) || \is_a($value, DateTime::class)) {
            return $value;
        }
        if (\is_string($value)) {
            try {
                return new DateTime(\str_replace('now()', 'now', $value));
            } catch (Exception) {
                return self::fromStrToDateTime($default);
            }
        }

        return self::fromStrToDateTime($default);
    }

    public static function fromTimeToStr(DateInterval|string|null $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (\is_string($value)) {
            return self::fromStrToTime($value)?->format('%H:%I:%S');
        }
        if (\is_a($value, DateInterval::class)) {
            return $value->format('%H:%I:%S');
        }

        return null;
    }

    public static function fromStrToTime(
        DateInterval|string|null $value,
        DateInterval|string|null $default = null
    ): ?DateInterval {
        if (!isset($value) && !isset($default)) {
            return null;
        }
        if (\is_a($value, DateInterval::class)) {
            return $value;
        }
        if (!\is_string($value)) {
            return self::fromStrToTime($default);
        }
        try {
            $splits = \explode(':', $value, 3);

            return match (\count($splits)) {
                0       => DateInterval::createFromDateString($value),
                1       => new DateInterval('PT' . (int)$splits[0] . 'H'),
                2       => new DateInterval('PT' . (int)$splits[0] . 'H' . (int)$splits[1] . 'M'),
                3       => new DateInterval(
                    'PT' . (int)$splits[0] . 'H' . (int)$splits[1] . 'M' . (int)$splits[2] . 'S'
                ),
                default => self::fromStrToTime($default),
            };
        } catch (Exception) {
            return self::fromStrToTime($default);
        }
    }

    public static function fromDateToStr(DateTime|string|null $value): ?string
    {
        return self::formatDateTime($value, 'Y-m-d');
    }

    public static function fromStrToDate(DateTime|string|null $value, DateTime|string|null $default = null): ?DateTime
    {
        $dateTime = self::fromStrToDateTime($value, $default);
        $dateTime?->setTime(0, 0);

        return $dateTime;
    }

    public static function fromTimestampToStr(DateTime|string|null $value): ?string
    {
        return self::formatDateTime($value, 'Y-m-d H:i:s.u');
    }

    public static function fromStrToTimestamp(
        DateTime|string|null $value,
        DateTime|string|null $default = null
    ): ?DateTime {
        return self::fromStrToDateTime($value, $default);
    }

    public static function fromCharToBool(?string $value, ?bool $default = null): ?bool
    {
        if (\is_string($value)) {
            return \in_array(\strtoupper($value), ['Y', 'J', 'TRUE']);
        }

        return $default;
    }

    public static function fromBoolToChar(bool $value): string
    {
        return $value ? 'Y' : 'N';
    }

    public static function fromIntToBool(int|string $value, bool $default = false): bool
    {
        if (\is_numeric($value)) {
            return $value > 0;
        }

        return $default;
    }

    public static function fromBoolToInt(bool $value): int
    {
        return $value ? 1 : 0;
    }
}
