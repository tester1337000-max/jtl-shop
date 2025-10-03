<?php

declare(strict_types=1);

namespace JTL\VerificationVAT;

use Psr\Log\LoggerInterface;

/**
 * Class AbstractVATCheck
 * @package JTL\VerificationVAT
 */
abstract class AbstractVATCheck implements VATCheckInterface
{
    public function __construct(protected VATCheckDownSlots $downTimes, protected LoggerInterface $logger)
    {
    }

    /**
     * spaces can't handled by the VIES-system,
     * so we condense the ID-string here and let them out
     */
    public function condenseSpaces(string $sourceString): string
    {
        return \str_replace(' ', '', $sourceString);
    }
}
