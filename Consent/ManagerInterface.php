<?php

declare(strict_types=1);

namespace JTL\Consent;

use Illuminate\Support\Collection;

/**
 * Interface ManagerInterface
 * @package JTL\Consent
 */
interface ManagerInterface
{
    /**
     * @return array<string, bool>
     */
    public function getConsents(): array;

    /**
     * @param ItemInterface $item
     * @return bool
     */
    public function itemHasConsent(ItemInterface $item): bool;

    /**
     * @param ItemInterface $item
     */
    public function itemGiveConsent(ItemInterface $item): void;

    /**
     * @param ItemInterface $item
     */
    public function itemRevokeConsent(ItemInterface $item): void;

    /**
     * @param string $itemID
     * @return bool
     */
    public function hasConsent(string $itemID): bool;

    /**
     * @param array<string, string> $data
     * @return array<string, bool>
     */
    public function save(array|string $data): array;

    /**
     * @param int $languageID
     * @return Collection<int, ItemInterface>
     */
    public function getActiveItems(int $languageID): Collection;

    /**
     * @param int $languageID
     * @return Collection<int, ItemInterface>
     * @throws \Exception
     */
    public function initActiveItems(int $languageID): Collection;
}
