<?php

declare(strict_types=1);

namespace JTL\Boxes\Items;

use JTL\Catalog\Product\Preise;
use JTL\Helpers\Request;
use JTL\Helpers\Text;
use JTL\Session\Frontend;
use JTL\Shop;

/**
 * Class Wishlist
 * @package JTL\Boxes\Items
 */
final class Wishlist extends AbstractBox
{
    private int $wishListID = 0;

    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->addMapping('nBilderAnzeigen', 'ShowImages');
        $this->addMapping('CWunschlistePos_arr', 'Items');
        $this->setShow($config['global']['global_wunschliste_anzeigen'] === 'Y');
        if (!empty(Frontend::getWishList()->getID())) {
            $this->setWishListID(Frontend::getWishList()->getID());
            $additionalParams = [];
            $requestURI       = $_SERVER['REQUEST_URI'] ?? $_SERVER['SCRIPT_NAME'] ?? '';
            $parsed           = \parse_url($requestURI);
            $shopURL          = Shop::getURL() . ($parsed['path'] ?? '/') . '?';
            if (isset($parsed['query'])) {
                \parse_str($parsed['query'], $additionalParams);
            }
            $wishlistItems = Frontend::getWishList()->getItems();
            $validPostVars = Request::getReservedQueryParams();
            $postMembers   = \array_keys($_REQUEST);
            foreach ($postMembers as $postMember) {
                if ((int)$_REQUEST[$postMember] > 0 && \in_array($postMember, $validPostVars, true)) {
                    $additionalParams[$postMember] = (int)$_REQUEST[$postMember];
                }
            }
            /** @var array<string, int> $additionalParams */
            $additionalParams = Text::filterXSS($additionalParams);
            foreach ($wishlistItems as $wishlistItem) {
                $product = $wishlistItem->getProduct();
                if ($product === null) {
                    continue;
                }
                $additionalParams['wlplo'] = $wishlistItem->getID();
                $wishlistItem->setURL($shopURL . \http_build_query($additionalParams));
                if (Frontend::getCustomerGroup()->isMerchant()) {
                    $price = isset($product->Preise->fVKNetto)
                        ? (int)$wishlistItem->getQty() * $product->Preise->fVKNetto
                        : 0;
                } else {
                    $price = isset($product->Preise->fVKNetto)
                        ? (int)$wishlistItem->getQty() * ($product->Preise->fVKNetto
                            * (100 + $_SESSION['Steuersatz'][$product->kSteuerklasse]) / 100)
                        : 0;
                }
                $wishlistItem->setPrice(Preise::getLocalizedPriceString($price, Frontend::getCurrency()));
            }
            $this->setItemCount((int)$this->config['boxen']['boxen_wunschzettel_anzahl']);
            $this->setItems(\array_reverse($wishlistItems));
        }
        \executeHook(\HOOK_BOXEN_INC_WUNSCHZETTEL, ['box' => $this]);
    }

    public function getWishListID(): int
    {
        return $this->wishListID;
    }

    public function setWishListID(int $id): void
    {
        $this->wishListID = $id;
    }

    public function getShowImages(): bool
    {
        return $this->config['boxen']['boxen_wunschzettel_bilder'] === 'Y';
    }

    public function setShowImages(mixed $value): void
    {
    }
}
