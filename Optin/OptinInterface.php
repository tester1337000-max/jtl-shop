<?php

declare(strict_types=1);

namespace JTL\Optin;

/**
 * Interface OptinInterface
 * @package JTL\Optin
 */
interface OptinInterface
{
    /**
     * @param OptinRefData $refData
     * @param int          $location
     * @return OptinInterface
     */
    public function createOptin(OptinRefData $refData, int $location = 0): OptinInterface;

    /**
     * @return void
     */
    public function activateOptin(): void;

    /**
     * @return void
     */
    public function deactivateOptin(): void;

    /**
     * @return void
     */
    public function sendActivationMail(): void;
}
