<?php

declare(strict_types=1);

use JTL\Cart\Cart;
use JTL\Catalog\Product\Artikel;
use JTL\Language\Texts;

/**
 * @deprecated since 5.5.0
 */
function lang_warenkorb_warenkorbEnthaeltXArtikel(Cart $cart): string
{
    \trigger_error(__METHOD__ . ' is deprecated. Use Texts::cartContainsItems() instead.', \E_USER_DEPRECATED);
    return Texts::cartContainsItems($cart);
}

/**
 * @deprecated since 5.5.0
 */
function lang_warenkorb_bestellungEnthaeltXArtikel(Cart $cart): string
{
    \trigger_error(__METHOD__ . ' is deprecated. Use Texts::orderCartContainsItems() instead.', \E_USER_DEPRECATED);
    return Texts::orderCartContainsItems($cart);
}

/**
 * @param int|string $ust
 * @param bool       $net
 * @return string
 * @deprecated since 5.5.0
 */
function lang_steuerposition($ust, $net): string
{
    \trigger_error(__METHOD__ . ' is deprecated. Use \JTL\Lang\Texts::taxItems() instead.', \E_USER_DEPRECATED);
    return Texts::taxItems($ust, (bool)$net);
}

/**
 * @deprecated since 5.5.0
 */
function lang_bestellstatus(int $state): string
{
    \trigger_error(__METHOD__ . ' is deprecated. Use \JTL\Lang\Texts::orderState() instead.', \E_USER_DEPRECATED);
    return Texts::orderState($state);
}

/**
 * @param Artikel   $product
 * @param int|float $amount
 * @param int       $configItemID
 * @return string
 * @deprecated since 5.5.0
 */
function lang_mindestbestellmenge(Artikel $product, $amount, int $configItemID = 0): string
{
    \trigger_error(__METHOD__ . ' is deprecated. Use \JTL\Lang\Texts::minOrderQTY() instead.', \E_USER_DEPRECATED);
    return Texts::minOrderQTY($product, $amount, $configItemID);
}
