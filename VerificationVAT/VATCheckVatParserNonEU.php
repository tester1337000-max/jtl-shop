<?php

declare(strict_types=1);

namespace JTL\VerificationVAT;

/**
 * Class VATCheckVatParserNonEU
 * @package JTL\VerificationVAT
 */
class VATCheckVatParserNonEU
{
    /**
     * @var array<string, array<int, string>> $countryPattern
     */
    private array $countryPattern = [
        // CH-Schweiz                    CHE-999.999.999 oder
        //                               CHE-999999999         1 Block mit 9 Ziffern,
        //                               CHE-999.999.999-99    with or without "Handelregister"-appendix (e.g. "-43")
        'CHE' => [
            'CHE-999_999_999',              // example: CHE-422.597.330 (valid)
            'CHE-999_999_999-9',
            'CHE-999_999_999-99',
            'CHE-999999999',
            'CHE-999999999-9',
            'CHE-999999999-99'
        ],

        // GB-Vereinigtes Königreich     GB999 9999 99 oder
        //                               GB999 9999 99 999 oder
        //                               GBGD999 oder
        //                               GBHA999              1 Block mit 3 Ziffern, 1 Block mit 4 Ziffern
        // und 1 Block mit 2 Ziffern; oder wie oben, gefolgt von einem Block mit 3 Ziffern; oder 1 Block mit 5 Ziffern
        //, 'GB' => [
        //      'GB999 9999 99'
        //    , 'GB999 9999 99 999'
        //    , 'GBGD999'
        //    , 'GBHA999'
        //]
        // modification in place of original documentation, because the VIES can not handle spaces
        'GB'  => [
            'GB999999999',  // example: GB862906405(ok), 'GB 117 8490 96'(ok, spaces are removed before parsing)
            'GB999999999999',
            'GBGD999',
            'GBHA999'
        ],
    ];

    private string|int $errorInfo = '';

    private int $errorCode = 0;

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
                    if ($pattern[$i] === '_' || $pattern[$i] === '-') {
                        continue 2;
                    }
                    break 2;
            }
        }
        // check, if we iterate the whole given VAT-ID,
        // and if not, return the position, at which we stopped
        if (\mb_strlen($vatID) !== $i) {
            $this->errorPos = $i; // store the error-position for later usage too

            return $i;
        }

        return 0;
    }

    public function parseVatId(): bool
    {
        // check, if there is a country, which matches the starting chars of the given ID
        $limit   = 4; // first three(!) for now
        $hit     = false;
        $pattern = '';
        for ($i = 1; $limit > $i && !$hit; $i++) {
            $pattern = \substr($this->vatID, 0, $i);
            $hit     = isset($this->countryPattern[$pattern]);
        }
        // compare our VAT-ID to all pattern of the guessed country
        foreach ($this->countryPattern[$pattern] as $pattern) {
            // length-check (and go back, if nothing matches)
            if (\strlen($this->vatID) !== \strlen($pattern)) {
                continue; // skipt this pattern, if the length did not match. try the next one
            }
            // checking the given pattern (return a possible interrupt-position)
            $parseResult = $this->isIdPatternValid($this->vatID, $pattern);
            if ($parseResult === 0) {
                return true; // if we found a valid pattern-match, we've done our job here
            }
            $this->errorCode = VATCheckInterface::ERR_PATTERN_MISMATCH;
            // error 120: id did not match any pattern of this country
            $this->errorInfo = $parseResult; // interrupt-/error-position

            return false;
        }
        $this->errorCode = VATCheckInterface::ERR_PATTERNLENGTH_NOT_FOUND; // error 110: no length was matching

        return false;
    }

    public function getErrorInfo(): int|string
    {
        return $this->errorInfo;
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    /**
     * returns the position in the VAT-ID-string at which the last error ocurred
     */
    public function getErrorPos(): int
    {
        return $this->errorPos;
    }
}
