<?php

declare(strict_types=1);

namespace JTL\VerificationVAT;

/**
 * class VATCheckVatParser
 */
class VATCheckVatParser
{
    /**
     * @var array<string, string[]>
     * pattern of the country-specitfic VAT-IDs
     * MODIFY ONLY THIS ARRAY TO COVER NEW CIRCUMSTANCES!
     *
     * original source:
     * http://ec.europa.eu/taxation_customs/vies/faq.html
     *
     * additional:
     * (http://www.die-mehrwertsteuer.de/de/aufbau-umsatzsteuer-identifikationsnummern-in-der-eu.html)
     *
     * pattern-modifiers:
     * "XX" - the first two letters are the country-code (e.g. "ES" for Spain)
     * "9"  - represents "any integer digit"
     * "X"  - any other letter is a fixed given letter and has to match
     * " "  - spaces has to match too - but can't handled by the VIES-system, so we have to left out them here
     * "_"  - wildcard for any character
     */
    private static array $countryPattern = [
        // AT-Oesterreich                ATU99999999          1 Block mit 9 Ziffern    (comment: 8 !?)
        'AT' => ['ATU99999999'],         // example: ATU48075808(ok)

        // BE-Belgien                    BE0999999999         1 Block mit 10 Ziffern
        'BE' => ['BE0999999999'],        // example: BE0428759497(ok)

        // BG-Bulgarien                  BG999999999 oder
        //                               BG9999999999         1 Block mit 9 Ziffern oder 1 Block mit 10 Ziffern
        'BG' => [
            'BG999999999',              // example: BG175074752(ok)
            'BG9999999999'
        ],

        // CY-Zypern                     CY99999999L          1 Block mit 9 Ziffern    (comment: 8 with 1 char!?)
        'CY' => ['CY99999999_'],         // example: CY10259033P(ok)

        // CZ-Tschechische Republik      CZ99999999 oder
        //                               CZ999999999 oder
        //                               CZ9999999999         1 Block mit 8, 9 oder 10 Ziffern
        'CZ' => [
            'CZ99999999',                // example: CZ25123891(ok)
            'CZ999999999',
            'CZ9999999999'               // example: CZ7103192745(ok)
        ],

        // DE-Deutschland                DE999999999          1 Block mit 9 Ziffern
        'DE' => ['DE999999999'],         // example: DE122779245(ok)

        // DK-Dänemark                   DK99 99 99 99        4 Blöcke mit 2 Ziffern
        //, 'DK' => ['DK99 99 99 99']
        // modification in place of original documentation, because the VIES can not handle spaces
        'DK' => ['DK99999999'],          // example: DK13585628(ok)

        // EE-Estland                    EE999999999          1 Block mit 9 Ziffern
        'EE' => ['EE999999999'],         // example: EE100594102(ok)

        // EL-Griechenland               EL999999999          1 Block mit 9 Ziffern
        'EL' => ['EL999999999'],         // example: EL094259216(ok)

        // ES-Spanien                    ESX9999999X          1 Block mit 9 Ziffern    (comment: 8 with 1 char!?)
        'ES' => ['ES_9999999_'],         // example: ESX2482300W(ok), ESB58378431(ok)

        // FI-Finnland                   FI99999999           1 Block mit 8 Ziffern
        'FI' => ['FI99999999'],          // example: FI20774740(ok)

        // FR-Frankreich                 FRXX 999999999       1 Block mit 2 Ziffern und 1 Block mit 9 Ziffern
        //'FR' => ['FRXX 999999999'],
        // modification in place of original documentation, because the VIES can not handle spaces
        'FR' => ['FR__999999999'],       // example: FR40303265045(ok), FRK7399859412(ok)

        // HR-Kroatien                   HR99999999999        1 Block mit 11 Ziffern
        'HR' => ['HR99999999999'],       // example: HR33392005961(ok)

        // HU-Ungarn                     HU99999999           1 Block mit 8 Ziffern
        'HU' => ['HU99999999'],

        // IE-Irland                     IE9S99999L oder
        //                               IE9999999WI          1 Block mit 8 Ziffern oder 1 Block mit 9 Ziffern
        //, 'IE' => [
        //      'IE9S99999L'
        //    , 'IE9999999WI'
        //]
        // modification in place of original EU-documentation
        'IE' => [
            'IE9_99999_',                // example: IE6433435F(ok), IE8D79739I(ok)
            'IE9_99999__'                // example: IE3333510LH(ok)
        ],

        // NI-Nordirland
        // not yet MIAS-support - state at: 2021-01-19
        //
        // northern ireland stays a member of the EU
        // VAT numbers are "not fully known" / "not yet confirmed"
        /*
        'XI' => [
            // maybe they use numbers like GB (not confirmed)
            'XI999999999',  // not yet confirmed (officially)
            'XI9999999',    // old numbers before 2013 (old format) for Ireland
            'XI9999999_A',  // new numbers for "individuals"
            'XI9999999_H'   // new numbers for "non-individuals"

            // possible other way (completely like GB):
        'XI' => [
            'XI999999999',
            'XI999999999999',
            'XIGD999',
            'XIHA999'
        ],
        */

        // IT-Italien                    IT99999999999        1 Block mit 11 Ziffern
        'IT' => ['IT99999999999'],      // example: IT00743110157(ok)

        // LT-Litauen                    LT999999999 oder
        //                               LT999999999999       1 Block mit 9 Ziffern oder 1 Block mit 12 Ziffern
        'LT' => [
            'LT999999999',               // example: LT119511515(ok)
            'LT999999999999'             // example: LT100001919017(ok)
        ],

        // LU-Luxemburg                  LU99999999           1 Block mit 8 Ziffern
        'LU' => ['LU99999999'],          // example: LU15027442(ok)

        // LV-Lettland                   LV99999999999        1 Block mit 11 Ziffern
        'LV' => ['LV99999999999'],       // example: LV40003737497(ok)

        // MT-Malta                      MT99999999           1 Block mit 8 Ziffern
        'MT' => ['MT99999999'],          // example: MT10047516(ok)

        // NL-Niederlande                NL999999999B99       1 Block mit 12 Ziffern
        'NL' => ['NL999999999B99'],      // example: NL004495445B01(ok)

        // PL-Polen                      PL9999999999         1 Block mit 10 Ziffern
        'PL' => ['PL9999999999'],        // example: PL8290001028(ok)

        // PT-Portugal                   PT999999999          1 Block mit 9 Ziffern
        'PT' => ['PT999999999'],         // example: PT501964843(ok)

        // RO-Rumänien                   RO999999999          1 Block mit mindestens 2 und höchstens 10 Ziffern
        'RO' => [
            'RO9999999999',
            'RO999999999',
            'RO99999999',
            'RO9999999',
            'RO999999',
            'RO99999',
            'RO9999',
            'RO999',
            'RO99'                       // example: RO27079589(ok), RO33315358(ok)
        ],

        // SE-Schweden                   SE999999999999       1 Block mit 12 Ziffern
        'SE' => ['SE999999999901'],      // example: SE556857280301(ok), SE556789180801(ok)

        // SI-Slowenien                  SI99999999           1 Block mit 8 Ziffern
        'SI' => ['SI99999999'],          // example: SI50223054(ok)

        // SK-Slowakei                   SK9999999999         1 Block mit 10 Ziffern
        'SK' => ['SK9999999999']         // example: SK7120000019(ok), SK2021254631(ok)
    ];

    /**
     * @var array<int, string>
     */
    private array $idParts = [];

    private int $errorCode = 0;

    private string|int $errorInfo = '';

    private int $errorPos = 0;

    public function __construct(private readonly string $vatID)
    {
    }

    /**
     * parses the VAT-ID-string.
     * returns the position of a possible check-interrupt or 0 if all was fine.
     */
    private function isIdPatternValid(string $vatID, string $pattern): int
    {
        $len = \mb_strlen($vatID);
        for ($i = 0; $i < $len; $i++) {
            // each character and white-space is compared exactly, while digits can be [1..9]
            switch (true) {
                case \ctype_alpha($pattern[$i]):
                case \ctype_space($pattern[$i]):
                    if ($pattern[$i] === $vatID[$i]) {
                        continue 2; // check-space OK
                    }

                    break 2; // check-space FAIL
                case \is_numeric($pattern[$i]):
                    if (\is_numeric($vatID[$i])) {
                        continue 2; // check-num OK
                    }

                    break 2; // check-num FAIL
                default:
                    if ($pattern[$i] === '_') {
                        continue 2;
                    }

                    break 2;
            }
        }
        // check, if we iterate the whole given VAT-ID,
        // and if not, return the position, at which we sopped
        if (\mb_strlen($vatID) !== $i) {
            $this->errorPos = $i; // store the error-position for later usage too

            return $i;
        }

        return 0;
    }

    /**
     * controls the parsing of the VAT-ID
     * ("comparing against multiple patterns of one country")
     * returns "true" = VAT-ID is correct, "false" = not correct.
     */
    public function parseVatId(): bool
    {
        // guess a country - the first 2 characters should allways be the country-code
        $result = \preg_match('/([A-Z]{2})(.*)/', $this->vatID, $this->idParts);
        if ($result === 0 || !isset($this->idParts[1])) {
            $this->errorCode = 100; // error: the ID did not start with 2 uppercase letters

            return false;
        }
        // there is no country starting with this 2 letters
        if (!isset(self::$countryPattern[$this->idParts[1]])) {
            $this->errorCode = VATCheckInterface::ERR_COUNTRY_NOT_FOUND;
            $this->errorInfo = $this->idParts[1];

            return false;
        }

        foreach (self::$countryPattern[$this->idParts[1]] as $pattern) {
            if (\mb_strlen($this->vatID) !== \mb_strlen($pattern)) {
                continue; // skipt this pattern, if the length did not match. try the next one
            }
            $parseResult = $this->isIdPatternValid($this->vatID, $pattern);
            if ($parseResult === 0) {
                return true;
            }

            $this->errorCode = VATCheckInterface::ERR_PATTERN_MISMATCH;
            $this->errorInfo = $parseResult; // interrupt-/error-position

            return false;
        }
        $this->errorCode = VATCheckInterface::ERR_PATTERNLENGTH_NOT_FOUND;

        return false;
    }

    /**
     * return the ID splitted into the two pieces:
     * - 2 (big) letters of country code
     * - n letters or digits as the rest of the ID
     *
     * NOTE: should called after '->parseVatId()'
     *
     * @return string[]
     */
    public function getIdAsParams(): array
    {
        return [$this->idParts[1], $this->idParts[2]];
    }

    /**
     * returns a descriptive string of the last ocurred error
     *
     * @return int
     */
    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    public function getErrorInfo(): string|int
    {
        return $this->errorInfo;
    }

    /**
     * returns the position, in the VAT-ID-string, at which the last error was ocurred
     *
     * @return int
     */
    public function getErrorPos(): int
    {
        return $this->errorPos;
    }
}
