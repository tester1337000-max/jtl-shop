<?php

declare(strict_types=1);

namespace JTL\VerificationVAT;

use Psr\Log\LoggerInterface;

/**
 * Interface VATCheckInterface
 * @package JTL\VerificationVAT
 */
interface VATCheckInterface
{
    public const ERR_NO_ID_GIVEN             = 1;   // error: no $szUstID was given
    public const ERR_PATTERNLENGTH_NOT_FOUND = 110; // error: no length was matching
    public const ERR_COUNTRY_NOT_FOUND       = 130; // error: no pattern for such a country
    public const ERR_PATTERN_MISMATCH        = 120; // error: id did not match any pattern of this country

    /**
     * @param VATCheckDownSlots $slots
     * @param LoggerInterface   $logger
     */
    public function __construct(VATCheckDownSlots $slots, LoggerInterface $logger);

    /**
     * @param string $ustID
     * @return array{success: bool, errortype: string, errorcode: int, errorinfo: string}
     */
    public function doCheckID(string $ustID): array;
}
