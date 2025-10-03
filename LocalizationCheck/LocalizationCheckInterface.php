<?php

declare(strict_types=1);

namespace JTL\Backend\LocalizationCheck;

use Illuminate\Support\Collection;

/**
 * Interface LocalizationCheckInterface
 * @package JTL\Backend\LocalizationCheck
 */
interface LocalizationCheckInterface
{
    /**
     * @return Collection<int, Item>
     */
    public function getExcessLocalizations(): Collection;

    /**
     * @return int
     */
    public function deleteExcessLocalizations(): int;

    /**
     * @return Collection<int, Item>
     */
    public function getItemsWithoutLocalization(): Collection;

    /**
     * @return string
     */
    public function getLocation(): string;
}
