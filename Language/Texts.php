<?php

declare(strict_types=1);

namespace JTL\Language;

use JTL\Cart\Cart;
use JTL\Catalog\Product\Artikel;
use JTL\Extensions\Config\Item;
use JTL\Shop;

class Texts
{
    public static function cartContainsItems(Cart $cart): string
    {
        if ($cart->hatTeilbareArtikel()) {
            $itemCount = $cart->gibAnzahlPositionenExt([\C_WARENKORBPOS_TYP_ARTIKEL]);
            if ($itemCount === 1) {
                return Shop::Lang()->get('yourbasketcontainsPositionsSingular', 'checkout', $itemCount);
            }

            return Shop::Lang()->get('yourbasketcontainsPositionsPlural', 'checkout', $itemCount);
        }
        $count       = $cart->gibAnzahlArtikelExt([\C_WARENKORBPOS_TYP_ARTIKEL]);
        $countString = \str_replace('.', ',', (string)$count);
        if ($count === 1) {
            return Shop::Lang()->get('yourbasketcontainsItemsSingular', 'checkout', $countString);
        }
        if ($count > 1) {
            return Shop::Lang()->get('yourbasketcontainsItemsPlural', 'checkout', $countString);
        }

        return Shop::Lang()->get('emptybasket', 'checkout');
    }

    public static function orderCartContainsItems(Cart $cart): string
    {
        $posCount  = \count($cart->PositionenArr);
        $itemCount = !empty($cart->kWarenkorb)
            ? $cart->gibAnzahlArtikelExt([\C_WARENKORBPOS_TYP_ARTIKEL])
            : 0;
        if ($posCount === 1) {
            if ($itemCount === 1) {
                return Shop::Lang()->get('orderPositionSingularItemsSingular', 'checkout', $posCount, $itemCount);
            }
            return Shop::Lang()->get('orderPositionSingularItemsPlural', 'checkout', $posCount, $itemCount);
        }
        if ($itemCount === 1) {
            return Shop::Lang()->get('orderPositionPluralItemsSingular', 'checkout', $posCount, $itemCount);
        }

        return Shop::Lang()->get('orderPositionPluralItemsPlural', 'checkout', $posCount, $itemCount);
    }

    public static function taxItems(int|float|string $ust, bool $net): string
    {
        if ((int)$ust >= $ust) {
            $ust = (int)$ust;
        }
        $showVat  = Shop::getSettingValue(\CONF_GLOBAL, 'global_ust_auszeichnung') === 'autoNoVat' ? '' : ($ust . '% ');
        $inklexkl = Shop::Lang()->get($net === true ? 'excl' : 'incl', 'productDetails');

        return $inklexkl . ' ' . $showVat . Shop::Lang()->get('vat', 'productDetails');
    }

    public static function orderState(int $state): string
    {
        return match ($state) {
            \BESTELLUNG_STATUS_OFFEN          => Shop::Lang()->get('statusPending', 'order'),
            \BESTELLUNG_STATUS_IN_BEARBEITUNG => Shop::Lang()->get('statusProcessing', 'order'),
            \BESTELLUNG_STATUS_BEZAHLT        => Shop::Lang()->get('statusPaid', 'order'),
            \BESTELLUNG_STATUS_VERSANDT       => Shop::Lang()->get('statusShipped', 'order'),
            \BESTELLUNG_STATUS_STORNO         => Shop::Lang()->get('statusCancelled', 'order'),
            \BESTELLUNG_STATUS_TEILVERSANDT   => Shop::Lang()->get('statusPartialShipped', 'order'),
            default                           => '',
        };
    }

    public static function minOrderQTY(Artikel $product, string|int|float $amount, int $configItemID = 0): string
    {
        if ($product->cEinheit) {
            $product->cEinheit = ' ' . $product->cEinheit;
        }
        $name = $product->cName;
        if ($configItemID > 0 && Item::checkLicense() === true) {
            $name = (new Item($configItemID))->getName();
        }

        return Shop::Lang()->get(
            'productMinorderQty',
            'messages',
            $name,
            $product->fMindestbestellmenge . $product->cEinheit,
            (float)$amount . $product->cEinheit
        );
    }
}
