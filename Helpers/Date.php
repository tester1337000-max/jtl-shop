<?php

declare(strict_types=1);

namespace JTL\Helpers;

use DateInterval;
use DateTime;
use Exception;
use JTL\Shop;

/**
 * Class Date
 * @package JTL\Helpers
 * @since 5.0.0
 */
class Date
{
    /**
     * @param DateTime|string|int|mixed $date
     * @since 5.0.0
     */
    public static function dateAddWeekday(mixed $date, int|string $weekdays): DateTime
    {
        try {
            if (\is_string($date)) {
                $resDate = new DateTime($date);
            } elseif (\is_numeric($date)) {
                $resDate = new DateTime();
                $resDate->setTimestamp((int)$date);
            } elseif (\is_object($date) && \is_a($date, DateTime::class)) {
                $resDate = new DateTime($date->format(DateTime::ATOM));
            } else {
                $resDate = new DateTime();
            }
        } catch (Exception $e) {
            Shop::Container()->getLogService()->error($e->getMessage());
            $resDate = new DateTime();
        }
        if ((int)$resDate->format('w') === 0) {
            // Add one weekday if startdate is on sunday
            $resDate->add(DateInterval::createFromDateString('1 weekday'));
        }
        // Add $weekdays as normal days
        $resDate->add(DateInterval::createFromDateString($weekdays . ' day'));
        if ((int)$resDate->format('w') === 0) {
            // Add one weekday if enddate is on sunday
            $resDate->add(DateInterval::createFromDateString('1 weekday'));
        }

        return $resDate;
    }

    /**
     * @param string $dateString - YYYY-MM-DD HH:MM:SS, YYYY-MM-DD, now oder now()
     * @return array<string, string>
     * @former gibDatumTeile()
     * @since 5.0.0
     */
    public static function getDateParts(string $dateString): array
    {
        $parts = [];
        if (\mb_strlen($dateString) > 0) {
            if (\mb_convert_case($dateString, \MB_CASE_LOWER) === 'now()') {
                $dateString = 'now';
            }
            try {
                $date              = new DateTime($dateString);
                $parts['cDatum']   = $date->format('Y-m-d');
                $parts['cZeit']    = $date->format('H:i:s');
                $parts['cJahr']    = $date->format('Y');
                $parts['cMonat']   = $date->format('m');
                $parts['cTag']     = $date->format('d');
                $parts['cStunde']  = $date->format('H');
                $parts['cMinute']  = $date->format('i');
                $parts['cSekunde'] = $date->format('s');
            } catch (Exception) {
            }
        }

        return $parts;
    }

    /**
     * localize datetime to DE
     */
    public static function localize(string $input, bool $dateOnly = false): string
    {
        return (new DateTime($input))->format($dateOnly ? 'd.m.Y' : 'd.m.Y H:i');
    }

    public static function convertDateToMysqlStandard(?string $date): string
    {
        if ($date === null) {
            $convertedDate = '_DBNULL_';
        } elseif (\preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $date)) {
            $convertedDate = self::safeDateFormat($date, 'Y-m-d', '_DBNULL_', 'd.m.Y');
        } elseif (\preg_match('/^\d{4}-\d{2}-(\d{2})$/', $date)) {
            $convertedDate = $date;
        } else {
            $convertedDate = '_DBNULL_';
        }

        return $convertedDate;
    }

    /**
     * Ermittelt den Wochenstart und das Wochenende
     * eines Datums im Format YYYY-MM-DD
     * und gibt ein Array mit Start als Timestamp zurück
     * Array[0] = Start
     * Array[1] = Ende
     * @return array<int, int>
     * @former ermittleDatumWoche()
     */
    public static function getWeekStartAndEnd(string $dateString): array
    {
        if (\mb_strlen($dateString) < 1) {
            return [];
        }
        [$year, $month, $day] = \explode('-', $dateString);
        // So = 0, SA = 6
        $weekDay = (int)\date('w', \mktime(0, 0, 0, (int)$month, (int)$day, (int)$year) ?: null);
        if ($weekDay === 0) { // Woche soll Montag starten - also So = 6, Mo = 0
            $weekDay = 6;
        } else {
            $weekDay--;
        }
        $dayOld = (int)$day;
        $day    = $dayOld - $weekDay;
        $month  = (int)$month;
        $year   = (int)$year;
        if ($day <= 0) {
            --$month;
            if ($month === 0) {
                $month = 12;
                ++$year;
            }
            $day = (int)\date('t', \mktime(0, 0, 0, $month, 1, $year) ?: null) - $weekDay + $dayOld;
        }
        $stampStart   = \mktime(0, 0, 0, $month, $day, $year);
        $daysPerMonth = (int)\date('t', \mktime(0, 0, 0, $month, 1, $year) ?: null);
        $day          += 6;
        if ($day > $daysPerMonth) {
            $day -= $daysPerMonth;
            ++$month;
            if ($month > 12) {
                $month = 1;
                ++$year;
            }
        }

        return [$stampStart, \mktime(23, 59, 59, $month, $day, $year)];
    }

    /**
     * @since 5.2.0
     * @former firstDayOfMonth()
     */
    public static function getFirstDayOfMonth(int $month = -1, int $year = -1): false|int
    {
        return \mktime(
            0,
            0,
            0,
            $month > -1 ? $month : (int)\date('m'),
            1,
            $year > -1 ? $year : (int)\date('Y')
        );
    }

    /**
     * @since 5.2.0
     * @former lastDayOfMonth()
     */
    public static function getLastDayOfMonth(int $month = -1, int $year = -1): false|int
    {
        return \mktime(
            23,
            59,
            59,
            $month > -1 ? $month : (int)\date('m'),
            (int)\date('t', self::getFirstDayOfMonth($month, $year) ?: null),
            $year > -1 ? $year : (int)\date('Y')
        );
    }

    public static function getMonthName(int|string $month): string
    {
        return \date('F', \mktime(0, 0, 0, (int)$month, 1, 2000) ?: null);
    }

    public static function safeDateFormat(
        ?string $source,
        string $format = 'd.m.Y H:i',
        string $default = '',
        ?string $fromFormat = null
    ): string {
        if ($source === null) {
            return $default;
        }
        try {
            $dateTime = $fromFormat === null
                ? new DateTime($source)
                : DateTime::createFromFormat($fromFormat, $source);
            if ($dateTime === false) {
                throw new \ValueError();
            }

            return $dateTime->format($format);
        } catch (\ValueError) {
            return $default;
        }
    }
}
