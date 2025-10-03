<?php

declare(strict_types=1);

namespace JTL\Services\JTL;

use Smarty\Smarty;

/**
 * Interface CaptchaService
 * @package JTL\Services\JTL
 */
interface CaptchaServiceInterface
{
    /**
     * @return bool
     */
    public function isConfigured(): bool;

    /**
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * @param Smarty|\Smarty $smarty
     * @return string
     */
    public function getHeadMarkup(Smarty|\Smarty $smarty): string;

    /**
     * @param Smarty|\Smarty $smarty
     * @return string
     */
    public function getBodyMarkup(Smarty|\Smarty $smarty): string;

    /**
     * @param array<string, mixed> $requestData
     * @return bool
     */
    public function validate(array $requestData): bool;
}
