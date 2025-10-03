<?php

declare(strict_types=1);

namespace JTL\Boxes\Items;

use JTL\Catalog\Product\Artikel;
use JTL\Helpers\Request;
use JTL\Helpers\Text;
use JTL\Services\JTL\LinkService;
use JTL\Session\Frontend;
use JTL\Shop;

/**
 * Class CompareList
 * @package JTL\Boxes\Items
 */
final class CompareList extends AbstractBox
{
    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->addMapping('cAnzeigen', 'ShowBox');
        $this->setShow(true);
        $productList = Frontend::get('Vergleichsliste')->oArtikel_arr ?? [];
        $products    = [];
        if (\count($productList) > 0) {
            $validParams = Request::getReservedQueryParams();
            $extra       = '';
            $postData    = \array_keys($_REQUEST);
            foreach ($postData as $param) {
                if ((int)$_REQUEST[$param] <= 0 || !\in_array($param, $validParams, true)) {
                    continue;
                }
                if (\is_array($_REQUEST[$param])) {
                    $extraTMP = '';
                    foreach ($_REQUEST[$param] as $item) {
                        $extraTMP .= '&' . $param . '%5B%5D=' . $item;
                    }
                    $extra .= $extraTMP;
                } else {
                    $extra .= '&' . $param . '=' . $_REQUEST[$param];
                }
            }
            $extra          = Text::filterXSS($extra);
            $defaultOptions = Artikel::getDefaultOptions();
            $baseURL        = LinkService::getInstance()->getStaticRoute('vergleichsliste.php')
                . '?' . \QUERY_PARAM_COMPARELIST_PRODUCT . '=';
            $db             = Shop::Container()->getDB();
            $cache          = Shop::Container()->getCache();
            $languageID     = Shop::getLanguageID();
            $currency       = Frontend::getCurrency();
            $cGroup         = Frontend::getCustomerGroup();
            $cGroupID       = $cGroup->getID();
            foreach ($productList as $item) {
                $product = new Artikel($db, $cGroup, $currency, $cache);
                $product->fuelleArtikel($item->kArtikel, $defaultOptions, $cGroupID, $languageID);
                $product->cURLDEL = $baseURL . $item->kArtikel . $extra;
                if (isset($item->oVariationen_arr) && \count($item->oVariationen_arr) > 0) {
                    $product->Variationen = $item->oVariationen_arr;
                }
                if ($product->kArtikel > 0) {
                    $products[] = $product;
                }
            }
        }
        $this->setItemCount((int)$this->config['vergleichsliste']['vergleichsliste_anzahl']);
        $this->setProducts($products);
        \executeHook(\HOOK_BOXEN_INC_VERGLEICHSLISTE, ['box' => $this]);
    }

    public function getShowBox(): string
    {
        return $this->config['boxen']['boxen_vergleichsliste_anzeigen'];
    }

    public function setShowBox(string $value): void
    {
    }
}
