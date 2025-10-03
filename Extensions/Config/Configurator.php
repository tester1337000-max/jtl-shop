<?php

declare(strict_types=1);

namespace JTL\Extensions\Config;

use JTL\Cart\Cart;
use JTL\Cart\CartHelper;
use JTL\Catalog\Product\Artikel;
use JTL\Catalog\Product\Preise;
use JTL\Nice;
use JTL\Session\Frontend;
use JTL\Shop;

use function Functional\some;

/**
 * Class Configurator
 * @package JTL\Extensions\Config
 */
class Configurator
{
    /**
     * @var array<int, array<int, Group>>
     */
    private static array $groups = [];

    public static function checkLicense(): bool
    {
        return Nice::getInstance()->checkErweiterung(\SHOP_ERWEITERUNG_KONFIGURATOR);
    }

    /**
     * @return array<int, array<int, Group>>
     */
    public static function getGroups(): array
    {
        return self::$groups;
    }

    /**
     * @return Group[]
     */
    public static function getKonfig(int $productID, int $languageID = 0): array
    {
        $groups = [];
        $data   = Shop::Container()->getDB()->selectAll(
            'tartikelkonfiggruppe',
            'kArtikel',
            $productID,
            'kKonfigGruppe',
            'nSort ASC'
        );
        if (\count($data) === 0 || !self::checkLicense()) {
            return [];
        }
        $languageID = $languageID ?: Shop::getLanguageID();
        if (!isset(self::$groups[$languageID])) {
            self::$groups[$languageID] = [];
        }
        foreach ($data as $item) {
            $id    = (int)$item->kKonfigGruppe;
            $group = self::$groups[$languageID][$id] ?? new Group($id, $languageID);
            if (\count($group->oItem_arr) > 0) {
                $groups[]                       = $group;
                self::$groups[$languageID][$id] = $group;
            }
        }

        return $groups;
    }

    public static function hasKonfig(int $productID): bool
    {
        if (!self::checkLicense()) {
            return false;
        }
        $obj = Shop::Container()->getDB()->getSingleObject(
            'SELECT tartikelkonfiggruppe.kKonfiggruppe
                 FROM tartikelkonfiggruppe
                 JOIN tkonfigitem
                    ON tkonfigitem.kKonfiggruppe = tartikelkonfiggruppe.kKonfiggruppe
                        AND tartikelkonfiggruppe.kArtikel = :pid',
            ['pid' => $productID]
        );

        return $obj !== null;
    }

    public static function validateKonfig(): bool
    {
        /* Vorvalidierung deaktiviert */
        return true;
    }

    /**
     * @param Cart $cart
     */
    public static function postcheckCart(Cart $cart): void
    {
        if (\count($cart->PositionenArr) === 0 || !self::checkLicense()) {
            return;
        }
        $deletedItems    = [];
        $customerGroupID = Frontend::getCustomerGroup()->getID();
        $languageID      = Shop::getLanguageID();
        foreach ($cart->PositionenArr as $index => $item) {
            if ($item->nPosTyp !== \C_WARENKORBPOS_TYP_ARTIKEL) {
                continue;
            }
            $deleted = false;
            if ($item->cUnique && $item->kKonfigitem === 0) {
                $configItems = [];
                foreach ($cart->PositionenArr as $child) {
                    if ($child->cUnique && $child->cUnique === $item->cUnique && $child->kKonfigitem > 0) {
                        $configItems[] = new Item($child->kKonfigitem, $languageID, $customerGroupID);
                    }
                }
                // Konfiguration validieren
                if (self::validateCart($item->kArtikel ?? 0, $configItems) !== true) {
                    $deleted        = true;
                    $deletedItems[] = $index;
                }
            } elseif (!$item->cUnique) {
                // Konfiguration vorhanden -> löschen
                if ($item->kKonfigitem > 0 && self::hasKonfig($item->kArtikel ?? 0)) {
                    $deleted        = true;
                    $deletedItems[] = $index;
                }
            }
            if ($deleted) {
                Shop::Container()->getLogService()->error(
                    'Validierung der Konfiguration fehlgeschlagen - Warenkorbposition wurde entfernt: {name} ({id})',
                    ['name' => $item->getName(), 'id' => $item->kArtikel]
                );
            }
        }
        if (\count($deletedItems) > 0) {
            CartHelper::deleteCartItems($deletedItems, false);
        }
    }

    /**
     * @param int    $productID
     * @param Item[] $configItems
     * @return array<int, string>|bool
     */
    public static function validateCart(int $productID, array $configItems): array|bool
    {
        if ($productID === 0) {
            Shop::Container()->getLogService()->error('Validierung der Konfiguration fehlgeschlagen - Ungültige Daten');

            return false;
        }
        $total   = 0.0;
        $product = new Artikel();
        $product->fuelleArtikel($productID, Artikel::getDefaultOptions());
        // Grundpreis
        if ($product->kArtikel > 0) {
            $total = $product->Preise->fVKNetto ?? 0.0;
        }
        $total  = self::getTotal($total, $configItems);
        $errors = self::getErrors($productID, $configItems);
        if ($total < 0.0) {
            $error = \sprintf(
                "Negative Konfigurationssumme für Artikel '%s' (Art.Nr.: %s, Netto: %s) - Vorgang abgebrochen",
                $product->cName,
                $product->cArtNr,
                Preise::getLocalizedPriceString($total)
            );
            Shop::Container()->getLogService()->error($error);

            return false;
        }

        return \count($errors) === 0 ? true : $errors;
    }

    /**
     * @param Item[] $configItems
     */
    private static function getTotal(float $total, array $configItems): float
    {
        foreach ($configItems as $configItem) {
            if (
                !isset($configItem->fAnzahl)
                || $configItem->fAnzahl < $configItem->getMin()
                || $configItem->fAnzahl > $configItem->getMax()
            ) {
                $configItem->fAnzahl = $configItem->getInitial();
            }
            $total += $configItem->getPreis(true) * $configItem->fAnzahl;
        }

        return $total;
    }

    /**
     * @param int    $productID
     * @param Item[] $configItems
     * @return array<int, string>
     */
    private static function getErrors(int $productID, array $configItems): array
    {
        $errors = [];
        foreach (self::getKonfig($productID) as $group) {
            $itemCount = 0;
            $groupID   = $group->getKonfiggruppe();
            $min       = $group->getMin();
            $max       = $group->getMax();
            if ($groupID === null) {
                continue;
            }
            foreach ($configItems as $configItem) {
                if ($configItem->getKonfiggruppe() === $groupID) {
                    $itemCount++;
                }
            }
            if ($itemCount < $min && $min > 0) {
                if ($min === $max) {
                    $errors[$groupID] = Shop::Lang()->get('configChooseNComponents', 'productDetails', $min);
                } else {
                    $errors[$groupID] = Shop::Lang()->get('configChooseMinComponents', 'productDetails', $min);
                }
                $errors[$groupID] .= self::langComponent($min > 1);
            } elseif ($itemCount > $max && $max > 0) {
                if ($min === $max) {
                    $errors[$groupID] = Shop::Lang()->get('configChooseNComponents', 'productDetails', $min)
                        . self::langComponent($min > 1);
                } else {
                    $errors[$groupID] = Shop::Lang()->get('configChooseMaxComponents', 'productDetails', $max)
                        . self::langComponent($max > 1);
                }
            }
        }

        return $errors;
    }

    private static function langComponent(bool $plural = false): string
    {
        $component = ' ';

        return $component . Shop::Lang()->get($plural ? 'configComponents' : 'configComponent', 'productDetails');
    }

    /**
     * @param Group[] $confGroups
     */
    public static function hasUnavailableGroup(array $confGroups): bool
    {
        return some($confGroups, fn(Group $group): bool => $group->getMin() > 0 && !$group->minItemsInStock());
    }
}
