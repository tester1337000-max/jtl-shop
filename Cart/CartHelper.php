<?php

declare(strict_types=1);

namespace JTL\Cart;

use JTL\Campaign;
use JTL\Catalog\Currency;
use JTL\Catalog\Product\Artikel;
use JTL\Catalog\Product\EigenschaftWert;
use JTL\Catalog\Product\Preise;
use JTL\Catalog\Wishlist\Wishlist;
use JTL\Checkout\CouponValidator;
use JTL\Checkout\Kupon;
use JTL\Checkout\Lieferadresse;
use JTL\Checkout\Rechnungsadresse;
use JTL\Customer\Customer;
use JTL\Customer\CustomerGroup;
use JTL\Extensions\Config\Configurator;
use JTL\Extensions\Config\Item;
use JTL\Extensions\Upload\Upload;
use JTL\Helpers\Form;
use JTL\Helpers\GeneralObject;
use JTL\Helpers\Product;
use JTL\Helpers\Request;
use JTL\Helpers\Tax;
use JTL\Helpers\Text;
use JTL\Helpers\XSelling;
use JTL\Language\Texts;
use JTL\Session\Frontend;
use JTL\Settings\Option\Checkout;
use JTL\Settings\Settings;
use JTL\Shop;
use stdClass;

use function Functional\filter;
use function Functional\map;

/**
 * Class CartHelper
 * @package JTL\Cart
 */
class CartHelper
{
    public const NET = 0;

    public const GROSS = 1;

    protected function initCartInfo(): stdClass
    {
        return (object)[
            'type'      => $this->getCustomerGroup()->isMerchant() ? self::NET : self::GROSS,
            'currency'  => $this->getCurrency(),
            'article'   => [0, 0],
            'shipping'  => [0, 0],
            'discount'  => [0, 0],
            'surcharge' => [0, 0],
            'total'     => [0, 0],
            'items'     => [],
        ];
    }

    protected function calculateCredit(stdClass $cartInfo): void
    {
        if (($_SESSION['Bestellung']->GuthabenNutzen ?? 0) === 1) {
            $amountGross = $_SESSION['Bestellung']->fGuthabenGenutzt * -1;

            $cartInfo->discount[self::NET]   += $amountGross;
            $cartInfo->discount[self::GROSS] += $amountGross;
        }
        // positive discount
        $cartInfo->discount[self::NET]   *= -1;
        $cartInfo->discount[self::GROSS] *= -1;
    }

    protected function calculatePayment(stdClass $cartInfo, int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }
        $payments = Shop::Container()->getDB()->getObjects(
            'SELECT cZahlungsanbieter, fBetrag
                FROM tzahlungseingang
                WHERE kBestellung = :orderId',
            ['orderId' => $orderId]
        );
        if (!$payments) {
            return;
        }
        foreach ($payments as $payed) {
            $incoming = (float)$payed->fBetrag;
            if ($incoming === 0.0) {
                continue;
            }

            $cartInfo->total[self::NET]     -= $incoming;
            $cartInfo->total[self::GROSS]   -= $incoming;
            $cartInfo->article[self::NET]   -= $incoming;
            $cartInfo->article[self::GROSS] -= $incoming;
            $cartInfo->items[]              = (object)[
                'name'     => \html_entity_decode($payed->cZahlungsanbieter),
                'quantity' => 1,
                'amount'   => [
                    self::NET   => -$incoming,
                    self::GROSS => -$incoming
                ]
            ];
        }
    }

    protected function calculateTotal(stdClass $cartInfo): void
    {
        $cartInfo->total[self::NET]   = $cartInfo->article[self::NET]
            + $cartInfo->shipping[self::NET]
            - $cartInfo->discount[self::NET]
            + $cartInfo->surcharge[self::NET];
        $cartInfo->total[self::GROSS] = $cartInfo->article[self::GROSS]
            + $cartInfo->shipping[self::GROSS]
            - $cartInfo->discount[self::GROSS]
            + $cartInfo->surcharge[self::GROSS];
    }

    public function getTotal(int $decimals = 0): stdClass
    {
        $info = $this->initCartInfo();

        foreach ($this->getPositions() as $item) {
            $amount      = $item->fPreis * $info->currency->getConversionFactor();
            $amountGross = Tax::getGross(
                $amount,
                CartItem::getTaxRate($item),
                $decimals > 0 ? $decimals : 2
            );

            switch ((int)$item->nPosTyp) {
                case \C_WARENKORBPOS_TYP_ARTIKEL:
                case \C_WARENKORBPOS_TYP_GRATISGESCHENK:
                    $data = (object)[
                        'name'     => '',
                        'quantity' => 1,
                        'amount'   => []
                    ];

                    $data->name   = \html_entity_decode($item->getName($_SESSION['cISOSprache']));
                    $data->amount = [
                        self::NET   => $amount,
                        self::GROSS => $amountGross
                    ];

                    if ($item->Artikel?->cTeilbar === 'Y' && (int)$item->nAnzahl !== $item->nAnzahl) {
                        $data->amount[self::NET]   *= (float)$item->nAnzahl;
                        $data->amount[self::GROSS] *= (float)$item->nAnzahl;

                        $data->name = \sprintf(
                            '%g %s %s',
                            (float)$item->nAnzahl,
                            $item->Artikel->cEinheit ?: 'x',
                            $data->name
                        );
                    } else {
                        $data->quantity = (int)$item->nAnzahl;
                    }

                    $info->article[self::NET]   += $data->amount[self::NET] * $data->quantity;
                    $info->article[self::GROSS] += $data->amount[self::GROSS] * $data->quantity;

                    $info->items[] = $data;
                    break;

                case \C_WARENKORBPOS_TYP_VERSANDPOS:
                case \C_WARENKORBPOS_TYP_VERSANDZUSCHLAG:
                case \C_WARENKORBPOS_TYP_VERPACKUNG:
                case \C_WARENKORBPOS_TYP_VERSAND_ARTIKELABHAENGIG:
                    $info->shipping[self::NET]   += $amount * $item->nAnzahl;
                    $info->shipping[self::GROSS] += $amountGross * $item->nAnzahl;
                    break;

                case \C_WARENKORBPOS_TYP_KUPON:
                case \C_WARENKORBPOS_TYP_GUTSCHEIN:
                case \C_WARENKORBPOS_TYP_NEUKUNDENKUPON:
                    $info->discount[self::NET]   += $amount * $item->nAnzahl;
                    $info->discount[self::GROSS] += $amountGross * $item->nAnzahl;
                    break;

                case \C_WARENKORBPOS_TYP_ZAHLUNGSART:
                    if ($amount >= 0) {
                        $info->surcharge[self::NET]   += $amount * $item->nAnzahl;
                        $info->surcharge[self::GROSS] += $amountGross * $item->nAnzahl;
                    } else {
                        $info->discount[self::NET]   += $amount * $item->nAnzahl;
                        $info->discount[self::GROSS] += $amountGross * $item->nAnzahl;
                    }
                    break;

                case \C_WARENKORBPOS_TYP_NACHNAHMEGEBUEHR:
                    $info->surcharge[self::NET]   += $amount * $item->nAnzahl;
                    $info->surcharge[self::GROSS] += $amountGross * $item->nAnzahl;
                    break;
                default:
                    break;
            }
        }

        $this->calculateCredit($info);
        $this->calculatePayment($info, $this->getIdentifier());
        $this->calculateTotal($info);

        $formatter = static function (array $prop) use ($decimals): array {
            return [
                self::NET   => \number_format($prop[self::NET], $decimals, '.', ''),
                self::GROSS => \number_format($prop[self::GROSS], $decimals, '.', ''),
            ];
        };

        if ($decimals > 0) {
            $info->article   = $formatter($info->article);
            $info->shipping  = $formatter($info->shipping);
            $info->discount  = $formatter($info->discount);
            $info->surcharge = $formatter($info->surcharge);
            $info->total     = $formatter($info->total);

            foreach ($info->items as $item) {
                $item->amount = $formatter($item->amount);
            }
        }

        return $info;
    }

    public function getObject(): ?object
    {
        return $_SESSION['Warenkorb'];
    }

    public function getShippingAddress(): Rechnungsadresse|Lieferadresse|null
    {
        return $_SESSION['Lieferadresse'];
    }

    public function getBillingAddress(): ?Rechnungsadresse
    {
        return $_SESSION['Rechnungsadresse'];
    }

    /**
     * @return CartItem[]
     */
    public function getPositions(): array
    {
        return Frontend::getCart()->PositionenArr;
    }

    public function getCustomer(): ?Customer
    {
        return Frontend::getCustomer();
    }

    public function getCustomerGroup(): CustomerGroup
    {
        return Frontend::getCustomerGroup();
    }

    public function getCurrency(): Currency
    {
        return Frontend::getCurrency();
    }

    public function getCurrencyISO(): string
    {
        return $this->getCurrency()->getCode();
    }

    public function getInvoiceID(): string
    {
        return '';
    }

    public function getIdentifier(): int
    {
        return 0;
    }

    /**
     * @deprecated since 5.5.0.
     * @noinspection PhpDeprecationInspection
     */
    public static function addVariationPictures(Cart $warenkorb): int
    {
        \trigger_error(__METHOD__ . ' is deprecated and should not be used anymore.', \E_USER_DEPRECATED);
        $count = 0;
        foreach ($warenkorb->PositionenArr as $item) {
            if ($item->Artikel !== null && \count($item->variationPicturesArr) > 0) {
                Product::addVariationPictures($item->Artikel, $item->variationPicturesArr);
                ++$count;
            }
        }

        return $count;
    }

    public static function checkAdditions(): bool
    {
        $qty = 0;
        if (isset($_POST['anzahl'])) {
            $_POST['anzahl'] = \str_replace(',', '.', $_POST['anzahl']);
        }
        if (isset($_POST['anzahl']) && (float)$_POST['anzahl'] > 0) {
            $qty = (float)$_POST['anzahl'];
        } elseif (isset($_GET['anzahl']) && (float)$_GET['anzahl'] > 0) {
            $qty = (float)$_GET['anzahl'];
        }
        if (isset($_POST['n']) && (float)$_POST['n'] > 0) {
            $qty = (float)$_POST['n'];
        } elseif (isset($_GET['n']) && (float)$_GET['n'] > 0) {
            $qty = (float)$_GET['n'];
        }
        $productID = isset($_POST['a']) ? (int)$_POST['a'] : Request::verifyGPCDataInt('a');
        \executeHook(\HOOK_TOOLS_GLOBAL_CHECKEWARENKORBEINGANG_ANFANG, [
            'kArtikel' => $productID,
            'fAnzahl'  => $qty
        ]);
        if ($productID <= 0) {
            return false;
        }
        $conf = Shop::getSettings([\CONF_GLOBAL, \CONF_VERGLEICHSLISTE]);
        if (
            (isset($_POST['Wunschliste']) || isset($_GET['Wunschliste']))
            && $conf['global']['global_wunschliste_anzeigen'] === 'Y'
        ) {
            return self::checkWishlist($productID, $qty, $conf['global']['global_wunschliste_weiterleitung'] === 'Y');
        }
        if (isset($_POST['Vergleichsliste'])) {
            return self::checkCompareList($productID, (int)$conf['vergleichsliste']['vergleichsliste_anzahl']);
        }
        if (!isset($_POST['Wunschliste']) && Request::pInt('wke') === 1) { // warenkorbeingang?
            return self::checkCart($productID, $qty);
        }

        return false;
    }

    private static function checkCart(int $productID, float|int $count): bool
    {
        // VariationsBox ist vorhanden => Prüfen ob Anzahl gesetzt wurde
        if (Request::pInt('variBox') === 1) {
            if (self::checkVariboxAmount($_POST['variBoxAnzahl'] ?? [])) {
                self::addVariboxToCart(
                    $_POST['variBoxAnzahl'],
                    $productID,
                    Product::isParent($productID),
                    isset($_POST['varimatrix'])
                );
            } else {
                \header('Location: ' . Shop::getURL() . '/?a=' . $productID . '&r=' . \R_EMPTY_VARIBOX, true, 303);
                exit;
            }

            return true;
        }
        if (Product::isParent($productID)) { // Varikombi
            $productID  = Product::getArticleForParent($productID);
            $attributes = Product::getSelectedPropertiesForVarCombiArticle($productID);
        } else {
            $attributes = Product::getSelectedPropertiesForArticle($productID);
        }
        $isConfigProduct = false;
        if (Configurator::checkLicense()) {
            $groups          = Configurator::getKonfig($productID);
            $isConfigProduct = GeneralObject::hasCount($groups);
        }
        // Beim Bearbeiten die alten Positionen löschen
        if (isset($_POST['kEditKonfig'])) {
            self::deleteCartItem(Request::pInt('kEditKonfig'));
        }
        if (!$isConfigProduct) {
            return self::addProductIDToCart($productID, $count, $attributes);
        }
        $valid             = true;
        $errors            = [];
        $itemErrors        = [];
        $configItems       = [];
        $configGroups      = GeneralObject::isCountable('item', $_POST)
            ? $_POST['item']
            : [];
        $configGroupCounts = GeneralObject::isCountable('quantity', $_POST)
            ? $_POST['quantity']
            : [];
        $configItemCounts  = GeneralObject::isCountable('item_quantity', $_POST)
            ? $_POST['item_quantity']
            : false;
        $ignoreLimits      = isset($_POST['konfig_ignore_limits']);
        $languageID        = Shop::getLanguageID();
        $customerGroupID   = Frontend::getCustomerGroup()->getID();
        foreach ($configGroups as $itemList) {
            foreach ($itemList as $configItemID) {
                $configItemID = (int)$configItemID;
                // Falls ungültig, ignorieren
                if ($configItemID <= 0) {
                    continue;
                }
                $configItem    = (new Item($configItemID, $languageID, $customerGroupID))
                    ->setQuantities(
                        $count,
                        $configItemCounts,
                        $configGroupCounts
                    );
                $configItems[] = $configItem;
                // Alle Artikel können in den WK gelegt werden?
                if ($configItem->getPosTyp() === \KONFIG_ITEM_TYP_ARTIKEL) {
                    // Varikombi
                    $configItem->oEigenschaftwerte_arr = [];
                    $tmpProduct                        = $configItem->getArtikel();
                    if ($tmpProduct === null) {
                        continue;
                    }
                    if (
                        $tmpProduct->kVaterArtikel > 0
                        && isset($tmpProduct->kEigenschaftKombi)
                        && $tmpProduct->kEigenschaftKombi > 0
                    ) {
                        $configItem->oEigenschaftwerte_arr = Product::getVarCombiAttributeValues(
                            (int)$tmpProduct->kArtikel,
                            false
                        );
                    }
                    if ($tmpProduct->cTeilbar !== 'Y' && (int)$count !== $count) {
                        $count = \max((int)$count, 1);
                    }
                    $tmpProduct->isKonfigItem = true;
                    $redirectParam            = self::addToCartCheck(
                        $tmpProduct,
                        $configItem->fAnzahlWK,
                        $configItem->oEigenschaftwerte_arr
                    );
                    if (\count($redirectParam) > 0) {
                        $valid           = false;
                        $productMessages = Product::getProductMessages(
                            $redirectParam,
                            true,
                            $configItem->getArtikel(),
                            $configItem->fAnzahlWK,
                            $configItem->getKonfigitem()
                        );

                        $itemErrors[$configItem->getKonfigitem()] = $productMessages[0];
                    }
                }
            }
        }
        // Komplette Konfiguration validieren
        if (!$ignoreLimits && (($errors = Configurator::validateCart($productID, $configItems)) !== true)) {
            $valid = false;
        }
        // Alle Konfigurationsartikel können in den WK gelegt werden
        if ($valid) {
            // Eindeutige ID
            $cUnique = \uniqid('', true);
            // Hauptartikel in den WK legen
            self::addProductIDToCart($productID, $count, $attributes, 0, $cUnique);
            $persCart = PersistentCart::getInstance(Frontend::getCustomer()->getID());
            // Konfigartikel in den WK legen
            foreach ($configItems as $configItem) {
                if ($configItem->fAnzahlWK === null) {
                    continue;
                }
                switch ($configItem->getPosTyp()) {
                    case \KONFIG_ITEM_TYP_ARTIKEL:
                        Frontend::getCart()->fuegeEin(
                            $configItem->getArtikelKey(),
                            $configItem->fAnzahlWK,
                            $configItem->oEigenschaftwerte_arr ?? [],
                            \C_WARENKORBPOS_TYP_ARTIKEL,
                            $cUnique,
                            $configItem->getKonfigitem()
                        );
                        break;

                    case \KONFIG_ITEM_TYP_SPEZIAL:
                        Frontend::getCart()->erstelleSpezialPos(
                            $configItem->getName(),
                            $configItem->fAnzahlWK,
                            $configItem->getPreis(),
                            $configItem->getSteuerklasse(),
                            \C_WARENKORBPOS_TYP_ARTIKEL,
                            false,
                            !Frontend::getCustomerGroup()->isMerchant(),
                            '',
                            $cUnique,
                            $configItem->getKonfigitem(),
                            $configItem->getArtikelKey()
                        );
                        break;
                }

                $persCart->check(
                    $configItem->getArtikelKey(),
                    $configItem->fAnzahlWK,
                    $configItem->oEigenschaftwerte_arr ?? [],
                    $cUnique,
                    $configItem->getKonfigitem()
                );
            }
            Frontend::getCart()->redirectTo();
        } else {
            Shop::Container()->getAlertService()->addError(
                Shop::Lang()->get('configError', 'productDetails'),
                'configError',
                ['dismissable' => false]
            );
            Shop::Smarty()->assign('aKonfigerror_arr', $errors)
                ->assign('aKonfigitemerror_arr', $itemErrors);
        }

        $itemList = [];
        foreach ($configGroups as $item) {
            /** @noinspection SlowArrayOperationsInLoopInspection */
            $itemList = \array_merge($itemList, $item);
        }
        Shop::Smarty()->assign('fAnzahl', $count)
            ->assign('nKonfigitem_arr', $itemList)
            ->assign('nKonfigitemAnzahl_arr', $configItemCounts)
            ->assign('nKonfiggruppeAnzahl_arr', $configGroupCounts);

        return $valid;
    }

    private static function checkCompareList(int $productID, int $maxItems): bool
    {
        $alertHelper = Shop::Container()->getAlertService();
        // Prüfen ob nicht schon die maximale Anzahl an Artikeln auf der Vergleichsliste ist
        $products = Frontend::get('Vergleichsliste')->oArtikel_arr ?? [];
        if ($maxItems <= \count($products)) {
            Shop::Container()->getAlertService()->addError(
                Shop::Lang()->get('compareMaxlimit', 'errorMessages'),
                'compareMaxlimit',
                ['dismissable' => false]
            );

            return false;
        }
        $productExists = Shop::Container()->getDB()->select(
            'tartikel',
            'kArtikel',
            $productID,
            null,
            null,
            null,
            null,
            false,
            'kArtikel, cName'
        );
        if ($productExists === null) {
            return true;
        }
        if (Product::checkProductVisibility($productID, Frontend::getCustomerGroup()->getID()) === true) {
            // Prüfe auf Vater Artikel
            $variations = [];
            if (Product::isParent($productID)) {
                $productID  = Product::getArticleForParent($productID);
                $variations = Product::getSelectedPropertiesForVarCombiArticle($productID, 1);
            }
            $compareList = Frontend::getCompareList();
            if ($compareList->productExists($productID)) {
                $alertHelper->addError(
                    Shop::Lang()->get('comparelistProductexists', 'messages'),
                    'comparelistProductexists',
                    ['dismissable' => false]
                );
            } else {
                $compareList->addProduct($productID, $variations);
                $alertHelper->addNotice(
                    Shop::Lang()->get('comparelistProductadded', 'messages'),
                    'comparelistProductadded'
                );
            }
        }

        return true;
    }

    private static function checkWishlist(int $productID, float|int $qty, bool $redirect): bool
    {
        $linkHelper = Shop::Container()->getLinkService();
        if (!isset($_POST['login']) && Frontend::getCustomer()->getID() < 1) {
            if ($qty <= 0) {
                $qty = 1;
            }
            \header(
                'Location: ' . $linkHelper->getStaticRoute('jtl.php')
                . '?a=' . $productID
                . '&n=' . $qty
                . '&r=' . \R_LOGIN_WUNSCHLISTE,
                true,
                302
            );
            exit;
        }

        if ($productID <= 0 || Frontend::getCustomer()->getID() <= 0) {
            return false;
        }
        $productExists = Shop::Container()->getDB()->select(
            'tartikel',
            'kArtikel',
            $productID,
            null,
            null,
            null,
            null,
            false,
            'kArtikel, cName'
        );
        if (
            $productExists !== null
            && $productExists->kArtikel > 0
            && Product::checkProductVisibility($productID, Frontend::getCustomerGroup()->getID()) === true
        ) {
            if (Product::isParent($productID)) {
                // Falls die Wunschliste aus der Artikelübersicht ausgewählt wurde,
                // muss zum Artikel weitergeleitet werden um Variationen zu wählen
                if (Request::verifyGPCDataInt('overview') === 1) {
                    \header(
                        'Location: ' . Shop::getURL() . '/?a=' . $productID .
                        '&n=' . $qty .
                        '&r=' . \R_VARWAEHLEN,
                        true,
                        303
                    );
                    exit;
                }

                $productID  = Product::getArticleForParent($productID);
                $attributes = $productID > 0
                    ? Product::getSelectedPropertiesForVarCombiArticle($productID)
                    : [];
            } else {
                $attributes = Product::getSelectedPropertiesForArticle($productID);
            }
            if ($productID <= 0) {
                return true;
            }
            $wishlist = Frontend::getWishList();
            if ($wishlist->getID() === 0) {
                $wishlist = new Wishlist();
                $wishlist->schreibeDB();
                $_SESSION['Wunschliste'] = $wishlist;
            }
            $itemID = $wishlist->fuegeEin(
                $productID,
                $productExists->cName,
                $attributes,
                $qty
            );
            Campaign::setCampaignAction(\KAMPAGNE_DEF_WUNSCHLISTE, $itemID, $qty);

            $obj = (object)['kArtikel' => $productID];
            \executeHook(\HOOK_TOOLS_GLOBAL_CHECKEWARENKORBEINGANG_WUNSCHLISTE, [
                'kArtikel'         => &$productID,
                'fAnzahl'          => &$qty,
                'AktuellerArtikel' => &$obj
            ]);

            Shop::Container()->getAlertService()->addNotice(
                Shop::Lang()->get('wishlistProductadded', 'messages'),
                'wishlistProductadded'
            );
            if ($redirect === true && !Request::isAjaxRequest()) {
                \header('Location: ' . $linkHelper->getStaticRoute('wunschliste.php'), true, 302);
                exit;
            }
        }

        return true;
    }

    /**
     * @param Artikel                  $product
     * @param float|int|numeric-string $qty
     * @param array<mixed>             $attributes
     * @param int                      $accuracy
     * @param string|null              $token
     * @return int[]
     * @former pruefeFuegeEinInWarenkorb()
     */
    public static function addToCartCheck(
        Artikel $product,
        float|int|string $qty,
        array $attributes,
        int $accuracy = 2,
        ?string $token = null
    ): array {
        $cart          = Frontend::getCart();
        $productID     = (int)$product->kArtikel; // relevant für die Berechnung von Artikelsummen im Warenkorb
        $redirectParam = [];
        $conf          = Shop::getSettingSection(\CONF_GLOBAL);
        if ($product->fAbnahmeintervall > 0 && !self::isMultiple((float)$qty, (float)$product->fAbnahmeintervall)) {
            $redirectParam[] = \R_ARTIKELABNAHMEINTERVALL;
        }
        if ($product->cTeilbar !== 'Y') {
            $qty = \max((int)$qty, 1);
        }
        if ($product->fMindestbestellmenge > $qty + $cart->gibAnzahlEinesArtikels($productID)) {
            $redirectParam[] = \R_MINDESTMENGE;
        }
        if (
            $product->cLagerBeachten === 'Y'
            && $product->cLagerVariation !== 'Y'
            && $product->cLagerKleinerNull !== 'Y'
        ) {
            foreach ($product->getAllDependentProducts(true) as $dependent) {
                /** @var Artikel $depProduct */
                $depProduct = $dependent->product;
                if (
                    $depProduct->fPackeinheit
                    * ($qty * $dependent->stockFactor +
                        Frontend::getCart()->getDependentAmount(
                            (int)$depProduct->kArtikel,
                            true
                        )
                    ) > $depProduct->fLagerbestand
                ) {
                    $redirectParam[] = \R_LAGER;
                    break;
                }
            }
        }
        if (!Frontend::getCustomerGroup()->mayViewPrices() || !Frontend::getCustomerGroup()->mayViewCategories()) {
            $redirectParam[] = \R_LOGIN;
        }
        // kein vorbestellbares Produkt, aber mit Erscheinungsdatum in Zukunft
        if ($product->nErscheinendesProdukt && $conf['global_erscheinende_kaeuflich'] === 'N') {
            $redirectParam[] = \R_VORBESTELLUNG;
        }
        // Die maximale Bestellmenge des Artikels wurde überschritten
        if (
            ($product->FunktionsAttribute[\FKT_ATTRIBUT_MAXBESTELLMENGE] ?? 0) > 0
            && ($qty > $product->FunktionsAttribute[\FKT_ATTRIBUT_MAXBESTELLMENGE]
                || ($cart->gibAnzahlEinesArtikels($productID) + $qty) >
                $product->FunktionsAttribute[\FKT_ATTRIBUT_MAXBESTELLMENGE])
        ) {
            $redirectParam[] = \R_MAXBESTELLMENGE;
        }
        // Der Artikel ist unverkäuflich
        if ((int)($product->FunktionsAttribute[\FKT_ATTRIBUT_UNVERKAEUFLICH] ?? 0) === 1) {
            $redirectParam[] = \R_UNVERKAEUFLICH;
        }
        if (isset($product->FunktionsAttribute[\FKT_ATTRIBUT_VOUCHER_FLEX])) {
            $price = (float)Request::postVar(\FKT_ATTRIBUT_VOUCHER_FLEX . 'Value');
            if ($price <= 0) {
                $redirectParam[] = \R_UNVERKAEUFLICH;
            }
        }
        // Preis auf Anfrage
        // verhindert, dass Konfigitems mit Preis=0 aus der Artikelkonfiguration fallen
        // wenn 'Preis auf Anfrage' eingestellt ist
        if (
            $product->bHasKonfig === false
            && !empty($product->isKonfigItem)
            && $product->inWarenkorbLegbar === \INWKNICHTLEGBAR_PREISAUFANFRAGE
        ) {
            $product->inWarenkorbLegbar = 1;
        }
        if (
            ($product->bHasKonfig === false && empty($product->isKonfigItem))
            && (!isset($product->Preise->fVKNetto) || $product->Preise->fVKNetto === 0.0)
            && $conf['global_preis0'] === 'N'
        ) {
            $redirectParam[] = \R_AUFANFRAGE;
        }
        // fehlen zu einer Variation werte?
        foreach ($product->Variationen as $var) {
            if (\in_array(\R_VARWAEHLEN, $redirectParam, true)) {
                break;
            }
            if ($var->cTyp === 'FREIFELD') {
                continue;
            }
            $exists = false;
            foreach ($attributes as $oEigenschaftwerte) {
                $oEigenschaftwerte->kEigenschaft = (int)$oEigenschaftwerte->kEigenschaft;
                if ($oEigenschaftwerte->kEigenschaft !== $var->kEigenschaft) {
                    continue;
                }
                if ($var->cTyp === 'PFLICHT-FREIFELD') {
                    if ($oEigenschaftwerte->cFreifeldWert !== '') {
                        $exists = true;
                    } else {
                        $redirectParam[] = \R_VARWAEHLEN;
                        break;
                    }
                } else {
                    $exists = true;
                    // schau, ob auch genug davon auf Lager
                    $attrValue = new EigenschaftWert((int)$oEigenschaftwerte->kEigenschaftWert);
                    // ist der Eigenschaftwert überhaupt gültig?
                    if ($attrValue->kEigenschaft !== $oEigenschaftwerte->kEigenschaft) {
                        $redirectParam[] = \R_VARWAEHLEN;
                        break;
                    }
                    // schaue, ob genug auf Lager von jeder var
                    if (
                        $product->cLagerBeachten === 'Y'
                        && $product->cLagerVariation === 'Y'
                        && $product->cLagerKleinerNull !== 'Y'
                    ) {
                        if ((float)$attrValue->fPackeinheit === 0.0) {
                            $attrValue->fPackeinheit = 1;
                        }
                        if (
                            $attrValue->fPackeinheit
                            * ($qty + $cart->gibAnzahlEinerVariation($productID, $attrValue->kEigenschaftWert))
                            > $attrValue->fLagerbestand
                        ) {
                            $redirectParam[] = \R_LAGERVAR;
                        }
                    }
                    break;
                }
            }
            if (!$exists) {
                $redirectParam[] = \R_VARWAEHLEN;
                break;
            }
        }
        if (!Form::validateToken($token)) {
            $redirectParam[] = \R_MISSING_TOKEN;
        }
        \executeHook(\HOOK_ADD_TO_CART_CHECK, [
            'product'       => $product,
            'quantity'      => $qty,
            'attributes'    => $attributes,
            'accuracy'      => $accuracy,
            'redirectParam' => &$redirectParam
        ]);

        return $redirectParam;
    }

    /**
     * @param array<mixed> $amounts
     * @former pruefeVariBoxAnzahl
     */
    public static function checkVariboxAmount(array $amounts): bool
    {
        foreach (\array_keys($amounts) as $cKeys) {
            if ((float)$amounts[$cKeys] > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @since 5.0.0
     */
    public static function roundOptionalCurrency(float|int $total, ?Currency $currency = null): float|int
    {
        $factor = ($currency ?? Frontend::getCurrency())->getConversionFactor();

        return self::roundOptional($total * $factor) / $factor;
    }

    /**
     * @since 5.0.0
     */
    public static function roundOptional(float|int $total): float|int
    {
        if (Settings::boolValue(Checkout::ROUND_TOTAL_5)) {
            return \round($total * 20) / 20;
        }

        return $total;
    }

    /**
     * @param int|float|numeric-string $qty
     * @former pruefeWarenkorbStueckliste()
     * @since 5.0.0
     */
    public static function checkCartPartComponent(Artikel $product, int|float|string $qty): ?int
    {
        $partList = Product::isStuecklisteKomponente((int)$product->kArtikel, true);
        if (
            !($product->cLagerBeachten === 'Y'
                && $product->cLagerKleinerNull !== 'Y'
                && ($product->kStueckliste > 0 || $partList))
        ) {
            return null;
        }
        $isComponent = false;
        $components  = null;
        if (isset($partList->kStueckliste)) {
            $isComponent = true;
        } else {
            $components = self::getPartComponent((int)$product->kStueckliste, true);
        }
        foreach (Frontend::getCart()->PositionenArr as $item) {
            if ($item->nPosTyp !== \C_WARENKORBPOS_TYP_ARTIKEL) {
                continue;
            }
            if (
                $isComponent
                && isset($item->Artikel->kStueckliste)
                && $item->Artikel->kStueckliste > 0
                && !\is_bool($partList)
                && ($item->nAnzahl * $partList->fAnzahl + $qty) > $product->fLagerbestand
            ) {
                return \R_LAGER;
            }
            if (!$isComponent && $item->Artikel !== null && \count($components) > 0) {
                if (!empty($item->Artikel->kStueckliste)) {
                    $itemComponents = self::getPartComponent($item->Artikel->kStueckliste, true);
                    foreach ($itemComponents as $component) {
                        $desiredComponentQuantity = $qty * $components[$component->kArtikel]->fAnzahl;
                        $currentComponentStock    = $item->Artikel->fLagerbestand * $component->fAnzahl;
                        if ($desiredComponentQuantity > $currentComponentStock) {
                            return \R_LAGER;
                        }
                    }
                } elseif (
                    isset($components[$item->kArtikel])
                    && (($item->nAnzahl * $components[$item->kArtikel]->fAnzahl) +
                        ($components[$item->kArtikel]->fAnzahl * $qty)) > $item->Artikel->fLagerbestand
                ) {
                    return \R_LAGER;
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, stdClass>
     * @former gibStuecklistenKomponente()
     * @since 5.0.0
     */
    public static function getPartComponent(int $kStueckliste, bool $assoc = false): array
    {
        if ($kStueckliste <= 0) {
            return [];
        }
        $data = Shop::Container()->getDB()->selectAll('tstueckliste', 'kStueckliste', $kStueckliste);
        if (\count($data) === 0) {
            return [];
        }
        if ($assoc === false) {
            return $data;
        }
        $res = [];
        foreach ($data as $item) {
            $res[$item->kArtikel] = $item;
        }

        return $res;
    }

    /**
     * @former checkeKuponWKPos()
     * @since 5.0.0
     */
    public static function checkCouponCartItems(CartItem $item, Kupon|stdClass $coupon): CartItem
    {
        $item->nPosTyp = (int)$item->nPosTyp;
        if (
            $item->nPosTyp !== \C_WARENKORBPOS_TYP_ARTIKEL
            || Frontend::getCart()->posTypEnthalten(\C_WARENKORBPOS_TYP_KUPON)
        ) {
            return $item;
        }
        $categoryQRY = '';
        $customerQRY = '';
        $categoryIDs = [];
        if ($item->Artikel?->kArtikel > 0) {
            $productID = (int)$item->Artikel->kArtikel;
            if (Product::isVariChild($productID)) {
                $productID = Product::getParent($productID);
            }
            $categories = Shop::Container()->getDB()->selectAll('tkategorieartikel', 'kArtikel', $productID);
            foreach ($categories as $category) {
                $category->kKategorie = (int)$category->kKategorie;
                if (!\in_array($category->kKategorie, $categoryIDs, true)) {
                    $categoryIDs[] = $category->kKategorie;
                }
            }
        }
        foreach ($categoryIDs as $id) {
            $categoryQRY .= " OR FIND_IN_SET('" . $id . "', REPLACE(cKategorien, ';', ',')) > 0";
        }
        if (Frontend::getCustomer()->isLoggedIn()) {
            $customerQRY = " OR FIND_IN_SET('" .
                Frontend::getCustomer()->getID() . "', REPLACE(cKunden, ';', ',')) > 0";
        }
        $couponsOK = Shop::Container()->getDB()->getSingleObject(
            "SELECT *
                FROM tkupon
                WHERE cAktiv = 'Y'
                    AND dGueltigAb <= NOW()
                    AND (dGueltigBis IS NULL OR dGueltigBis > NOW())
                    AND fMindestbestellwert <= :minAmount
                    AND (kKundengruppe = -1
                        OR kKundengruppe = 0
                        OR kKundengruppe = :cgID)
                    AND (nVerwendungen = 0
                        OR nVerwendungen > nVerwendungenBisher)
                    AND (cArtikel = '' OR FIND_IN_SET(:artNO, REPLACE(cArtikel, ';', ',')) > 0)
                    AND (cHersteller = '-1' OR FIND_IN_SET(:manuf, REPLACE(cHersteller, ';', ',')) > 0)
                    AND (cKategorien = '' OR cKategorien = '-1' " . $categoryQRY . ")
                    AND (cKunden = '' OR cKunden = '-1' " . $customerQRY . ')
                    AND kKupon = :couponID',
            [
                'minAmount' => Frontend::getCart()->gibGesamtsummeWaren(true, false),
                'cgID'      => Frontend::getCustomerGroup()->getID(),
                'artNO'     => \str_replace('%', '\%', (string)($item->Artikel?->cArtNr)),
                'manuf'     => (string)($item->Artikel?->kHersteller),
                'couponID'  => (int)$coupon->kKupon
            ]
        );
        if (
            $couponsOK !== null
            && $couponsOK->kKupon > 0
            && $couponsOK->cWertTyp === 'prozent'
        ) {
            $item->fPreisEinzelNetto -= ($item->fPreisEinzelNetto / 100) * $coupon->fWert;
            $item->fPreis            -= ($item->fPreis / 100) * $coupon->fWert;
            $item->cHinweis          = $coupon->cName .
                ' (' . \str_replace('.', ',', $coupon->fWert) .
                '% ' . Shop::Lang()->get('discount') . ')';

            foreach ($item->WarenkorbPosEigenschaftArr as $attribute) {
                if (isset($attribute->fAufpreis) && (float)$attribute->fAufpreis > 0) {
                    $attribute->fAufpreis -= ((float)$attribute->fAufpreis / 100) * $coupon->fWert;
                }
            }
            foreach (Frontend::getCurrencies() as $currency) {
                $currencyName                                  = $currency->getName();
                $item->cGesamtpreisLocalized[0][$currencyName] = Preise::getLocalizedPriceString(
                    Tax::getGross(
                        $item->fPreis * $item->nAnzahl,
                        CartItem::getTaxRate($item)
                    ),
                    $currency
                );
                $item->cGesamtpreisLocalized[1][$currencyName] = Preise::getLocalizedPriceString(
                    $item->fPreis * $item->nAnzahl,
                    $currency
                );
                $item->cEinzelpreisLocalized[0][$currencyName] = Preise::getLocalizedPriceString(
                    Tax::getGross($item->fPreis, CartItem::getTaxRate($item)),
                    $currency
                );
                $item->cEinzelpreisLocalized[1][$currencyName] = Preise::getLocalizedPriceString(
                    $item->fPreis,
                    $currency
                );
            }
        }

        return $item;
    }

    /**
     * @former checkSetPercentCouponWKPos()
     * @since 5.0.0
     */
    public static function checkSetPercentCouponWKPos(CartItem $cartItem, Kupon $coupon): stdClass
    {
        $item              = new stdClass();
        $item->fPreis      = 0.0;
        $item->cName       = '';
        $cartItem->nPosTyp = (int)$cartItem->nPosTyp;
        if ($cartItem->nPosTyp !== \C_WARENKORBPOS_TYP_ARTIKEL) {
            return $item;
        }
        $categoryQRY = '';
        $customerQRY = '';
        $categoryIDs = [];
        if ($cartItem->Artikel?->kArtikel > 0) {
            $productID = (int)$cartItem->Artikel->kArtikel;
            if (Product::isVariChild($productID)) {
                $productID = Product::getParent($productID);
            }
            $categories = Shop::Container()->getDB()->selectAll(
                'tkategorieartikel',
                'kArtikel',
                $productID,
                'kKategorie'
            );
            foreach ($categories as $category) {
                $category->kKategorie = (int)$category->kKategorie;
                if (!\in_array($category->kKategorie, $categoryIDs, true)) {
                    $categoryIDs[] = $category->kKategorie;
                }
            }
        }
        foreach ($categoryIDs as $id) {
            $categoryQRY .= " OR FIND_IN_SET('" . $id . "', REPLACE(cKategorien, ';', ',')) > 0";
        }
        if (Frontend::getCustomer()->isLoggedIn()) {
            $customerQRY = " OR FIND_IN_SET('" .
                Frontend::getCustomer()->getID() . "', REPLACE(cKunden, ';', ',')) > 0";
        }
        $couponOK = Shop::Container()->getDB()->getSingleObject(
            "SELECT *
                FROM tkupon
                WHERE cAktiv = 'Y'
                    AND dGueltigAb <= NOW()
                    AND (dGueltigBis IS NULL OR dGueltigBis > NOW())
                    AND fMindestbestellwert <= :minAmount
                    AND (kKundengruppe = -1
                        OR kKundengruppe = 0
                        OR kKundengruppe = :cgID)
                    AND (nVerwendungen = 0 OR nVerwendungen > nVerwendungenBisher)
                    AND (cArtikel = '' OR FIND_IN_SET(:artNo, REPLACE(cArtikel, ';', ',')) > 0)
                    AND (cHersteller = '-1' OR FIND_IN_SET(:manuf, REPLACE(cHersteller, ';', ',')) > 0)
                    AND (cKategorien = '' OR cKategorien = '-1' " . $categoryQRY . ")
                    AND (cKunden = '' OR cKunden = '-1' " . $customerQRY . ')
                    AND kKupon = :couponID',
            [
                'minAmount' => Frontend::getCart()->gibGesamtsummeWaren(true, false),
                'cgID'      => Frontend::getCustomerGroup()->getID(),
                'artNo'     => \str_replace('%', '\%', (string)($cartItem->Artikel?->cArtNr)),
                'manuf'     => (string)($cartItem->Artikel?->kHersteller),
                'couponID'  => $coupon->kKupon
            ]
        );
        if ($couponOK !== null && $couponOK->kKupon > 0 && $couponOK->cWertTyp === 'prozent') {
            $item->fPreis = $cartItem->fPreis *
                Frontend::getCurrency()->getConversionFactor() *
                $cartItem->nAnzahl *
                ((100 + CartItem::getTaxRate($cartItem)) / 100);
            $item->cName  = $cartItem->cName;
        }

        return $item;
    }

    /**
     * @param array<mixed> $variBoxCounts
     * @former fuegeVariBoxInWK()
     * @since 5.0.0
     */
    public static function addVariboxToCart(
        array $variBoxCounts,
        int $productID,
        bool $isParent,
        bool $isVariMatrix = false
    ): void {
        if (\count($variBoxCounts) === 0) {
            return;
        }
        $parentID   = $productID;
        $attributes = [];
        unset($_SESSION['variBoxAnzahl_arr']);

        foreach (\array_keys($variBoxCounts) as $key) {
            if ((float)$variBoxCounts[$key] <= 0) {
                continue;
            }
            if ($isVariMatrix) {
                // varkombi matrix - all keys are IDs of a concrete child
                $productID                       = (int)$key;
                $properties                      = Product::getPropertiesForVarCombiArticle($productID, $parentID);
                $variKombi                       = new stdClass();
                $variKombi->fAnzahl              = (float)$variBoxCounts[$key];
                $variKombi->kEigenschaft_arr     = \array_keys($properties);
                $variKombi->kEigenschaftWert_arr = \array_values($properties);

                $_POST['eigenschaftwert']            = $properties;
                $_SESSION['variBoxAnzahl_arr'][$key] = $variKombi;
                $attributes[$key]                    = new stdClass();
                $attributes[$key]->kArtikel          = $productID;
                $attributes[$key]->oEigenschaft_arr  = \array_map(static function ($a) use ($properties): stdClass {
                    return (object)[
                        'kEigenschaft'     => $a,
                        'kEigenschaftWert' => $properties[$a],
                    ];
                }, $variKombi->kEigenschaft_arr);
            } elseif (\preg_match('/([\d:]+)?_([\d:]+)/', $key, $hits) && \count($hits) === 3) {
                if (empty($hits[1])) {
                    // 1-dimensional matrix - key is combination of property id and property value
                    unset($hits[1]);
                    $n = 1;
                } else {
                    // 2-dimensional matrix - key is set of combinations of property id and property value
                    $n = 2;
                }
                \array_shift($hits);

                $variKombi          = new stdClass();
                $variKombi->fAnzahl = (float)$variBoxCounts[$key];
                for ($i = 0; $i < $n; $i++) {
                    [$propertyID, $propertyValue] = \explode(':', $hits[$i]);

                    $variKombi->{'cVariation' . $i}       = Text::filterXSS($hits[$i]);
                    $variKombi->{'kEigenschaft' . $i}     = (int)$propertyID;
                    $variKombi->{'kEigenschaftWert' . $i} = (int)$propertyValue;

                    $_POST['eigenschaftwert_' . Text::filterXSS($propertyID)] = (int)$propertyValue;
                }

                $_SESSION['variBoxAnzahl_arr'][$key] = $variKombi;
                $attributes[$key]                    = new stdClass();
                $attributes[$key]->oEigenschaft_arr  = [];
                $attributes[$key]->kArtikel          = 0;

                if ($isParent) {
                    $productID                          = Product::getArticleForParent($parentID);
                    $attributes[$key]->oEigenschaft_arr = Product::getSelectedPropertiesForVarCombiArticle($productID);
                } else {
                    $attributes[$key]->oEigenschaft_arr = Product::getSelectedPropertiesForArticle($productID);
                }
                $attributes[$key]->kArtikel = $productID;
            }
        }

        if (\count($attributes) === 0) {
            return;
        }
        $errorAtChild   = $productID;
        $qty            = 0;
        $errors         = [];
        $defaultOptions = Artikel::getDefaultOptions();
        $db             = Shop::Container()->getDB();
        $cache          = Shop::Container()->getCache();
        $customerGroup  = Frontend::getCustomerGroup();
        $currency       = Frontend::getCurrency();
        foreach ($attributes as $key => $attribute) {
            $product = new Artikel($db, $customerGroup, $currency, $cache);
            $product->fuelleArtikel($attribute->kArtikel, $defaultOptions);
            $redirects = self::addToCartCheck(
                $product,
                (float)$variBoxCounts[$key],
                $attribute->oEigenschaft_arr
            );

            $_SESSION['variBoxAnzahl_arr'][$key]->bError = false;
            if (\count($redirects) > 0) {
                $qty          = (float)$variBoxCounts[$key];
                $errorAtChild = $attribute->kArtikel;
                foreach ($redirects as $redirectID) {
                    if (!\in_array($redirectID, $errors, true)) {
                        $errors[] = $redirectID;
                    }
                }
                $_SESSION['variBoxAnzahl_arr'][$key]->bError = true;
            }
        }

        if (\count($errors) > 0) {
            $product = new Artikel($db, $customerGroup, $currency, $cache);
            $product->fuelleArtikel($isParent ? $parentID : $productID, $defaultOptions);
            $redirectURL = $product->cURLFull . '?a=';
            if ($isParent) {
                $redirectURL .= $parentID;
                $redirectURL .= '&child=' . $errorAtChild;
            } else {
                $redirectURL .= $productID;
            }
            if ($qty > 0) {
                $redirectURL .= '&n=' . $qty;
            }
            $redirectURL .= '&r=' . \implode(',', $errors);
            \header('Location: ' . $redirectURL, true, 302);
            exit;
        }

        foreach ($attributes as $key => $attribute) {
            if (!$_SESSION['variBoxAnzahl_arr'][$key]->bError) {
                //#8224, #7482 -> do not call setzePositionsPreise() in loop @ Wanrekob::fuegeEin()
                self::addProductIDToCart(
                    $attribute->kArtikel,
                    (float)$variBoxCounts[$key],
                    $attribute->oEigenschaft_arr,
                    0,
                    false,
                    0,
                    null,
                    false
                );
            }
        }

        Frontend::getCart()->setzePositionsPreise();
        unset($_SESSION['variBoxAnzahl_arr']);
        Frontend::getCart()->redirectTo();
    }

    /**
     * @param float|int|numeric-string $qty
     * @param array<mixed>             $attrValues
     * @former fuegeEinInWarenkorb()
     * @since 5.0.0
     */
    public static function addProductIDToCart(
        int $productID,
        float|int|string $qty,
        array $attrValues = [],
        int $redirect = 0,
        false|string $unique = '',
        int $configItemID = 0,
        ?stdClass $options = null,
        bool $setzePositionsPreise = true,
        string $responsibility = 'core'
    ): bool {
        if (!($qty > 0 && ($productID > 0 || ($productID === 0 && !empty($configItemID) && !empty($unique))))) {
            return false;
        }
        $product = new Artikel();
        $options = $options ?? Artikel::getDefaultOptions();
        $product->fuelleArtikel($productID, $options);
        if (isset($product->FunktionsAttribute[\FKT_ATTRIBUT_VOUCHER_FLEX])) {
            $price = (float)Request::postVar(\FKT_ATTRIBUT_VOUCHER_FLEX . 'Value');
            if ($price > 0 && $product->Preise !== null) {
                $product->Preise->fVKNetto = Tax::getNet($price, $product->Preise->fUst, 4);
                $product->Preise->berechneVKs();
                $unique = \uniqid((string)$price, true);
            }
        }
        if ($product->cTeilbar !== 'Y') {
            $qty = \max((int)$qty, 1);
        }
        \executeHook(\HOOK_CARTHELPER_ADD_PRODUCT_ID_TO_CART, [
            'product' => $product,
            'qty'     => &$qty
        ]);
        $redirectParam = self::addToCartCheck($product, $qty, $attrValues);
        // verhindert, dass Konfigitems mit Preis=0 aus der Artikelkonfiguration fallen
        // wenn 'Preis auf Anfrage' eingestellt ist
        if (!empty($configItemID) && isset($redirectParam[0]) && $redirectParam[0] === \R_AUFANFRAGE) {
            unset($redirectParam[0]);
        }

        if (\count($redirectParam) > 0) {
            if (isset($_SESSION['variBoxAnzahl_arr'])) {
                return false;
            }
            if ($redirect === 0) {
                $con = (!\str_contains($product->cURLFull ?? '', '?')) ? '?' : '&';
                if ($product->kEigenschaftKombi > 0) {
                    $url = empty($product->cURLFull)
                        ? (Shop::getURL() . '/?a=' . $product->kVaterArtikel . '&a2=' . $product->kArtikel . '&')
                        : ($product->cURLFull . $con);
                } else {
                    $url = empty($product->cURLFull)
                        ? (Shop::getURL() . '/?a=' . $product->kArtikel . '&')
                        : ($product->cURLFull . $con);
                }
                \header('Location: ' . $url . 'n=' . $qty . '&r=' . \implode(',', $redirectParam), true, 302);
                exit;
            }

            return false;
        }
        Frontend::getCart()
            ->fuegeEin(
                $productID,
                $qty,
                $attrValues,
                1,
                $unique,
                $configItemID,
                false,
                $responsibility
            )
            ->loescheSpezialPos(\C_WARENKORBPOS_TYP_VERSANDPOS)
            ->loescheSpezialPos(\C_WARENKORBPOS_TYP_VERSANDZUSCHLAG)
            ->loescheSpezialPos(\C_WARENKORBPOS_TYP_VERSAND_ARTIKELABHAENGIG)
            ->loescheSpezialPos(\C_WARENKORBPOS_TYP_ZAHLUNGSART)
            ->loescheSpezialPos(\C_WARENKORBPOS_TYP_ZINSAUFSCHLAG)
            ->loescheSpezialPos(\C_WARENKORBPOS_TYP_BEARBEITUNGSGEBUEHR)
            ->loescheSpezialPos(\C_WARENKORBPOS_TYP_NEUKUNDENKUPON)
            ->loescheSpezialPos(\C_WARENKORBPOS_TYP_NACHNAHMEGEBUEHR);

        Kupon::resetNewCustomerCoupon(false);
        if ($setzePositionsPreise) {
            Frontend::getCart()->setzePositionsPreise();
        }
        unset(
            $_SESSION['VersandKupon'],
            $_SESSION['Versandart'],
            $_SESSION['Zahlungsart']
        );
        // Wenn Kupon vorhanden und der cWertTyp prozentual ist, dann verwerfen und neu anlegen
        Kupon::reCheck();
        // avoid recursion when this method was called by the persistent cart..
        if (!isset($_POST['login']) && !isset($_POST['TwoFA_code']) && !isset($_REQUEST['basket2Pers'])) {
            $persCart = PersistentCart::getInstance(Frontend::getCustomer()->getID());
            $persCart->check($productID, $qty, $attrValues, $unique, $configItemID);
        }
        Upload::setUploadCheckNeeded();
        Shop::Smarty()
            ->assign('cartNote', Shop::Lang()->get('basketAdded', 'messages'))
            ->assign('bWarenkorbHinzugefuegt', true)
            ->assign('bWarenkorbAnzahl', $qty);
        Campaign::setCampaignAction(\KAMPAGNE_DEF_WARENKORB, $productID, $qty);
        Frontend::getCart()->redirectTo((bool)$redirect, $unique);

        return true;
    }

    /**
     * @param array<mixed> $items
     * @former loescheWarenkorbPositionen()
     * @since 5.0.0
     */
    public static function deleteCartItems(array $items, bool $removeShippingCoupon = true): void
    {
        $cart    = Frontend::getCart();
        $uniques = [];
        foreach ($items as $item) {
            if (!isset($cart->PositionenArr[$item])) {
                return;
            }
            if (
                $cart->PositionenArr[$item]->nPosTyp !== \C_WARENKORBPOS_TYP_ARTIKEL
                && $cart->PositionenArr[$item]->nPosTyp !== \C_WARENKORBPOS_TYP_GRATISGESCHENK
            ) {
                return;
            }
            $unique = $cart->PositionenArr[$item]->cUnique;
            if (!empty($unique) && $cart->PositionenArr[$item]->kKonfigitem > 0) {
                return;
            }
            \executeHook(\HOOK_WARENKORB_LOESCHE_POSITION, [
                'nPos'     => $item,
                'position' => &$cart->PositionenArr[$item]
            ]);
            Upload::deleteArtikelUploads((int)$cart->PositionenArr[$item]->kArtikel);

            $uniques[] = $unique;

            unset($cart->PositionenArr[$item]);
        }
        $cart->PositionenArr = \array_merge($cart->PositionenArr);
        foreach ($uniques as $unique) {
            if (empty($unique)) {
                continue;
            }
            $itemCount = \count($cart->PositionenArr);
            /** @noinspection ForeachInvariantsInspection */
            for ($i = 0; $i < $itemCount; $i++) {
                if (isset($cart->PositionenArr[$i]->cUnique) && $cart->PositionenArr[$i]->cUnique === $unique) {
                    unset($cart->PositionenArr[$i]);
                    $cart->PositionenArr = \array_merge($cart->PositionenArr);
                    $i                   = -1;
                }
            }
        }
        self::deleteAllSpecialItems($removeShippingCoupon);
        if (!$cart->posTypEnthalten(\C_WARENKORBPOS_TYP_ARTIKEL)) {
            unset($_SESSION['Kupon']);
            $_SESSION['Warenkorb'] = new Cart();
        }
        require_once \PFAD_ROOT . \PFAD_INCLUDES . 'bestellvorgang_inc.php';
        self::freeGiftStillValid($cart);
        if (Frontend::getCustomer()->getID() > 0) {
            PersistentCart::getInstance(Frontend::getCustomer()->getID())->entferneAlles()->bauePersVonSession();
        }
    }

    /**
     * @former loescheWarenkorbPosition()
     * @since 5.0.0
     */
    public static function deleteCartItem(int $index): void
    {
        self::deleteCartItems([$index]);
    }

    private static function checkForDrops(Cart $cart): bool
    {
        // wurden Positionen gelöscht?
        $drop = null;
        $post = false;
        if (Request::postVar('dropPos') === 'assetToUse') {
            $_SESSION['Bestellung']->GuthabenNutzen   = false;
            $_SESSION['Bestellung']->fGuthabenGenutzt = 0;
            unset($_POST['dropPos']);
        }
        if (isset($_POST['dropPos'])) {
            $drop = (int)$_POST['dropPos'];
            $post = true;
        } elseif (isset($_GET['dropPos'])) {
            $drop = (int)$_GET['dropPos'];
        }
        if ($drop !== null) {
            self::deleteCartItem($drop);
            self::freeGiftStillValid($cart);
            if ($post) {
                \header(
                    'Location: ' . Shop::Container()->getLinkService()->getStaticRoute(
                        'warenkorb.php',
                        true,
                        true
                    ),
                    true,
                    303
                );
                exit;
            }

            return true;
        }

        return false;
    }

    /**
     * @former uebernehmeWarenkorbAenderungen()
     * @since 5.0.0
     */
    public static function applyCartChanges(): void
    {
        unset($_SESSION['cPlausi_arr'], $_SESSION['cPost_arr']);
        // Gratis Geschenk wurde hinzugefuegt
        if (isset($_POST['gratishinzufuegen'])) {
            return;
        }
        $cart = Frontend::getCart();
        if (self::checkForDrops($cart) === true) {
            return;
        }
        // wurde WK aktualisiert?
        if (empty($_POST['anzahl'])) {
            return;
        }
        $db            = Shop::Container()->getDB();
        $cache         = Shop::Container()->getCache();
        $updated       = false;
        $freeGiftID    = 0;
        $cartNotices   = $_SESSION['Warenkorbhinweise'] ?? [];
        $options       = Artikel::getDefaultOptions();
        $customerGroup = Frontend::getCustomerGroup();
        $currency      = Frontend::getCurrency();
        foreach ($cart->PositionenArr as $i => $item) {
            $item->kArtikel = (int)$item->kArtikel;
            if ($item->nPosTyp === \C_WARENKORBPOS_TYP_ARTIKEL) {
                if ($item->kArtikel === 0) {
                    continue;
                }
                // stückzahlen verändert?
                if (isset($_POST['anzahl'][$i])) {
                    $product = new Artikel($db, $customerGroup, $currency, $cache);
                    $valid   = true;
                    $product->fuelleArtikel($item->kArtikel, $options);
                    $quantity = (float)\str_replace(',', '.', $_POST['anzahl'][$i]);
                    if ($product->cTeilbar !== 'Y') {
                        $quantity = \max((int)$quantity, 1);
                    }
                    if (
                        $product->fAbnahmeintervall > 0
                        && !self::isMultiple($quantity, (float)$product->fAbnahmeintervall)
                    ) {
                        $valid         = false;
                        $cartNotices[] = Shop::Lang()->get('wkPurchaseintervall', 'messages');
                    }
                    if (
                        $quantity + $cart->gibAnzahlEinesArtikels($item->kArtikel, $i)
                        < $product->fMindestbestellmenge
                    ) {
                        $valid         = false;
                        $cartNotices[] = Texts::minOrderQTY($product, $quantity);
                    }
                    if (
                        $product->cLagerBeachten === 'Y'
                        && $product->cLagerVariation !== 'Y'
                        && $product->cLagerKleinerNull !== 'Y'
                    ) {
                        $available = true;
                        foreach ($product->getAllDependentProducts(true) as $dependent) {
                            /** @var Artikel $depProduct */
                            $depProduct = $dependent->product;
                            if (
                                $depProduct->fPackeinheit * ($quantity * $dependent->stockFactor
                                    + Frontend::getCart()->getDependentAmount(
                                        (int)$depProduct->kArtikel,
                                        true,
                                        [$i]
                                    )) > $depProduct->fLagerbestand
                            ) {
                                $valid     = false;
                                $available = false;
                                break;
                            }
                        }

                        if ($available === false) {
                            $msg = Shop::Lang()->get('quantityNotAvailable', 'messages');
                            if (!isset($cartNotices) || !\in_array($msg, $cartNotices, true)) {
                                $cartNotices[] = $msg;
                            }
                            $_SESSION['Warenkorb']->PositionenArr[$i]->nAnzahl =
                                $_SESSION['Warenkorb']->getMaxAvailableAmount($i, $quantity);
                            if (
                                isset($product->FunktionsAttribute[\FKT_ATTRIBUT_MAXBESTELLMENGE])
                                && $product->FunktionsAttribute[\FKT_ATTRIBUT_MAXBESTELLMENGE] > 0
                            ) {
                                $_SESSION['Warenkorb']->PositionenArr[$i]->nAnzahl = \min(
                                    $_SESSION['Warenkorb']->getMaxAvailableAmount($i, $quantity),
                                    $product->FunktionsAttribute[\FKT_ATTRIBUT_MAXBESTELLMENGE]
                                );
                            }
                        }
                    }
                    // maximale Bestellmenge des Artikels beachten
                    if (
                        isset($product->FunktionsAttribute[\FKT_ATTRIBUT_MAXBESTELLMENGE])
                        && $product->FunktionsAttribute[\FKT_ATTRIBUT_MAXBESTELLMENGE] > 0
                        && $quantity > $product->FunktionsAttribute[\FKT_ATTRIBUT_MAXBESTELLMENGE]
                    ) {
                        $valid         = false;
                        $cartNotices[] = Shop::Lang()->get('wkMaxorderlimit', 'messages');
                    }
                    if (
                        $product->cLagerBeachten === 'Y'
                        && $product->cLagerVariation === 'Y'
                        && $product->cLagerKleinerNull !== 'Y'
                    ) {
                        foreach ($item->WarenkorbPosEigenschaftArr as $data) {
                            $id    = (int)$data->kEigenschaftWert;
                            $value = new EigenschaftWert($id, $db);
                            if (
                                $value->fPackeinheit
                                * ($quantity + $cart->gibAnzahlEinerVariation($item->kArtikel, $id, $i))
                                > $value->fLagerbestand
                            ) {
                                $cartNotices[] = Shop::Lang()->get(
                                    'quantityNotAvailableVar',
                                    'messages'
                                );
                                $valid         = false;
                                break;
                            }
                        }
                    }

                    if ($valid) {
                        $item->nAnzahl = $quantity;
                        $item->fPreis  = $product->gibPreis(
                            $item->nAnzahl,
                            $item->WarenkorbPosEigenschaftArr,
                            0,
                            $item->cUnique
                        );
                        $item->setzeGesamtpreisLocalized();
                        $item->fGesamtgewicht = $item->gibGesamtgewicht();

                        $updated = true;
                    }
                }
                // Grundpreise bei Staffelpreisen
                if (isset($item->Artikel->fVPEWert) && $item->Artikel->fVPEWert > 0) {
                    $nLast = 0;
                    for ($j = 1; $j <= 5; $j++) {
                        $cStaffel = 'nAnzahl' . $j;
                        if (
                            isset($item->Artikel->Preise->$cStaffel)
                            && $item->Artikel->Preise->$cStaffel > 0
                            && $item->Artikel->Preise->$cStaffel <= $item->nAnzahl
                        ) {
                            $nLast = $j;
                        }
                    }
                    if ($nLast > 0) {
                        $cStaffel = 'fPreis' . $nLast;
                        $item->Artikel->baueVPE($item->Artikel->Preise->$cStaffel);
                    } else {
                        $item->Artikel->baueVPE();
                    }
                }
            } elseif ($item->nPosTyp === \C_WARENKORBPOS_TYP_GRATISGESCHENK) {
                $freeGiftID = $item->kArtikel;
            }
        }
        $_SESSION['Warenkorbhinweise'] = $cartNotices;
        // positionen mit nAnzahl = 0 müssen gelöscht werden
        $cart->loescheNullPositionen();
        if (!$cart->posTypEnthalten(\C_WARENKORBPOS_TYP_ARTIKEL)) {
            $_SESSION['Warenkorb'] = new Cart();
            $cart                  = $_SESSION['Warenkorb'];
        }
        if ($updated) {
            $tmpCoupon   = null;
            $sessCoupont = $_SESSION['Kupon'] ?? null;
            // existiert ein proz. Kupon, der auf die neu eingefügte Pos greift?
            if (
                $sessCoupont !== null
                && $sessCoupont->cWertTyp === 'prozent'
                && (int)$sessCoupont->nGanzenWKRabattieren === 0
                && $cart->gibGesamtsummeWarenExt(
                    [\C_WARENKORBPOS_TYP_ARTIKEL],
                    true
                ) >= $sessCoupont->fMindestbestellwert
            ) {
                $tmpCoupon = $sessCoupont;
            }
            self::deleteAllSpecialItems();
            if (isset($tmpCoupon->kKupon) && $tmpCoupon->kKupon > 0) {
                /** @var Kupon $sessCoupont */
                $sessCoupont       = $tmpCoupon;
                $_SESSION['Kupon'] = $tmpCoupon;
                foreach ($cart->PositionenArr as $i => $cartItem) {
                    $cart->PositionenArr[$i] = self::checkCouponCartItems($cartItem, $sessCoupont);
                }
            }
            CouponValidator::validateNewCustomerCoupon(Frontend::getCustomer());
        }
        $cart->setzePositionsPreise();
        // Gesamtsumme Warenkorb < Gratisgeschenk && Gratisgeschenk in den Pos?
        if ($freeGiftID > 0) {
            // Prüfen, ob der Artikel wirklich ein Gratis Geschenk ist
            $gift = Shop::Container()->getFreeGiftService()->getFreeGiftProduct(
                $freeGiftID,
                $cart->gibGesamtsummeWarenExt([\C_WARENKORBPOS_TYP_ARTIKEL], true),
                $customerGroup->getID(),
            );
            if ($gift === null || empty($gift->productID)) {
                $cart->loescheSpezialPos(\C_WARENKORBPOS_TYP_GRATISGESCHENK);
            }
        }
        if (isset($_SESSION['Kunde']->kKunde) && $_SESSION['Kunde']->kKunde > 0) {
            $persCart = PersistentCart::getInstance($_SESSION['Kunde']->kKunde);
            $persCart->entferneAlles()->bauePersVonSession();
        }
    }

    /**
     * @former checkeSchnellkauf()
     * @since 5.0.0
     */
    public static function checkQuickBuy(): string
    {
        $msg = '';
        if (empty($_POST['ean']) || Request::pInt('schnellkauf') <= 0) {
            return $msg;
        }
        $ean         = Text::htmlentities(Text::filterXSS((string)$_POST['ean']));
        $msg         = Shop::Lang()->get('eanNotExist') . ' ' . $ean;
        $db          = Shop::Container()->getDB();
        $productData = $db->select('tartikel', 'cArtNr', $ean);
        if ($productData === null || empty($productData->kArtikel)) {
            $productData = $db->select('tartikel', 'cBarcode', $ean);
        }
        if ($productData !== null && $productData->kArtikel > 0) {
            $id      = (int)$productData->kArtikel;
            $product = (new Artikel($db))->fuelleArtikel($id, Artikel::getDefaultOptions());
            if (
                $product !== null
                && $product->kArtikel > 0
                && self::addProductIDToCart($id, 1, Product::getSelectedPropertiesForArticle($id))
            ) {
                $msg = $productData->cName . ' ' . Shop::Lang()->get('productAddedToCart');
            }
        }

        return $msg;
    }

    /**
     * @former loescheAlleSpezialPos()
     * @since 5.0.0
     */
    public static function deleteAllSpecialItems(bool $removeShippingCoupon = true): void
    {
        Frontend::getCart()
            ->loescheSpezialPos(\C_WARENKORBPOS_TYP_ZAHLUNGSART)
            ->loescheSpezialPos(\C_WARENKORBPOS_TYP_ZINSAUFSCHLAG)
            ->loescheSpezialPos(\C_WARENKORBPOS_TYP_BEARBEITUNGSGEBUEHR)
            ->loescheSpezialPos(\C_WARENKORBPOS_TYP_VERSANDPOS)
            ->loescheSpezialPos(\C_WARENKORBPOS_TYP_VERSANDZUSCHLAG)
            ->loescheSpezialPos(\C_WARENKORBPOS_TYP_NACHNAHMEGEBUEHR)
            ->loescheSpezialPos(\C_WARENKORBPOS_TYP_VERSAND_ARTIKELABHAENGIG)
            ->loescheSpezialPos(\C_WARENKORBPOS_TYP_VERPACKUNG)
            ->checkIfCouponIsStillValid();
        unset(
            $_SESSION['Versandart'],
            $_SESSION['Verpackung'],
            $_SESSION['Zahlungsart']
        );
        if ($removeShippingCoupon) {
            unset(
                $_SESSION['VersandKupon'],
                $_SESSION['oVersandfreiKupon']
            );
        }
        Kupon::resetNewCustomerCoupon();
        Kupon::reCheck();

        \executeHook(\HOOK_WARENKORB_LOESCHE_ALLE_SPEZIAL_POS);

        Frontend::getCart()->setzePositionsPreise();
    }

    /**
     * @former gibXSelling()
     * @since 5.0.0
     */
    public static function getXSelling(): stdClass
    {
        $xSelling  = new stdClass();
        $conf      = Shop::getSettings([\CONF_KAUFABWICKLUNG]);
        $cartItems = Frontend::getCart()->PositionenArr;
        if ($conf['kaufabwicklung']['warenkorb_xselling_anzeigen'] !== 'Y' || \count($cartItems) === 0) {
            return $xSelling;
        }
        $productIDs = map(
            filter($cartItems, fn($p): bool => isset($p->Artikel->kArtikel)),
            fn($p): int => (int)$p->Artikel->kArtikel
        );
        if (\count($productIDs) === 0) {
            return $xSelling;
        }
        $db          = Shop::Container()->getDB();
        $cache       = Shop::Container()->getCache();
        $xsellHelper = new XSelling($db);
        $xsellData   = $xsellHelper->getXSellingCart(
            $productIDs,
            (int)$conf['kaufabwicklung']['warenkorb_xselling_anzahl']
        );

        \executeHook(\HOOK_CARTHELPER_GET_XSELLING, [
            'oArtikelKey_arr' => $productIDs,
            'xsellData'       => &$xsellData
        ]);

        if (\count($xsellData) === 0) {
            return $xSelling;
        }
        $xSelling->Kauf            = new stdClass();
        $xSelling->Kauf->Artikel   = [];
        $defaultOptions            = Artikel::getDefaultOptions();
        $defaultOptions->nShipping = 0;
        $customerGroup             = Frontend::getCustomerGroup();
        $currency                  = Frontend::getCurrency();
        foreach ($xsellData as $productID) {
            $product = new Artikel($db, $customerGroup, $currency, $cache);
            $product->fuelleArtikel($productID, $defaultOptions);
            if ($product->kArtikel > 0 && $product->aufLagerSichtbarkeit()) {
                $xSelling->Kauf->Artikel[] = $product;
            }
        }

        return $xSelling;
    }

    /**
     * @return Artikel[]
     * @former gibGratisGeschenke()
     * @since 5.0.0
     * @deprecated since 5.4.0
     */
    public static function getFreeGifts(): array
    {
        \trigger_error(
            __METHOD__ . ' is deprecated. Use Shop::Container()->getFreeGiftService()'
            . '->getFreeGiftProducts()->getProductArray() instead.',
            \E_USER_DEPRECATED
        );
        return Shop::Container()->getFreeGiftService()->getFreeGifts()->getProductArray();
    }

    /**
     * Schaut nach ob eine Bestellmenge > Lagersbestand ist und falls dies erlaubt ist, gibt es einen Hinweis
     *
     * @param array<string, mixed> $conf
     * @return string
     * @former pruefeBestellMengeUndLagerbestand()
     * @since 5.0.0
     */
    public static function checkOrderAmountAndStock(array $conf = []): string
    {
        $cart     = Frontend::getCart();
        $notice   = '';
        $name     = '';
        $exists   = false;
        $langCode = Shop::getLanguageCode();
        foreach ($cart->PositionenArr as $item) {
            if (
                $item->nPosTyp === \C_WARENKORBPOS_TYP_ARTIKEL
                && isset($item->Artikel)
                && $item->Artikel->cLagerBeachten === 'Y'
                && $item->Artikel->cLagerKleinerNull === 'Y'
                && $conf['global']['global_lieferverzoegerung_anzeigen'] === 'Y'
                && $item->nAnzahl > $item->Artikel->fLagerbestand
            ) {
                $exists = true;
                $name   .= '<li>'
                    . $item->getName($langCode)
                    . '</li>';
            }
        }
        $cart->cEstimatedDelivery = $cart->getEstimatedDeliveryTime();
        if ($exists) {
            $notice = \sprintf(
                Shop::Lang()->get('orderExpandInventory', 'basket'),
                '<ul>' . $name . '</ul>'
            );
            $notice .= '<strong>' .
                Shop::Lang()->get('shippingTime', 'global') .
                ': ' .
                $cart->cEstimatedDelivery .
                '</strong>';
        }

        return $notice;
    }

    /**
     * Nachschauen ob beim Konfigartikel alle Pflichtkomponenten vorhanden sind, andernfalls löschen
     * @former validiereWarenkorbKonfig()
     * @since 5.0.0
     */
    public static function validateCartConfig(): void
    {
        Configurator::postcheckCart(Frontend::getCart());
    }

    public static function isMultiple(float $quantity, float $multiple): bool
    {
        $eps      = 1E-10;
        $residual = $quantity / $multiple;

        return \abs($residual - \round($residual)) < $eps;
    }

    /**
     * @param Kupon      $coupon
     * @param CartItem[] $items
     * @return bool
     * @former warenkorbKuponFaehigArtikel()
     * @since 5.2.0
     */
    public static function cartHasCouponValidProducts(Kupon $coupon, array $items): bool
    {
        if (empty($coupon->cArtikel)) {
            return true;
        }
        foreach ($items as $item) {
            if (
                $item->nPosTyp === \C_WARENKORBPOS_TYP_ARTIKEL
                && \preg_match('/;' . \preg_quote((string)$item->Artikel?->cArtNr, '/') . ';/i', $coupon->cArtikel)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Kupon      $coupon
     * @param CartItem[] $items
     * @return bool
     * @former warenkorbKuponFaehigHersteller
     * @since 5.2.0
     */
    public static function cartHasCouponValidManufacturers(Kupon $coupon, array $items): bool
    {
        if (empty($coupon->cHersteller) || (int)$coupon->cHersteller === -1) {
            return true;
        }
        foreach ($items as $item) {
            if (
                $item->nPosTyp === \C_WARENKORBPOS_TYP_ARTIKEL
                && \preg_match(
                    '/;' . \preg_quote((string)$item->Artikel?->kHersteller, '/') . ';/i',
                    $coupon->cHersteller
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param CartItem[] $items
     * @former warenkorbKuponFaehigKategorien()
     * @since 5.2.0
     */
    public static function cartHasCouponValidCategories(Kupon $coupon, array $items): bool
    {
        if (empty($coupon->cKategorien) || (int)$coupon->cKategorien === -1) {
            return true;
        }
        $products = [];
        foreach ($items as $item) {
            if (empty($item->Artikel)) {
                continue;
            }
            $products[] = $item->Artikel->kVaterArtikel !== 0
                ? $item->Artikel->kVaterArtikel
                : $item->Artikel->kArtikel;
        }
        if (\count($products) === 0) {
            return false;
        }
        // check if at least one product is in at least one category valid for this coupon
        $category = Shop::Container()->getDB()->getSingleObject(
            'SELECT kKategorie
            FROM tkategorieartikel
              WHERE kArtikel IN (' . \implode(',', $products) . ')
                AND kKategorie IN (' . \str_replace(';', ',', \trim($coupon->cKategorien, ';')) . ')
                LIMIT 1'
        );

        return $category !== null;
    }

    /**
     * liefert Gesamtsumme der Artikel im Warenkorb, welche dem Kupon zugeordnet werden können
     *
     * @param CartItem[] $cartItems
     * @former gibGesamtsummeKuponartikelImWarenkorb()
     * @since 5.2.0
     */
    public static function getCouponProductsTotal(Kupon $coupon, array $cartItems): float
    {
        $total = 0;
        foreach ($cartItems as $item) {
            if (
                $item->nPosTyp === \C_WARENKORBPOS_TYP_ARTIKEL
                && self::cartHasCouponValidProducts($coupon, [$item])
                && self::cartHasCouponValidManufacturers($coupon, [$item])
            ) {
                $total += $item->fPreis
                    * $item->nAnzahl
                    * ((100 + CartItem::getTaxRate($item)) / 100);
            }
        }

        return \round($total, 2);
    }

    /**
     * @former freeGiftStillValid()
     * @since 5.2.0
     */
    public static function freeGiftStillValid(Cart $cart): bool
    {
        $valid = true;
        foreach ($cart->PositionenArr as $item) {
            if ($item->nPosTyp !== \C_WARENKORBPOS_TYP_GRATISGESCHENK || $item->kArtikel === null) {
                continue;
            }
            // Prüfen ob der Artikel wirklich ein Gratisgeschenk ist und ob die Mindestsumme erreicht wird
            $gift = Shop::Container()->getFreeGiftService()->getFreeGiftProduct(
                $item->kArtikel,
                $cart->gibGesamtsummeWarenExt(
                    [\C_WARENKORBPOS_TYP_ARTIKEL],
                    true
                )
            );
            if ($gift !== null) {
                $cart->loescheSpezialPos(\C_WARENKORBPOS_TYP_GRATISGESCHENK);
                $valid = false;
            }
            break;
        }

        return $valid;
    }

    /**
     * Prüft ob im WK ein Versandfrei Kupon eingegeben wurde und falls ja,
     * wird dieser nach Eingabe der Lieferadresse gesetzt (falls Kriterien erfüllt)
     *
     * @return array<string, int>
     * @former pruefeVersandkostenfreiKuponVorgemerkt()
     * @since 5.2.0
     */
    public static function applyShippingFreeCoupon(): array
    {
        if (
            (isset($_SESSION['Kupon']) && $_SESSION['Kupon']->cKuponTyp === Kupon::TYPE_SHIPPING)
            || (isset($_SESSION['oVersandfreiKupon'])
                && $_SESSION['oVersandfreiKupon']->cKuponTyp === Kupon::TYPE_SHIPPING)
        ) {
            Frontend::getCart()->loescheSpezialPos(\C_WARENKORBPOS_TYP_KUPON);
            unset($_SESSION['Kupon']);
        }
        $errors = [];
        if (isset($_SESSION['oVersandfreiKupon']->kKupon) && $_SESSION['oVersandfreiKupon']->kKupon > 0) {
            // Wurde im WK ein Versandfreikupon eingegeben?
            $errors = Kupon::checkCoupon($_SESSION['oVersandfreiKupon']);
            if (Form::hasNoMissingData($errors)) {
                Kupon::acceptCoupon($_SESSION['oVersandfreiKupon']);
                Shop::Smarty()->assign('KuponMoeglich', Kupon::couponsAvailable());
            } else {
                Frontend::getCart()->loescheSpezialPos(\C_WARENKORBPOS_TYP_KUPON, true);
                Kupon::mapCouponErrorMessage($errors['ungueltig']);
            }
        }

        return $errors;
    }
}
