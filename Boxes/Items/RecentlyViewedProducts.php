<?php

declare(strict_types=1);

namespace JTL\Boxes\Items;

use JTL\Catalog\Product\Artikel;
use JTL\Helpers\GeneralObject;
use JTL\Session\Frontend;
use JTL\Shop;

/**
 * Class RecentlyViewedProducts
 * @package JTL\Boxes\Items
 */
final class RecentlyViewedProducts extends AbstractBox
{
    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->setShow(false);
        if (
            GeneralObject::hasCount('ZuletztBesuchteArtikel', $_SESSION)
            && Frontend::getCustomerGroup()->mayViewCategories()
        ) {
            $products       = [];
            $defaultOptions = Artikel::getDefaultOptions();
            $db             = Shop::Container()->getDB();
            $cache          = Shop::Container()->getCache();
            $customerGroup  = Frontend::getCustomerGroup();
            $currency       = Frontend::getCurrency();
            foreach ($_SESSION['ZuletztBesuchteArtikel'] as $i => $item) {
                $product = new Artikel($db, $customerGroup, $currency, $cache);
                $product->fuelleArtikel($item->kArtikel, $defaultOptions);
                if ($product->kArtikel > 0) {
                    $products[$i] = $product;
                }
            }
            $this->setProducts(\array_reverse($products));
            $this->setShow(true);

            \executeHook(\HOOK_BOXEN_INC_ZULETZTANGESEHEN, ['box' => $this]);
        }
    }
}
