<?php

declare(strict_types=1);

namespace JTL\VerificationVAT;

use DateTime;

/**
 * Class VATCheckDownSlots
 * @package JTL\VerificationVAT
 */
class VATCheckDownSlots
{
    /**
     * @var array<string, array<int, array<int, string>>>
     * array of "down-time-slots" of the VIES-system of all member-countries
     * MODIFY ONLY THIS ARRAY TO COVER NEW CIRCUMSTANCES!
     *
     * original source:
     * http://ec.europa.eu/taxation_customs/vies/help.html
     *
     */
    private static array $downTimeSlots = [
        //
        // array-item example:
        //
        // 'country' => [
        //       ['WEEKDAY', 'START', 'ENDING']  // means "one day a week, from start-time to end-time"
        //       [       '', 'START', 'ENDING']  // means "all days a week, from start-time to end-time"
        //     , [...]
        // ]

        // Unavailable almost daily around 06:00 AM for a few minutes (Oesterreich)
        'AT' => [
            ['', '05:59', '06:15']
        ],

        // Available 24/7 (Belgien)
        'BE' => [],

        // Unknown (Bulgarien)
        'BG' => [],

        // Unknown (Kroatien)
        'HR' => [],

        // Available 24/7 (Zypern)
        'CY' => [],

        // Unavailable everyday around 07:00 AM for about 20 minutes (Tschechische Republik)
        'CZ' => [
            ['', '07:00', '07:20']
        ],

        // Available from 05:00 AM to 11:00 PM (Deutschland)
        'DE' => [
            ['', '23:00', '05:00']
        ],

        // Available 24/7 (Daenemark)
        'DK' => [],

        // Available 24/7 (Estland)
        'EE' => [],

        // Available 24/7 (Griechenland)
        'EL' => [],

        // Unavailable daily around 11:00 PM for a few minutes (Spanien)
        'ES' => [],

        // Unavailable every Sunday between 05:40 AM and 05:50 AM (Finnland)
        'FI' => [
            ['Sun', '05:40', '05:50']
        ],

        // Unavailable almost everyday between 01:30 AM and 01:40 AM (Frankreich)
        'FR' => [
            ['', '01:30', '01:40']
        ],

        // Unavailable every Saturday from 07:30 AM to 10:30 AM
        // and almost daily from around 04:30 AM to 04:40 AM (Vereinigtes Königreich)
        'GB' => [
            ['Sat', '07:30', '10:30'],
            ['', '04:30', '04:40']
        ],

        // Available 24/7 (Ungarn)
        'HU' => [],

        // Unavailable on Sunday nights for maximum 2 hours (Irland)
        'IE' => [
            ['Sun', '', '']
        ],

        // Unavailable every Monday to Saturday from 08:00 PM for 30 to 60 minutes (Italien)
        'IT' => [
            ['Sun', '08:00', '09:00']
        ],

        // Available 24/7 (Litauen)
        'LT' => [],

        // Available 24/7 (Luxemburg)
        'LU' => [],

        // Available 24/7 (Lettland)
        'LV' => [],

        // Unavailable every Thursday from 07:00 AM to 07:30 AM (Malta)
        'MT' => [
            ['Thu', '07:00', '7:30']
        ],

        // Unavailable every weekend from Saturday 09:50 PM to Sunday 09:40 PM (Niederlande)
        'NL' => [
            ['Sat', '09:50', '00:00'],
            ['Sun', '00:00', '09:40']
        ],

        // Available 24/7 (Polen)
        'PL' => [],

        // Unavailable every Friday from around 23:30 for about 30 minutes or more (Portugal)
        'PT' => [
            ['Fri', '23:30', '00:00']
        ],

        // Unavailable almost every weekend from Saturday 09:50 PM to Sunday 09:50 PM (Rumänien)
        'RO' => [
            ['Sat', '09:50', '00:00'],
            ['Sun', '00:00', '09:50']
        ],

        // Available 24/7 (Schweden)
        'SE' => [],

        // Available 24/7 (Slowakei)
        'SK' => []
    ];

    private DateTime $now;

    private string $downInfo = '';

    public const WEEKDAY = 0;
    public const START   = 1;
    public const ENDING  = 2;

    public function __construct()
    {
        $this->now = new DateTime();
    }

    /**
     * return a informational string, which tells the user why the
     * VAT-check is currently not possible and with which time-slot he has to calculate.
     */
    public function getDownInfo(): string
    {
        return $this->downInfo;
    }

    public function isDown(string $countryCode): bool
    {
        if (!isset(self::$downTimeSlots[$countryCode])) {
            // at the moment, we skip unknown countries (use string-parsing only)
            return false;
        }

        foreach (self::$downTimeSlots[$countryCode] as $countryDownTimes) {
            // if no weekday was given (which means "every weekday"),
            // we replace the weekday in the check-array with the current weekday here
            if ($countryDownTimes[self::WEEKDAY] === '') {
                $countryDownTimes[self::WEEKDAY] = $this->now->format('D');
            }

            $startTime = DateTime::createFromFormat(
                'D:H:i',
                $countryDownTimes[self::WEEKDAY] . ':' . $countryDownTimes[self::START]
            );
            $endTime   = DateTime::createFromFormat(
                'D:H:i',
                $countryDownTimes[self::WEEKDAY] . ':' . $countryDownTimes[self::ENDING]
            );

            if ($endTime !== false && $startTime <= $this->now && $this->now <= $endTime) {
                // inform the user about this event
                $this->downInfo = $endTime->format('H:i');
                // the VAT-service of this country is down till this time

                // if we see ANY VALID DOWNTIME, we go back with TRUE (what means "service is DOWN NOW")
                return true;
            }
        }
        // the service is not down. all is fine to proceed normally.
        return false;
    }
}
