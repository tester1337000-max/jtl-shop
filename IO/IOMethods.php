<?php

declare(strict_types=1);

namespace JTL\IO;

use Exception;
use JTL\Alert\Alert;
use JTL\Boxes\Factory;
use JTL\Boxes\Renderer\DefaultRenderer;
use JTL\Cache\JTLCacheInterface;
use JTL\Campaign;
use JTL\Cart\Cart;
use JTL\Cart\CartHelper;
use JTL\Cart\PersistentCart;
use JTL\Catalog\Category\Kategorie;
use JTL\Catalog\Category\KategorieListe;
use JTL\Catalog\Currency;
use JTL\Catalog\Product\Artikel;
use JTL\Catalog\Product\EigenschaftWert;
use JTL\Catalog\Product\Preise;
use JTL\Catalog\Product\VariationValue;
use JTL\Catalog\Separator;
use JTL\Catalog\Wishlist\Wishlist;
use JTL\Checkout\DeliveryAddressTemplate;
use JTL\Checkout\Kupon;
use JTL\Customer\CustomerGroup;
use JTL\DB\DbInterface;
use JTL\Extensions\Config\Configurator;
use JTL\Extensions\Config\Item;
use JTL\Extensions\SelectionWizard\Wizard;
use JTL\FreeGift\Services\FreeGiftService;
use JTL\Helpers\Form;
use JTL\Helpers\GeneralObject;
use JTL\Helpers\Product;
use JTL\Helpers\Tax;
use JTL\Helpers\Text;
use JTL\Helpers\Typifier;
use JTL\Helpers\URL;
use JTL\Language\LanguageHelper;
use JTL\Language\Texts;
use JTL\RMA\DomainObjects\RMADomainObject;
use JTL\RMA\DomainObjects\RMAItemDomainObject;
use JTL\RMA\Helper\RMAItems;
use JTL\RMA\Services\RMAReasonService;
use JTL\RMA\Services\RMAReturnAddressService;
use JTL\RMA\Services\RMAService;
use JTL\Router\Controller\ReviewController;
use JTL\Router\Route;
use JTL\Router\State;
use JTL\Session\Frontend;
use JTL\Shipping\DomainObjects\ShippingCartPositionDTO;
use JTL\Shipping\DomainObjects\ShippingDTO;
use JTL\Shipping\Services\ShippingService;
use JTL\Shop;
use JTL\Shopsetting;
use JTL\Smarty\JTLSmarty;
use JTL\TwoFA\FrontendTwoFA;
use JTL\TwoFA\FrontendUserData;
use JTL\TwoFA\TwoFAEmergency;
use SmartyException;
use stdClass;

use function Functional\filter;
use function Functional\first;
use function Functional\flatten;
use function Functional\pluck;

/**
 * Class IOMethods
 * @package JTL\IO
 */
class IOMethods
{
    private DbInterface $db;
    private JTLCacheInterface $cache;
    private FreeGiftService $freeGiftService;
    private ShippingService $shippingService;

    public function __construct(
        private readonly IO $io,
        ?DbInterface $db = null,
        ?JTLCacheInterface $cache = null,
        private readonly RMAService $rmaService = new RMAService(),
        private readonly RMAReasonService $rmaReasonService = new RMAReasonService(),
        private readonly RMAReturnAddressService $rmaReturnAddressService = new RMAReturnAddressService(),
        ?FreeGiftService $freeGiftService = null,
        ?ShippingService $shippingService = null,
    ) {
        $this->db              = $db ?? Shop::Container()->getDB();
        $this->cache           = $cache ?? Shop::Container()->getCache();
        $this->freeGiftService = $freeGiftService ?? Shop::Container()->getFreeGiftService();
        $this->shippingService = $shippingService ?? Shop::Container()->getShippingService();
    }

    /**
     * @throws Exception
     */
    public function registerMethods(): IO
    {
        return $this->io->register('suggestions', $this->suggestions(...))
            ->register('pushToBasket', $this->pushToBasket(...))
            ->register('pushToComparelist', $this->pushToComparelist(...))
            ->register('removeFromComparelist', $this->removeFromComparelist(...))
            ->register('pushToWishlist', $this->pushToWishlist(...))
            ->register('removeFromWishlist', $this->removeFromWishlist(...))
            ->register('updateWishlistDropdown', $this->updateWishlistDropdown(...))
            ->register('checkDependencies', $this->checkDependencies(...))
            ->register('checkVarkombiDependencies', $this->checkVarkombiDependencies(...))
            ->register('buildConfiguration', $this->buildConfiguration(...))
            ->register('getBasketItems', $this->getBasketItems(...))
            ->register('getCategoryMenu', $this->getCategoryMenu(...))
            ->register('getRegionsByCountry', $this->getRegionsByCountry(...))
            ->register('checkDeliveryCountry', $this->checkDeliveryCountry(...))
            ->register('setSelectionWizardAnswers', $this->setSelectionWizardAnswers(...))
            ->register('getCitiesByZip', $this->getCitiesByZip(...))
            ->register('getZips', $this->getZips(...))
            ->register('getOpcDraftsHtml', $this->getOpcDraftsHtml(...))
            ->register('setWishlistVisibility', $this->setWishlistVisibility(...))
            ->register('updateWishlistItem', $this->updateWishlistItem(...))
            ->register('updateReviewHelpful', $this->updateReviewHelpful(...))
            ->register('setDeliveryaddressDefault', $this->setDeliveryaddressDefault(...))
            ->register('rmaSummary', $this->rmaSummary(...))
            ->register('rmaItems', $this->rmaItems(...))
            ->register('genTwoFAEmergencyCodes', $this->genTwoFAEmergencyCodes(...))
            ->register('getNewTwoFA', $this->getNewTwoFA(...))
            ->register('createShippingAddress', $this->createShippingAddress(...));
    }

    public function getNewTwoFA(int $userID): IOResponse
    {
        $customer = Frontend::getCustomer();
        $response = new IOResponse();
        $response->assignVar('response', null);
        if ($userID !== $customer->getID()) {
            return $response;
        }

        $twoFA          = new FrontendTwoFA($this->db, FrontendUserData::getByID($userID, $this->db));
        $data           = new stdClass();
        $data->szSecret = $twoFA->createNewSecret()->getSecret();
        $data->szQRcode = $twoFA->getQRcode();
        $response->assignVar('response', $data);

        return $response;
    }

    public function genTwoFAEmergencyCodes(int $userID): IOResponse
    {
        $customer = Frontend::getCustomer();
        $response = new IOResponse();
        $response->assignVar('response', null);
        if ($userID !== $customer->getID()) {
            return $response;
        }
        $data  = new stdClass();
        $twoFA = new FrontendTwoFA($this->db, FrontendUserData::getByID($userID, $this->db));

        $data->loginName = $twoFA->getUserData()->getName();
        $data->shopName  = $twoFA->getShopName();

        $emergencyCodes = new TwoFAEmergency($this->db);
        $emergencyCodes->removeExistingCodes($twoFA->getUserData());

        $data->vCodes = $emergencyCodes->createNewCodes($twoFA->getUserData());

        $response->assignVar('response', $data);

        return $response;
    }

    /**
     * @return stdClass[]
     * @throws SmartyException
     */
    public function suggestions(string $keyword): array
    {
        $results = [];
        if (\mb_strlen($keyword) < 2) {
            return $results;
        }
        $smarty     = Shop::Smarty();
        $language   = Shop::getLanguageID();
        $maxResults = ($cnt = Shop::getSettingValue(\CONF_ARTIKELUEBERSICHT, 'suche_ajax_anzahl')) > 0
            ? $cnt
            : 10;
        $results    = $this->db->getObjects(
            "SELECT cSuche AS keyword, nAnzahlTreffer AS quantity
                FROM tsuchanfrage
                WHERE SOUNDEX(cSuche) LIKE CONCAT(TRIM(TRAILING '0' FROM SOUNDEX(:keyword)), '%')
                    AND nAktiv = 1
                    AND kSprache = :lang
                ORDER BY CASE
                    WHEN cSuche = :keyword THEN 0
                    WHEN cSuche LIKE CONCAT(:keyword, '%') THEN 1
                    WHEN cSuche LIKE CONCAT('%', :keyword, '%') THEN 2
                    ELSE 99
                    END, nAnzahlGesuche DESC, cSuche
                LIMIT :maxres",
            [
                'keyword' => $keyword,
                'maxres'  => $maxResults,
                'lang'    => $language
            ]
        );
        $smarty->assign('shopURL', Shop::getURL());
        foreach ($results as $result) {
            $result->suggestion = $smarty->assign('result', $result)->fetch('snippets/suggestion.tpl');
        }

        return $results;
    }

    /**
     * @return array<int, string>
     */
    public function getCitiesByZip(string $cityQuery, string $country, string $zip): array
    {
        if (empty($country)) {
            return [];
        }

        $result = \array_unique(
            pluck(
                $this->db->getObjects(
                    'SELECT cOrt
                        FROM tplz
                        WHERE cLandISO = :country
                            AND cPLZ LIKE :zip
                            AND cOrt LIKE :cityQuery',
                    ['country' => $country, 'zip' => $zip . '%', 'cityQuery' => $cityQuery . '%']
                ),
                'cOrt'
            )
        );

        \sort($result);

        return $result;
    }

    /**
     * @return array<int, string>
     */
    public function getZips(string $postCodeQuery, string $country, string $city): array
    {
        if (empty($country)) {
            return [];
        }

        $result = pluck(
            $this->db->getObjects(
                'SELECT DISTINCT cPLZ
                    FROM tplz
                    WHERE cLandISO = :country
                        AND cPLZ LIKE :postCodeQuery
                        AND cOrt LIKE :city',
                ['country' => $country, 'city' => $city . '%', 'postCodeQuery' => $postCodeQuery . '%']
            ),
            'cPLZ'
        );
        \sort($result);

        return $result;
    }

    /**
     * @param array<string, mixed> $properties
     */
    public function pushToBasket(int $productID, float|int|string $amount, array $properties): IOResponse
    {
        require_once \PFAD_ROOT . \PFAD_INCLUDES . 'sprachfunktionen.php';
        $config     = Shopsetting::getInstance($this->db, $this->cache)->getAll();
        $smarty     = Shop::Smarty();
        $response   = new stdClass();
        $ioResponse = new IOResponse();
        $token      = $properties['jtl_token'];
        if ($amount <= 0 || $productID <= 0) {
            return $ioResponse;
        }
        $product               = new Artikel($this->db, null, null, $this->cache);
        $options               = Artikel::getDefaultOptions();
        $options->nStueckliste = 1;
        $product->fuelleArtikel($productID, $options);
        // Falls der Artikel ein Variationskombikind ist, hole direkt seine Eigenschaften
        if ($product->kEigenschaftKombi > 0 || $product->nIstVater === 1) {
            // Variationskombi-Artikel
            $_POST['eigenschaftwert'] = $properties['eigenschaftwert'];
            $properties               = Product::getSelectedPropertiesForVarCombiArticle($productID);
        } elseif (GeneralObject::isCountable('eigenschaftwert', $properties)) {
            // einfache Variation - keine Varkombi
            $_POST['eigenschaftwert'] = $properties['eigenschaftwert'];
            $properties               = Product::getSelectedPropertiesForArticle($productID);
        }

        if ($product->cTeilbar !== 'Y') {
            $amount = \max((int)$amount, 1);
        } else {
            $amount = (float)$amount;
        }
        $errors = CartHelper::addToCartCheck($product, $amount, $properties, 2, $token);
        if (\count($errors) > 0) {
            $localizedErrors = Product::getProductMessages($errors, true, $product, $amount);

            $response->nType  = 0;
            $response->cLabel = Shop::Lang()->get('basket');
            $response->cHints = Text::utf8_convert_recursive($localizedErrors);
            $ioResponse->assignVar('response', $response);

            return $ioResponse;
        }
        $cart = Frontend::getCart();
        $cart->fuegeEin($productID, $amount, $properties)
            ->loescheSpezialPos(\C_WARENKORBPOS_TYP_VERSANDPOS)
            ->loescheSpezialPos(\C_WARENKORBPOS_TYP_VERSANDZUSCHLAG)
            ->loescheSpezialPos(\C_WARENKORBPOS_TYP_VERSAND_ARTIKELABHAENGIG)
            ->loescheSpezialPos(\C_WARENKORBPOS_TYP_ZAHLUNGSART)
            ->loescheSpezialPos(\C_WARENKORBPOS_TYP_ZINSAUFSCHLAG)
            ->loescheSpezialPos(\C_WARENKORBPOS_TYP_BEARBEITUNGSGEBUEHR)
            ->loescheSpezialPos(\C_WARENKORBPOS_TYP_NEUKUNDENKUPON)
            ->loescheSpezialPos(\C_WARENKORBPOS_TYP_NACHNAHMEGEBUEHR);

        unset(
            $_SESSION['VersandKupon'],
            $_SESSION['NeukundenKupon'],
            $_SESSION['Versandart'],
            $_SESSION['Zahlungsart']
        );
        // Wenn Kupon vorhanden und prozentual auf ganzen Warenkorb,
        // dann verwerfen und neu anlegen
        Kupon::reCheck();
        // Persistenter Warenkorb
        if (!isset($_POST['login'])) {
            PersistentCart::getInstance(Frontend::getCustomer()->getID())->check($productID, $amount, $properties);
        }
        $pageType    = Shop::getPageType();
        $boxes       = Shop::Container()->getBoxService();
        $boxesToShow = $boxes->render($boxes->buildList($pageType), $pageType);
        $xSelling    = Product::getXSelling($productID, $product->nIstVater > 0);
        $sum         = [
            Preise::getLocalizedPriceString($cart->gibGesamtsummeWarenExt([\C_WARENKORBPOS_TYP_ARTIKEL], true)),
            Preise::getLocalizedPriceString($cart->gibGesamtsummeWarenExt([\C_WARENKORBPOS_TYP_ARTIKEL]))
        ];

        $customer           = Frontend::getCustomer();
        $customerGroup      = Frontend::getCustomerGroup();
        $customerGroupID    = $customer->getGroupID();
        $currency           = Frontend::getCurrency();
        $cartItems          = Frontend::getCart()->PositionenArr;
        $freeShippingMethod = $this->shippingService->getFreeShippingMethod(
            $customer,
            $customerGroup,
            $currency,
            (string)$customer->cLand,
            $cartItems,
            (string)$customer->cPLZ,
        );

        $smarty->assign('Boxen', $boxesToShow)
            ->assign('WarenkorbWarensumme', $sum)
            ->assign(
                'WarenkorbVersandkostenfreiHinweis',
                $freeShippingMethod !== null
                    ? $this->shippingService->getShippingFreeString(
                        $freeShippingMethod,
                        $customerGroup->isMerchant(),
                        $currency,
                        $cartItems,
                        (string)$customer->cLand,
                    ) : ''
            )
            ->assign(
                'nextFreeGiftMissingAmount',
                $this->freeGiftService->getNextAvailableMissingAmount(
                    $cart->gibGesamtsummeWarenExt([\C_WARENKORBPOS_TYP_ARTIKEL], true),
                    $customerGroupID,
                )
            )
            ->assign('zuletztInWarenkorbGelegterArtikel', $cart->gibLetztenWKArtikel())
            ->assign('fAnzahl', $amount)
            ->assign('NettoPreise', Frontend::getCustomerGroup()->getIsMerchant())
            ->assign('Einstellungen', $config)
            ->assign('Xselling', $xSelling)
            ->assign('WarensummeLocalized', $cart->gibGesamtsummeWarenLocalized())
            ->assign('oSpezialseiten_arr', Shop::Container()->getLinkService()->getSpecialPages())
            ->assign('Steuerpositionen', $cart->gibSteuerpositionen())
            ->assign('favourableShippingString', $cart->favourableShippingString);

        $response->nType           = 2;
        $response->cWarenkorbText  = Texts::cartContainsItems($cart);
        $response->cWarenkorbLabel = Shop::Lang()->get(
            'cartSumLabel',
            'checkout',
            Preise::getLocalizedPriceString(
                $cart->gibGesamtsummeWarenExt(
                    [\C_WARENKORBPOS_TYP_ARTIKEL],
                    !Frontend::getCustomerGroup()->isMerchant()
                )
            )
        );
        $response->cPopup          = $smarty->fetch('productdetails/pushed.tpl');
        $response->cWarenkorbMini  = $smarty->fetch('basket/cart_dropdown.tpl');
        $response->oArtikel        = $product;
        $response->cNotification   = Shop::Lang()->get('basketAllAdded', 'messages');

        $ioResponse->assignVar('response', $response);
        Campaign::setCampaignAction(\KAMPAGNE_DEF_WARENKORB, $productID, $amount);
        if ($config['global']['global_warenkorb_weiterleitung'] === 'Y') {
            $response->nType     = 1;
            $response->cLocation = Shop::Container()->getLinkService()->getStaticRoute('warenkorb.php');
            $ioResponse->assignVar('response', $response);
        }

        return $ioResponse;
    }

    public function pushToComparelist(int $productID): IOResponse
    {
        $conf       = Shopsetting::getInstance($this->db, $this->cache)->getAll();
        $response   = new stdClass();
        $ioResponse = new IOResponse();
        $smarty     = Shop::Smarty();

        $_POST['Vergleichsliste'] = 1;
        $_POST['a']               = $productID;

        CartHelper::checkAdditions();
        $response->nType  = 2;
        $response->nCount = \count($_SESSION['Vergleichsliste']->oArtikel_arr ?? []);
        $response->cTitle = Shop::Lang()->get('compare');
        $buttons          = [
            (object)[
                'href'    => '#',
                'fa'      => 'fa fa-arrow-circle-right',
                'title'   => Shop::Lang()->get('continueShopping', 'checkout'),
                'primary' => true,
                'dismiss' => 'modal'
            ]
        ];

        if ($response->nCount > 1) {
            \array_unshift(
                $buttons,
                (object)[
                    'href'  => Shop::Container()->getLinkService()->getStaticRoute('vergleichsliste.php'),
                    'fa'    => 'fa-tasks',
                    'title' => Shop::Lang()->get('compare')
                ]
            );
        }
        $alerts  = Shop::Container()->getAlertService();
        $content = $smarty->assign('alertList', $alerts)
            ->assign('Einstellungen', $conf)
            ->fetch('snippets/alert_list.tpl');

        $response->cNotification = $smarty
            ->assign('type', $alerts->alertTypeExists(Alert::TYPE_ERROR) ? 'danger' : 'info')
            ->assign('body', $content)
            ->assign('buttons', $buttons)
            ->fetch('snippets/notification.tpl');

        $response->cNavBadge     = $smarty->fetch('layout/header_shop_nav_compare.tpl');
        $response->navDropdown   = $smarty->fetch('snippets/comparelist_dropdown.tpl');
        $response->cBoxContainer = [];
        foreach ($this->forceRenderBoxes(\BOX_VERGLEICHSLISTE, $conf, $smarty) as $id => $html) {
            $response->cBoxContainer[$id] = $html;
        }
        $ioResponse->assignVar('response', $response);

        return $ioResponse;
    }

    public function removeFromComparelist(int $productID): IOResponse
    {
        $conf       = Shopsetting::getInstance($this->db, $this->cache)->getAll();
        $response   = new stdClass();
        $ioResponse = new IOResponse();
        $smarty     = Shop::Smarty();

        $_GET['Vergleichsliste']                = 1;
        $_GET[\QUERY_PARAM_COMPARELIST_PRODUCT] = $productID;

        Frontend::getInstance()->setStandardSessionVars();
        $response->nType     = 2;
        $response->productID = $productID;
        $response->nCount    = \count(Frontend::get('Vergleichsliste')->oArtikel_arr ?? []);
        $response->cTitle    = Shop::Lang()->get('compare');
        $response->cNavBadge = $smarty->assign('Einstellungen', $conf)
            ->fetch('layout/header_shop_nav_compare.tpl');

        $response->navDropdown   = $smarty->fetch('snippets/comparelist_dropdown.tpl');
        $response->cBoxContainer = [];

        foreach ($this->forceRenderBoxes(\BOX_VERGLEICHSLISTE, $conf, $smarty) as $id => $html) {
            $response->cBoxContainer[$id] = $html;
        }
        $ioResponse->assignVar('response', $response);

        return $ioResponse;
    }

    /**
     * @param array<mixed> $conf
     * @return array<int, string>
     */
    private function forceRenderBoxes(int $type, array $conf, JTLSmarty $smarty): array
    {
        $res      = [];
        $boxData  = $this->db->getObjects(
            'SELECT *, 0 AS nSort, \'\' AS pageIDs, \'\' AS pageVisibilities,
                       GROUP_CONCAT(tboxensichtbar.nSort) AS sortBypageIDs,
                       GROUP_CONCAT(tboxensichtbar.kSeite) AS pageIDs,
                       GROUP_CONCAT(tboxensichtbar.bAktiv) AS pageVisibilities
                FROM tboxen
                LEFT JOIN tboxensichtbar
                    ON tboxen.kBox = tboxensichtbar.kBox
                LEFT JOIN tboxvorlage
                    ON tboxen.kBoxvorlage = tboxvorlage.kBoxvorlage
                WHERE tboxen.kBoxvorlage = :type
                GROUP BY tboxen.kBox',
            ['type' => $type]
        );
        $factory  = new Factory($conf);
        $renderer = new DefaultRenderer($smarty);
        foreach ($boxData as $item) {
            $box = $factory->getBoxByBaseType($type);
            $box->map([$item]);
            $box->setFilter([]);
            $box->setShow(true);
            $renderer->setBox($box);
            $res[$box->getID()] = $renderer->render();
        }

        return $res;
    }

    /**
     * @param array<mixed> $data
     */
    public function pushToWishlist(int $productID, float|int|string $qty, array $data): IOResponse
    {
        $_POST      = $data;
        $conf       = Shopsetting::getInstance($this->db, $this->cache)->getAll();
        $response   = new stdClass();
        $ioResponse = new IOResponse();
        $qty        = empty($qty) ? 1 : $qty;
        $smarty     = Shop::Smarty();
        if (Frontend::getCustomer()->getID() === 0) {
            $response->nType     = 1;
            $response->cLocation = Shop::Container()->getLinkService()->getStaticRoute('jtl.php')
                . '?a=' . $productID
                . '&n=' . $qty
                . '&r=' . \R_LOGIN_WUNSCHLISTE;
            $ioResponse->assignVar('response', $response);

            return $ioResponse;
        }
        $vals = $this->db->selectAll('teigenschaft', 'kArtikel', $productID);
        if (!empty($vals) && empty($_POST['eigenschaftwert']) && !Product::isParent($productID)) {
            // Falls die Wunschliste aus der Artikelübersicht ausgewählt wurde,
            // muss zum Artikel weitergeleitet werden um Variationen zu wählen
            $response->nType     = 1;
            $response->cLocation = (Shop::getURL() . '/?a=' . $productID
                . '&n=' . $qty
                . '&r=' . \R_VARWAEHLEN);
            $ioResponse->assignVar('response', $response);

            return $ioResponse;
        }

        $_POST['Wunschliste'] = 1;
        $_POST['a']           = $productID;
        $_POST['n']           = $qty;

        CartHelper::checkAdditions();

        foreach (Frontend::getWishList()->getItems() as $wlPos) {
            if ($wlPos->getProductID() === $productID) {
                $response->wlPosAdd = $wlPos->getID();
            }
        }
        $response->nType     = 2;
        $response->nCount    = \count(Frontend::getWishList()->getItems());
        $response->productID = $productID;
        $response->cTitle    = Shop::Lang()->get('goToWishlist');
        $buttons             = [
            (object)[
                'href'    => '#',
                'fa'      => 'fa fa-arrow-circle-right',
                'title'   => Shop::Lang()->get('continueShopping', 'checkout'),
                'primary' => true,
                'dismiss' => 'modal'
            ]
        ];

        if ($response->nCount > 1) {
            \array_unshift(
                $buttons,
                (object)[
                    'href'  => Shop::Container()->getLinkService()->getStaticRoute('wunschliste.php'),
                    'fa'    => 'fa-tasks',
                    'title' => Shop::Lang()->get('goToWishlist')
                ]
            );
        }
        $alerts = Shop::Container()->getAlertService();
        $body   = $smarty->assign('alertList', $alerts)
            ->assign('Einstellungen', $conf)
            ->fetch('snippets/alert_list.tpl');

        $smarty->assign('type', $alerts->alertTypeExists(Alert::TYPE_ERROR) ? 'danger' : 'info')
            ->assign('body', $body)
            ->assign('buttons', $buttons);

        $response->cNotification = $smarty->fetch('snippets/notification.tpl');
        $response->cNavBadge     = $smarty->fetch('layout/header_shop_nav_wish.tpl');
        $response->cBoxContainer = [];
        foreach ($this->forceRenderBoxes(\BOX_WUNSCHLISTE, $conf, $smarty) as $id => $html) {
            $response->cBoxContainer[$id] = $html;
        }
        $ioResponse->assignVar('response', $response);

        if ($conf['global']['global_wunschliste_weiterleitung'] === 'Y') {
            $response->nType     = 1;
            $response->cLocation = Shop::Container()->getLinkService()->getStaticRoute('wunschliste.php');
            $ioResponse->assignVar('response', $response);
        }

        return $ioResponse;
    }

    public function removeFromWishlist(int $productID): IOResponse
    {
        $conf       = Shopsetting::getInstance($this->db, $this->cache)->getAll();
        $response   = new stdClass();
        $ioResponse = new IOResponse();
        $smarty     = Shop::Smarty();

        $_GET['Wunschliste'] = 1;
        $_GET['wlplo']       = $productID;

        Frontend::getInstance()->setStandardSessionVars();
        $response->nType         = 2;
        $response->wlPosRemove   = $productID;
        $response->nCount        = \count(Frontend::getWishList()->getItems());
        $response->cTitle        = Shop::Lang()->get('goToWishlist');
        $response->cBoxContainer = [];
        $response->cNavBadge     = $smarty->assign('Einstellungen', $conf)
            ->fetch('layout/header_shop_nav_wish.tpl');

        foreach ($this->forceRenderBoxes(\BOX_WUNSCHLISTE, $conf, $smarty) as $id => $html) {
            $response->cBoxContainer[$id] = $html;
        }
        $ioResponse->assignVar('response', $response);

        return $ioResponse;
    }

    public function updateWishlistDropdown(): IOResponse
    {
        $response   = new stdClass();
        $ioResponse = new IOResponse();
        $smarty     = Shop::Smarty();

        $response->content         = $smarty->assign('wishlists', Wishlist::getWishlists())
            ->fetch('snippets/wishlist_dropdown.tpl');
        $response->currentPosCount = \count(Frontend::getWishList()->getItems());

        $ioResponse->assignVar('response', $response);

        return $ioResponse;
    }

    /**
     * @param int $type - 0 = Template, 1 = Object
     */
    public function getBasketItems(int $type = 0): IOResponse
    {
        require_once \PFAD_ROOT . \PFAD_INCLUDES . 'sprachfunktionen.php';
        $cart       = Frontend::getCart();
        $response   = new stdClass();
        $ioResponse = new IOResponse();

        switch ($type) {
            default:
            case 0:
                $smarty = Shop::Smarty();
                $this->getBasketItemsTemplate($cart, $smarty);
                $response->cTemplate = $smarty->fetch('basket/cart_dropdown_label.tpl');
                break;

            case 1:
                $response->cItems = $cart->PositionenArr;
                break;
        }

        $ioResponse->assignVar('response', $response);

        return $ioResponse;
    }

    /**
     * @param array<string, mixed> $aValues
     */
    public function buildConfiguration(array $aValues): IOResponse
    {
        $_POST['jtl_token'] = $aValues['jtl_token'];
        $smarty             = Shop::Smarty();
        $response           = new IOResponse();
        $product            = new Artikel($this->db, null, null, $this->cache);
        $productID          = (int)($aValues['VariKindArtikel'] ?? $aValues['a']);
        $items              = $aValues['item'] ?? [];
        $quantities         = $aValues['quantity'] ?? [];
        $itemQuantities     = $aValues['item_quantity'] ?? [];
        $variationValues    = $aValues['eigenschaftwert'] ?? [];
        $amount             = (float)($aValues['anzahl'] ?? 1);
        $customerGroupID    = Frontend::getCustomerGroup()->getID();
        $languageID         = Shop::getLanguageID();
        $config             = Product::buildConfig(
            $productID,
            $amount,
            $variationValues,
            $items,
            $quantities,
            $itemQuantities,
            true
        );
        if ($config === null) {
            return $response;
        }
        $net                   = Frontend::getCustomerGroup()->getIsMerchant();
        $options               = Artikel::getDefaultOptions();
        $options->nVariationen = 1;
        $product->fuelleArtikel($productID, $options, $customerGroupID, $languageID);
        $fVKNetto = (float)$product->gibPreis($amount, [], $customerGroupID);
        $fVK      = [
            Tax::getGross($fVKNetto, $_SESSION['Steuersatz'][$product->kSteuerklasse]),
            $fVKNetto
        ];
        if ($product->Preise === null) {
            return $response;
        }
        $product->Preise->cVKLocalized              = [
            0 => Preise::getLocalizedPriceString($fVK[0]),
            1 => Preise::getLocalizedPriceString($fVK[1])
        ];
        [$itemErrors, $configItems, $invalidGroups] = $this->getErrorsItemsAndGroups(
            $languageID,
            $customerGroupID,
            $items,
            $quantities,
            $itemQuantities,
            $amount
        );

        $errors                = Configurator::validateCart($productID, $configItems);
        $config->invalidGroups = \array_values(
            \array_unique(
                \array_merge(
                    $invalidGroups,
                    \array_keys(\is_array($errors) ? $errors : [])
                )
            )
        );
        $config->errorMessages = $itemErrors;
        $config->valid         = empty($config->invalidGroups) && empty($config->errorMessages);
        $cartHelperErrors      = CartHelper::addToCartCheck(
            $product,
            1,
            Product::getSelectedPropertiesForArticle($productID, false)
        );

        $config->variationsSelected = $product->kVaterArtikel > 0
            || !\in_array(
                \R_VARWAEHLEN,
                $cartHelperErrors,
                true
            );
        $config->inStock            = !\in_array(\R_LAGER, $cartHelperErrors, true);
        $smarty->assign('oKonfig', $config)
            ->assign('NettoPreise', $net)
            ->assign('Artikel', $product);
        $config->cTemplate = $smarty->fetch('productdetails/config_summary.tpl');

        $response->assignVar('response', $config);

        return $response;
    }

    /**
     * @param array<int|numeric-string, int|numeric-string>|null $selectedVariationValues
     */
    public function getArticleStockInfo(int $productID, ?array $selectedVariationValues = null): stdClass
    {
        $result = (object)[
            'stock'  => false,
            'status' => 0,
            'text'   => '',
        ];

        if ($selectedVariationValues !== null) {
            $products = $this->getArticleByVariations($productID, $selectedVariationValues);
            if (\count($products) === 1) {
                $productID = (int)$products[0]->kArtikel;
            } else {
                return $result;
            }
        }

        if ($productID <= 0) {
            return $result;
        }
        $product                            = new Artikel($this->db, null, null, $this->cache);
        $options                            = Artikel::getDefaultOptions();
        $options->nKeinLagerbestandBeachten = 1;

        $product->fuelleArtikel(
            $productID,
            $options,
            CustomerGroup::getCurrent(),
            Shop::getLanguageID()
        );

        $stockInfo = $product->getStockInfo();

        if ($stockInfo->notExists || !$stockInfo->inStock) {
            $result->stock = false;
            $result->text  = $stockInfo->notExists
                ? Shop::Lang()->get('notAvailableInSelection')
                : Shop::Lang()->get('ampelRot');
        } else {
            $result->stock = true;
            $result->text  = '';
        }

        $result->status = $product->Lageranzeige->nStatus ?? 0;

        return $result;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function checkDependencies(array $values): IOResponse
    {
        $ioResponse    = new IOResponse();
        $customerGroup = Frontend::getCustomerGroup();
        $checkBulk     = isset($values['VariKindArtikel']);
        $parentID      = $checkBulk
            ? (int)$values['VariKindArtikel']
            : (int)$values['a'];
        if ($parentID <= 0) {
            return $ioResponse;
        }
        $valueIDs = \array_filter((array)$values['eigenschaftwert']);
        /** @var string $wrapper */
        $wrapper = isset($values['wrapper'])
            ? Text::filterXSS($values['wrapper'])
            : '';

        $response         = new stdClass();
        $response->check  = Wishlist::checkVariOnList($parentID, $valueIDs);
        $response->itemID = $parentID;
        $ioResponse->assignVar('response', $response);

        $product = new Artikel($this->db, null, null, $this->cache);
        $product->fuelleArtikel(
            $parentID,
            $checkBulk
                ? null
                : (object)[
                    'nKeinLagerbestandBeachten' => 1,
                    'nMain'                     => 1,
                    'nWarenlager'               => 1,
                    'nVariationen'              => 1,
                    'nArtikelAttribute'         => 1,
                    'nShipping'                 => 1
            ],
            $customerGroup->getID()
        );
        $weightDiff   = 0;
        $newProductNr = '';

        // Alle Variationen ohne Freifeld
        $keyValueVariations = $product->keyValueVariations($product->VariationenOhneFreifeld);
        foreach ($valueIDs as $index => $value) {
            if (isset($keyValueVariations[$index]) === false) {
                unset($valueIDs[$index]);
                continue;
            }
            $ioResponse->callEvoProductFunction(
                'variationActive',
                $index,
                \addslashes($value),
                null,
                $wrapper
            );
        }

        foreach ($valueIDs as $valueID) {
            $currentValue = new EigenschaftWert((int)$valueID, $this->db);
            $weightDiff   += (float)$currentValue->fGewichtDiff;
            $newProductNr = (
                empty($currentValue->cArtNr) === false
                && $product->cArtNr !== $currentValue->cArtNr
            )
                ? $currentValue->cArtNr
                : $product->cArtNr ?? '';
        }

        $ioResponse = $this->checkMoreDependencies(
            $ioResponse,
            $product,
            $customerGroup,
            $valueIDs,
            (float)$weightDiff,
            $newProductNr,
            (float)($values['anzahl'] ?? 0),
            $wrapper
        );
        \executeHook(\HOOK_IO_CHECK_DEPENDENCIES, [
            'response' => &$ioResponse,
            'product'  => &$product
        ]);

        return $ioResponse;
    }

    /**
     * @param array{a: int|string, VariKindArtikel?: int|string, eigenschaftwert?: array<mixed>,
     * layout?: string, wrapper?: string} $values
     */
    public function checkVarkombiDependencies(
        array $values,
        int|string|null $propertyID = 0,
        int|string|null $propertyValueID = 0
    ): IOResponse {
        $propertyID      = (int)$propertyID;
        $propertyValueID = (int)$propertyValueID;
        $ioResponse      = new IOResponse();
        $parentProductID = (int)$values['a'];
        $childProductID  = isset($values['VariKindArtikel']) ? (int)$values['VariKindArtikel'] : 0;
        $idx             = isset($values['eigenschaftwert']) ? (array)$values['eigenschaftwert'] : [];
        $freetextValues  = [];
        $set             = \array_filter($idx);
        $layout          = isset($values['layout']) ? Text::filterXSS($values['layout']) : '';
        $wrapper         = isset($values['wrapper']) ? Text::filterXSS($values['wrapper']) : '';
        if ($parentProductID <= 0) {
            throw new Exception('Product not found ' . $parentProductID);
        }
        $product = new Artikel($this->db, null, null, $this->cache);
        $product->fuelleArtikel(
            $parentProductID,
            (object)[
                'nKeinLagerbestandBeachten' => 1,
                'nMain'                     => 1,
                'nWarenlager'               => 1,
                'nVariationen'              => 1,
            ]
        );
        // Alle Variationen ohne Freifeld
        $keyValueVariations = $product->keyValueVariations($product->VariationenOhneFreifeld);
        // Freifeldpositionen gesondert zwischenspeichern
        foreach ($set as $kKey => $cVal) {
            if (!isset($keyValueVariations[$kKey])) {
                unset($set[$kKey]);
                $freetextValues[$kKey] = (int)$cVal;
            }
        }
        $hasInvalidSelection = false;
        $invalidVariations   = $product->getVariationsBySelection($set, true);
        foreach ($set as $kKey => $kValue) {
            if (isset($invalidVariations[$kKey]) && \in_array((int)$kValue, $invalidVariations[$kKey], true)) {
                $hasInvalidSelection = true;
                break;
            }
        }
        // Auswahl zurücksetzen, sobald eine nicht vorhandene Variation ausgewählt wurde.
        if ($hasInvalidSelection) {
            [$ioResponse, $set, $return] = $this->selectedInvalidVariation(
                $ioResponse,
                $product,
                $propertyID,
                $propertyValueID,
                $childProductID,
                $wrapper,
            );
            if ($return) {
                return $ioResponse;
            }
        }
        // Alle EigenschaftWerte vorhanden, Kind-Artikel ermitteln
        if (\count($set) >= $product->nVariationOhneFreifeldAnzahl) {
            $products = $this->getArticleByVariations($parentProductID, $set);
            if (\count($products) === 1 && $childProductID !== (int)$products[0]->kArtikel) {
                return $this->setChildProductContent(
                    $ioResponse,
                    $product,
                    $products,
                    $freetextValues,
                    $childProductID,
                    $parentProductID,
                    $layout,
                    $wrapper,
                );
            }
        }

        $ioResponse->callEvoProductFunction('variationDisableAll', $wrapper);
        $possibleVariations = $product->getVariationsBySelection($set);
        foreach ($product->Variationen as $variation) {
            if (\in_array($variation->cTyp, ['FREITEXT', 'PFLICHTFREITEXT'])) {
                $ioResponse->callEvoProductFunction('variationEnable', $variation->kEigenschaft, 0, $wrapper);
                continue;
            }
            $ioResponse->callEvoProductFunction('showGalleryVariation', $variation->kEigenschaft, $wrapper);
            foreach ($variation->Werte as $value) {
                $ioResponse = $this->setVariationStockInfo(
                    $ioResponse,
                    $value,
                    $wrapper,
                    $parentProductID,
                    $set,
                    $possibleVariations,
                );
            }

            if (isset($set[$variation->kEigenschaft])) {
                $ioResponse->callEvoProductFunction(
                    'variationActive',
                    $variation->kEigenschaft,
                    \addslashes((string)$set[$variation->kEigenschaft]),
                    null,
                    $wrapper
                );
            }
        }
        $ioResponse->callEvoProductFunction('variationRefreshAll', $wrapper);

        return $ioResponse;
    }

    /**
     * @param array<int|numeric-string, int|numeric-string> $selectedVariationValues
     * @return stdClass[]
     */
    public function getArticleByVariations(int $parentProductID, array $selectedVariationValues): array
    {
        if (empty($selectedVariationValues)) {
            return [];
        }
        $variationID    = 0;
        $variationValue = 0;
        $combinations   = [];
        $i              = 0;
        foreach ($selectedVariationValues as $id => $value) {
            $id    = (int)$id;
            $value = (int)$value;
            if ($i === 0) {
                $i++;
                $variationID    = $id;
                $variationValue = $value;
            } else {
                $combinations[] = '(' . $id . ', ' . $value . ')';
            }
        }

        $combinationSQL = empty($combinations) === false
            ? 'EXISTS (
                     SELECT 1
                     FROM teigenschaftkombiwert innerKombiwert
                     WHERE (innerKombiwert.kEigenschaft, innerKombiwert.kEigenschaftWert) IN
                     (' . \implode(', ', $combinations) . ')
                        AND innerKombiwert.kEigenschaftKombi = teigenschaftkombiwert.kEigenschaftKombi
                     GROUP BY innerKombiwert.kEigenschaftKombi
                     HAVING COUNT(innerKombiwert.kEigenschaftKombi) = ' . \count($combinations) . '
                )
                AND '
            : '';

        return $this->db->getObjects(
            'SELECT tartikel.kArtikel,
                tseo.kKey AS kSeoKey, COALESCE(tseo.cSeo, \'\') AS cSeo,
                tartikel.fLagerbestand, tartikel.cLagerBeachten, tartikel.cLagerKleinerNull
                FROM teigenschaftkombiwert
                INNER JOIN tartikel
                    ON tartikel.kEigenschaftKombi = teigenschaftkombiwert.kEigenschaftKombi
                LEFT JOIN tseo
                    ON tseo.cKey = \'kArtikel\'
                    AND tseo.kKey = tartikel.kArtikel
                    AND tseo.kSprache = :languageID
                LEFT JOIN tartikelsichtbarkeit
                    ON tartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                    AND tartikelsichtbarkeit.kKundengruppe = :customergroupID
                WHERE ' . $combinationSQL . 'tartikel.kVaterArtikel = :parentProductID
                    AND teigenschaftkombiwert.kEigenschaft = :variationID
                    AND teigenschaftkombiwert.kEigenschaftWert = :variationValue
                    AND tartikelsichtbarkeit.kArtikel IS NULL',
            [
                'languageID'      => Shop::getLanguageID(),
                'customergroupID' => Frontend::getCustomerGroup()->getID(),
                'parentProductID' => $parentProductID,
                'variationID'     => $variationID,
                'variationValue'  => $variationValue,
            ]
        );
    }

    public function getCategoryMenu(int $categoryID): IOResponse
    {
        $smarty = Shop::Smarty();
        $auto   = $categoryID === 0;
        if ($auto) {
            $categoryID = Shop::$kKategorie;
        }
        $response   = new IOResponse();
        $list       = new KategorieListe();
        $category   = new Kategorie($categoryID, 0, 0, false, $this->db);
        $categories = $list->getChildCategories($category->getParentID(), 0, 0);
        if ($auto && \count($categories) === 0) {
            $category   = new Kategorie($category->getParentID(), 0, 0, false, $this->db);
            $categories = $list->getChildCategories($category->getParentID(), 0, 0);
        }

        $smarty->assign('result', (object)['current' => $category, 'items' => $categories])
            ->assign('nSeitenTyp', 0);

        $response->assignVar('response', $smarty->fetch('snippets/categories_offcanvas.tpl'));

        return $response;
    }

    public function getRegionsByCountry(string $iso): IOResponse
    {
        $ioResponse = new IOResponse();
        if (\mb_strlen($iso) === 2) {
            $country = Shop::Container()->getCountryService()->getCountry($iso);
            if ($country === null) {
                return $ioResponse;
            }
            $data           = new stdClass();
            $data->states   = $country->getStates();
            $data->required = $country->isRequireStateDefinition()
                || Shop::getSettingValue(\CONF_KUNDEN, 'kundenregistrierung_abfragen_bundesland') === 'Y';
            $ioResponse->assignVar('response', $data);
        }

        return $ioResponse;
    }

    public function checkDeliveryCountry(string $country): IOResponse
    {
        $response = new IOResponse();
        if (\mb_strlen($country) !== 2) {
            return $response;
        }
        $deliveryCountries = $this->shippingService->getPossibleShippingCountries(
            [$country],
            Frontend::getCustomerGroup()->getID(),
            Frontend::getCart()->PositionenArr,
        );
        $response->assignVar('response', \count($deliveryCountries) === 1);

        return $response;
    }

    /**
     * @param array<int|numeric-string> $selection
     */
    public function setSelectionWizardAnswers(string $keyName, int $id, int $languageID, array $selection): IOResponse
    {
        $smarty     = Shop::Smarty();
        $ioResponse = new IOResponse();
        $wizard     = Wizard::startIfRequired($keyName, $id, $languageID, $smarty, $selection);
        if ($wizard === null) {
            return $ioResponse;
        }
        $lastSelectedValue = $wizard->getLastSelectedValue();
        $productFilter     = $wizard->getNaviFilter();
        if (
            ($lastSelectedValue !== null && $lastSelectedValue->getCount() === 1)
            || $wizard->getCurQuestion() === $wizard->getQuestionCount()
            || $wizard->getQuestion($wizard->getCurQuestion())?->nTotalResultCount === 0
        ) {
            $ioResponse->setClientRedirect($productFilter->getFilterURL()->getURL());
        } else {
            $ioResponse->assignDom('selectionwizard', 'innerHTML', $wizard->fetchForm($smarty));
        }

        return $ioResponse;
    }

    /**
     * @param array<int, mixed> $languages
     * @param array<mixed>      $currentLanguage
     */
    public function getOpcDraftsHtml(
        string $curPageID,
        string $adminSessionToken,
        array $languages,
        array $currentLanguage
    ): IOResponse {
        foreach ($languages as $i => $lang) {
            $languages[$i] = (object)$lang;
        }
        $opc              = Shop::Container()->getOPC();
        $opcPageService   = Shop::Container()->getOPCPageService();
        $response         = new IOResponse();
        $publicDraftkeys  = $opcPageService->getPublicPageKeys($curPageID);
        $newDraftListHtml = Shop::Smarty()
            ->assign('pageDrafts', $opcPageService->getDrafts($curPageID))
            ->assign('ShopURL', Shop::getURL())
            ->assign('adminSessionToken', $adminSessionToken)
            ->assign('languages', $languages)
            ->assign('currentLanguage', (object)$currentLanguage)
            ->assign('opcPageService', $opcPageService)
            ->assign('opc', $opc)
            ->assign('publicDraftKeys', $publicDraftkeys)
            ->assign('opcStartUrl', Shop::getAdminURL() . '/' . Route::OPC)
            ->fetch(\PFAD_ROOT . \PFAD_ADMIN . 'opc/tpl/draftlist.tpl');

        $response->assignDom('opc-draft-list', 'innerHTML', $newDraftListHtml);

        return $response;
    }

    public function setWishlistVisibility(int $wlID, bool $state, string $token): IOResponse
    {
        $ioResponse = new IOResponse();
        $wl         = Wishlist::instanceByID($wlID, $this->db);
        if ($wl->isSelfControlled() === false) {
            return $ioResponse;
        }
        if (Form::validateToken($token)) {
            $wl->setVisibility($state);
        }
        $response        = new stdClass();
        $response->wlID  = $wlID;
        $response->state = $state;
        $response->url   = $wl->getURL();

        $ioResponse->assignVar('response', $response);

        return $ioResponse;
    }

    /**
     * @since 5.3.0
     */
    public function setDeliveryaddressDefault(int $laID, string $token): IOResponse
    {
        $ioResponse      = new IOResponse();
        $response        = new stdClass();
        $response->laID  = $laID;
        $response->state = 0;
        if (Form::validateToken($token)) {
            $deliveryAddress  = new DeliveryAddressTemplate($this->db);
            $response->result = $deliveryAddress->setAsDefault($laID, Frontend::getCustomer()->getID());
        }

        $ioResponse->assignVar('response', $response);

        return $ioResponse;
    }

    /**
     * @param array<mixed> $args
     * @throws Exception
     * @since 5.3.0
     */
    public function rmaItems(array $args = []): IOResponse
    {
        $param = [];
        foreach ($args as $arg) {
            if (!isset($param[$arg['name']])) {
                $param[$arg['name']] = [$arg['value']];
            } else {
                $param[$arg['name']][] = $arg['value'];
            }
        }
        $ioResponse = new IOResponse();
        $response   = new stdClass();

        if (!Form::validateToken($param['jtl_token'][0])) {
            $response->result = false;
            $response->msg    = Shop::Lang()->get('missingToken', 'messages');
            $ioResponse->assignVar('response', $response);

            return $ioResponse;
        }
        if (!isset($param['quantity'])) {
            $param['quantity'] = [];
        }

        $customerID = Frontend::getCustomer()->getID();
        $languageID = Shop::getLanguageID();
        if ($customerID <= 0) {
            $response->result = false;
            $response->msg    = Shop::Lang()->get('rma_login', 'rma');
            $ioResponse->assignVar('response', $response);

            return $ioResponse;
        }
        $returnableProducts = $this->rmaService->getReturnableProducts(
            customerID: $customerID,
            languageID: $languageID,
            cancellationTime: Shopsetting::getInstance()->getInt('global_cancellation_time')
        );

        $rmaItems = new RMAItems();
        foreach ($param['quantity'] as $key => $quantity) {
            $returnableProduct = null;
            foreach ($returnableProducts->getArray() as $product) {
                if ($product->shippingNotePosID . '_' . $product->id === $quantity['posUniqueID']) {
                    $returnableProduct          = $product->toObject(true);
                    $returnableProduct->product = $product->getProduct();
                    break;
                }
            }
            if ($returnableProduct === null) {
                continue;
            }

            $rmaItems->append(
                new RMAItemDomainObject(
                    shippingNotePosID: Typifier::intify($returnableProduct->shippingNotePosID ?? 0),
                    productID: Typifier::intify($returnableProduct->product->kArtikel ?? null),
                    reasonID: Typifier::intify($param['reason'][$key]['value'] ?? null),
                    name: (string)($returnableProduct->name ?? ''),
                    quantity: Typifier::floatify($quantity['value'] ?? null, 1.00),
                    vat: Typifier::floatify($returnableProduct->vat ?? null),
                    unit: Typifier::stringify($returnableProduct->unit ?? null, null),
                    comment: Typifier::stringify($param['comment'][$key]['value'] ?? null, null),
                    createDate: \date('Y-m-d H:i:s'),
                    product: $returnableProduct->product ?? null,
                    reason: $this->rmaReasonService->getReason(
                        id: Typifier::intify($param['reason'][$key]['value'] ?? 0),
                        languageID: Typifier::intify($languageID)
                    )
                )
            );
        }

        $response->result = true;
        $response->html   = Shop::Smarty()->assign('rmaItems', $rmaItems)
            ->assign('rmaService', $this->rmaService)
            ->fetch('account/rma_itemlist.tpl');

        $ioResponse->assignVar('response', $response);

        return $ioResponse;
    }

    /**
     * @param array<mixed> $args
     * @throws Exception
     * @since 5.3.0
     */
    public function rmaSummary(array $args = []): IOResponse
    {
        $param = [];
        foreach ($args as $arg) {
            if (!isset($param[$arg['name']])) {
                $param[$arg['name']] = [$arg['value']];
                continue;
            }
            $param[$arg['name']][] = $arg['value'];
        }

        $ioResponse = new IOResponse();
        $response   = new stdClass();
        if (Form::validateToken($param['jtl_token'][0]) === false) {
            $response->result = false;
            $response->msg    = Shop::Lang()->get('missingToken', 'messages');
            $ioResponse->assignVar('response', $response);

            return $ioResponse;
        }

        $param['quantity'] ??= [];
        $customerID        = Frontend::getCustomer()->getID();
        $languageID        = Shop::getLanguageID();
        if ($customerID <= 0) {
            $response->result = false;
            $response->msg    = Shop::Lang()->get('rma_login', 'rma');
            $ioResponse->assignVar('response', $response);

            return $ioResponse;
        }

        $returnableProducts = $this->rmaService->getReturnableProducts(
            customerID: $customerID,
            languageID: $languageID,
            cancellationTime: Shopsetting::getInstance()->getInt('global_cancellation_time')
        );

        $rmaItems = new RMAItems();

        foreach ($param['quantity'] as $key => $quantity) {
            // Check if sent products are returnable and use data from DB instead of POST
            $returnableProduct = null;
            foreach ($returnableProducts->getArray() as $item) {
                if ($item->shippingNotePosID . '_' . $item->id === $quantity['posUniqueID']) {
                    $returnableProduct            = $item->toObject(true);
                    $returnableProduct->product   = $item->getProduct();
                    $returnableProduct->orderNo   = $item->getOrderNo();
                    $returnableProduct->orderDate = $item->getOrderDate();
                    break;
                }
            }
            if ($returnableProduct === null) {
                continue;
            }

            $rmaItems->append(
                new RMAItemDomainObject(
                    shippingNotePosID: Typifier::intify($returnableProduct->shippingNotePosID ?? null),
                    orderID: Typifier::intify($returnableProduct->orderID ?? null),
                    orderPosID: Typifier::intify($returnableProduct->orderPosID ?? null),
                    productID: Typifier::intify($returnableProduct->product->kArtikel ?? null),
                    reasonID: Typifier::intify($param['reason'][$key]['value'] ?? null),
                    name: (string)($returnableProduct->name ?? ''),
                    variationName: Typifier::stringify($returnableProduct->variationName ?? null, null),
                    variationValue: Typifier::stringify($returnableProduct->variationValue ?? null, null),
                    partListProductID: Typifier::intify($returnableProduct->partListProductID ?? null),
                    partListProductName: Typifier::stringify($returnableProduct->partListProductName ?? null, null),
                    partListProductURL: Typifier::stringify($returnableProduct->partListProductURL ?? null, null),
                    partListProductNo: Typifier::stringify($returnableProduct->partListProductNo ?? null, null),
                    quantity: Typifier::floatify($quantity['value'] ?? null, 1.00),
                    vat: Typifier::floatify($returnableProduct->vat ?? null),
                    unit: Typifier::stringify($returnableProduct->unit ?? null, null),
                    comment: Typifier::stringify($param['comment'][$key]['value'] ?? null, null),
                    createDate: \date('Y-m-d H:i:s'),
                    product: $returnableProduct->product,
                    reason: $this->rmaReasonService->getReason(
                        id: Typifier::intify($param['reason'][$key]['value'] ?? 0),
                        languageID: $languageID
                    ),
                    orderNo: $returnableProduct->orderNo,
                    orderDate: $returnableProduct->orderDate
                )
            );
        }

        $rmaDomainObject = new RMADomainObject(
            customerID: $customerID,
            createDate: \date('Y-m-d H:i:s'),
            items: $rmaItems,
            returnAddress: $this->rmaReturnAddressService->returnAddressFromDeliveryAddressTemplateID(
                deliveryAddressTemplateID: Typifier::intify($param['returnAddress'][0])
            )
        );
        // Save DO in session to use it in the next step (saveRMA)
        Frontend::set('rmaRequest', $rmaDomainObject);

        $response->result = true;
        $response->html   = Shop::Smarty()->assign('rmaService', $this->rmaService)
            ->assign('rmaReturnAddressService', $this->rmaReturnAddressService)
            ->assign('rma', $rmaDomainObject)
            ->fetch('account/rma_summary.tpl');

        $ioResponse->assignVar('response', $response);

        return $ioResponse;
    }

    /**
     * @param array<array<mixed>> $args
     */
    public function createShippingAddress(array $args = []): IOResponse
    {
        $param = [];
        foreach ($args as $arg) {
            $newName                 = \str_replace(
                ['[]', '[', ']'],
                ['_', '_', ''],
                $arg['name']
            );
            $param[(string)$newName] = $arg['value'];
        }
        $ioResponse = new IOResponse();
        $response   = new stdClass();
        if (!Form::validateToken($param['jtl_token'])) {
            $response->result = false;
            $response->msg    = Shop::Lang()->get('missingToken', 'messages');
            $ioResponse->assignVar('response', $response);

            return $ioResponse;
        }

        $customerID = Frontend::getCustomer()->getID();
        if ($customerID <= 0) {
            $response->result = false;
            $response->msg    = Shop::Lang()->get('rma_login', 'rma');
            $ioResponse->assignVar('response', $response);

            return $ioResponse;
        }

        $data                                = Text::filterXSS($param);
        $template                            = new DeliveryAddressTemplate($this->db);
        $template->kLieferadresse            = 0;
        $template->kKunde                    = $customerID;
        $template->cAnrede                   = $data['register_shipping_address_anrede'] ?? '';
        $template->cTitel                    = $data['register_shipping_address_titel'] ?? '';
        $template->cVorname                  = $data['register_shipping_address_vorname'] ?? '';
        $template->cNachname                 = $data['register_shipping_address_nachname'] ?? '';
        $template->cFirma                    = $data['register_shipping_address_firma'] ?? '';
        $template->cZusatz                   = $data['register_shipping_address_firmazusatz'] ?? '';
        $template->cStrasse                  = $data['register_shipping_address_strasse'] ?? '';
        $template->cHausnummer               = $data['register_shipping_address_hausnummer'] ?? '';
        $template->cAdressZusatz             = $data['register_shipping_address_adresszusatz'] ?? '';
        $template->cLand                     = $data['register_shipping_address_land'] ?? '';
        $template->cBundesland               = $data['register_shipping_address_bundesland'] ?? '';
        $template->cPLZ                      = $data['register_shipping_address_plz'] ?? '';
        $template->cOrt                      = $data['register_shipping_address_ort'] ?? '';
        $template->cMobil                    = $data['register_shipping_address_mobil'] ?? '';
        $template->cFax                      = $data['register_shipping_address_fax'] ?? '';
        $template->cTel                      = $data['register_shipping_address_tel'] ?? '';
        $template->cMail                     = $data['register_shipping_address_email'] ?? '';
        $template->nIstStandardLieferadresse = (int)($data['register_shipping_address_isDefault'] ?? 0);

        $returnAddressID = $template->persist();
        if ($returnAddressID > 0) {
            $response->result  = true;
            $selectOptions     = Shop::Smarty()->assign('returnAddresses', DeliveryAddressTemplate::getAll($customerID))
                ->assign('selectedID', $returnAddressID)
                ->fetch('account/returnaddress/form_option.tpl');
            $response->options = $selectOptions;
            $ioResponse->assignVar('response', $response);
        }
        return $ioResponse;
    }

    /**
     * @param array<mixed> $formData
     */
    public function updateWishlistItem(int $wlID, array $formData): IOResponse
    {
        $wishList = Wishlist::instanceByID($wlID, $this->db);
        if ($wishList->isSelfControlled() === true && Form::validateToken($formData['jtl_token'])) {
            Wishlist::update($wlID, $formData);
        }
        $ioResponse     = new IOResponse();
        $response       = new stdClass();
        $response->wlID = $wlID;

        $ioResponse->assignVar('response', $response);

        return $ioResponse;
    }

    /**
     * @param array<mixed> $formData
     * @throws Exception
     */
    public function updateReviewHelpful(array $formData): IOResponse
    {
        $_POST      = $formData;
        $controller = new ReviewController(
            $this->db,
            $this->cache,
            new State(),
            Shopsetting::getInstance($this->db, $this->cache)->getAll(),
            Shop::Container()->getAlertService()
        );
        if (Form::validateToken()) {
            $controller->updateWasHelpful(
                (int)($formData['a'] ?? 0),
                Frontend::getCustomer()->getID(),
                (int)($formData['btgseite'] ?? 0),
                (int)($formData['btgsterne'] ?? 0)
            );
        }
        $reviews = (new Artikel($this->db, null, null, $this->cache))
            ->fuelleArtikel(
                (int)($formData['a'] ?? 0),
                Artikel::getDetailOptions()
            )?->Bewertungen?->oBewertung_arr;

        $ioResponse       = new IOResponse();
        $response         = new stdClass();
        $response->review = first(
            flatten(
                filter(
                    $reviews ?? [],
                    static fn(stdClass $e): bool => (int)$e->kBewertung === (int)$formData['reviewID']
                )
            )
        );

        $ioResponse->assignVar('response', $response);

        return $ioResponse;
    }

    private function getBasketItemsTemplate(Cart $cart, JTLSmarty $smarty): void
    {
        $customerGroup   = Frontend::getCustomerGroup();
        $customerGroupID = $customerGroup->getID();
        $customer        = Frontend::getCustomer();
        $currency        = Frontend::getCurrency();
        $cartItems       = Frontend::getCart()->PositionenArr;
        $qty             = $cart->gibAnzahlPositionenExt([\C_WARENKORBPOS_TYP_ARTIKEL]);
        $country         = $_SESSION['cLieferlandISO'] ?? '';
        $plz             = '*';
        if ($customer->getID() > 0) {
            $customerGroupID = $customer->getGroupID();
            $country         = $customer->cLand;
            $plz             = $customer->cPLZ;
        }

        $freeShippingMethod = $this->shippingService->getFreeShippingMethod(
            $customer,
            $customerGroup,
            $currency,
            (string)$country,
            $cartItems,
            (string)$plz,
        );

        $smarty->assign('WarensummeLocalized', $cart->gibGesamtsummeWarenLocalized())
            ->assign('Warensumme', $cart->gibGesamtsummeWaren())
            ->assign('Steuerpositionen', $cart->gibSteuerpositionen())
            ->assign('Einstellungen', Shop::getSettings([\CONF_GLOBAL, \CONF_BILDER, \CONF_SONSTIGES]))
            ->assign('WarenkorbArtikelAnzahl', $qty)
            ->assignDeprecated(
                'WarenkorbArtikelPositionenanzahl',
                $qty,
                '5.4.0',
            )
            ->assign('zuletztInWarenkorbGelegterArtikel', $cart->gibLetztenWKArtikel())
            ->assign('WarenkorbGesamtgewicht', $cart->getWeight())
            ->assign('Warenkorbtext', Texts::cartContainsItems($cart))
            ->assign('NettoPreise', Frontend::getCustomerGroup()->getIsMerchant())
            ->assign(
                'FavourableShipping',
                $cart->getFavourableShipping()
            )
            ->assign(
                'WarenkorbVersandkostenfreiHinweis',
                $freeShippingMethod !== null
                    ? $this->shippingService->getShippingFreeString(
                        $freeShippingMethod,
                        $customerGroup->isMerchant(),
                        $currency,
                        $cartItems,
                        (string)$country,
                    ) : ''
            )
            ->assign(
                'nextFreeGiftMissingAmount',
                $this->freeGiftService->getNextAvailableMissingAmount(
                    $cart->gibGesamtsummeWarenExt([\C_WARENKORBPOS_TYP_ARTIKEL], true),
                    $customerGroupID,
                )
            )
            ->assign('oSpezialseiten_arr', Shop::Container()->getLinkService()->getSpecialPages())
            ->assign('favourableShippingString', $cart->favourableShippingString);

        if (!empty($country) && !empty($plz)) {
            $shippingMethods = $this->shippingService->getPossibleShippingMethods(
                $customer,
                $customerGroup,
                $country,
                $currency,
                $plz,
                $cartItems,
            );

            if (empty($shippingMethods) === false) {
                $smarty
                    ->assign(
                        'ArtikelabhaengigeVersandarten',
                        \array_map(
                            static fn(ShippingCartPositionDTO $dto): stdClass => $dto->toLegacyObject(),
                            $shippingMethods[0]->customShippingCosts
                        )
                    )
                    ->assign(
                        'Versandarten',
                        \array_map(
                            static fn(ShippingDTO $dto): stdClass => $dto->toLegacyObject(),
                            $shippingMethods
                        )
                    )
                    ->assign('Versandland', LanguageHelper::getCountryCodeByCountryName($country))
                    ->assign('VersandPLZ', Text::filterXSS($plz));
            }
            \executeHook(\HOOK_WARENKORB_PAGE_ERMITTLEVERSANDKOSTEN);
        }
    }

    /**
     * @param array<mixed> $valueIDs
     * @return array{array<int, array<int, mixed>>, array<int, array<int, mixed>>}
     */
    private function buildBulkPriceArray(Artikel $product, array $valueIDs, Currency $currency): array
    {
        $fStaffelVK = [0 => [], 1 => []];
        $cStaffelVK = [0 => [], 1 => []];
        foreach ($product->staffelPreis_arr as $staffelPreis) {
            $nAnzahl         = &$staffelPreis['nAnzahl'];
            $fStaffelVKNetto = (float)$product->gibPreis(
                $nAnzahl,
                $valueIDs,
                Frontend::getCustomerGroup()->getID()
            );

            $fStaffelVK[0][$nAnzahl] = Tax::getGross(
                $fStaffelVKNetto,
                $_SESSION['Steuersatz'][$product->kSteuerklasse]
            );
            $fStaffelVK[1][$nAnzahl] = $fStaffelVKNetto;
            $cStaffelVK[0][$nAnzahl] = Preise::getLocalizedPriceString(
                $fStaffelVK[0][$nAnzahl],
                $currency
            );
            $cStaffelVK[1][$nAnzahl] = Preise::getLocalizedPriceString(
                $fStaffelVK[1][$nAnzahl],
                $currency
            );
        }

        return [$fStaffelVK, $cStaffelVK];
    }

    /**
     * @return array{array<int, array<int, mixed>>, array<int, array<int, mixed>>}
     */
    private function buildBulkPriceRecomendationArray(Artikel $product): array
    {
        $fStaffelVPE = [0 => [], 1 => []];
        $cStaffelVPE = [0 => [], 1 => []];
        foreach ($product->staffelPreis_arr as $key => $staffelPreis) {
            $nAnzahl                  = &$staffelPreis['nAnzahl'];
            $fStaffelVPE[0][$nAnzahl] = $product->fStaffelpreisVPE_arr[$key][0];
            $fStaffelVPE[1][$nAnzahl] = $product->fStaffelpreisVPE_arr[$key][1];
            $cStaffelVPE[0][$nAnzahl] = $staffelPreis['cBasePriceLocalized'][0];
            $cStaffelVPE[1][$nAnzahl] = $staffelPreis['cBasePriceLocalized'][1];
        }

        return [$fStaffelVPE, $cStaffelVPE];
    }

    /**
     * @param array<mixed> $valueIDs
     */
    private function checkMoreDependencies(
        IOResponse $ioResponse,
        Artikel $product,
        CustomerGroup $customerGroup,
        array $valueIDs,
        float $weightDiff,
        string $newProductNr,
        float $amount,
        string $wrapper,
    ): IOResponse {
        $currency = Frontend::getCurrency();
        $isNet    = $customerGroup->getIsMerchant();
        $fVKNetto = (float)$product->gibPreis(
            $amount,
            $valueIDs,
            $customerGroup->getID()
        );
        $fVK      = $isNet
            ? $fVKNetto
            : Tax::getGross(
                $fVKNetto,
                $_SESSION['Steuersatz'][$product->kSteuerklasse]
            );

        if (!$product->bHasKonfig) {
            $priceLabel = '';
            if (($product->nVariationAnzahl ?? 0) > 0) {
                $priceLabel = $product->nVariationOhneFreifeldAnzahl === \count($valueIDs)
                    ? Shop::Lang()->get('priceAsConfigured', 'productDetails')
                    : Shop::Lang()->get('priceStarting');
            }
            $ioResponse->callEvoProductFunction(
                'setPrice',
                $fVK,
                Preise::getLocalizedPriceString(
                    $fVK,
                    $currency
                ),
                $priceLabel,
                $wrapper
            );
        }
        $unitWeightLabel = Shop::Lang()->get('weightUnit');
        $ioResponse->callEvoProductFunction(
            'setArticleWeight',
            [
                [
                    $product->fGewicht,
                    Separator::getUnit(
                        \JTL_SEPARATOR_WEIGHT,
                        Shop::getLanguageID(),
                        (float)$product->fGewicht + $weightDiff
                    ) . ' ' . $unitWeightLabel
                ],
                [
                    $product->fArtikelgewicht,
                    Separator::getUnit(
                        \JTL_SEPARATOR_WEIGHT,
                        Shop::getLanguageID(),
                        (float)$product->fArtikelgewicht + $weightDiff
                    ) . ' ' . $unitWeightLabel
                ],
            ],
            $wrapper
        );

        if (!empty($product->staffelPreis_arr)) {
            [$fStaffelVK, $cStaffelVK] = $this->buildBulkPriceArray($product, $valueIDs, $currency);
            $ioResponse->callEvoProductFunction(
                'setStaffelPrice',
                $fStaffelVK,
                $cStaffelVK,
                $wrapper
            );
        }

        if ($product->hasVPE()) {
            $product->baueVPE($fVKNetto);
            [$fStaffelVPE, $cStaffelVPE] = $this->buildBulkPriceRecomendationArray($product);
            $ioResponse->callEvoProductFunction(
                'setVPEPrice',
                $product->cLocalizedVPE[$isNet] ?? null,
                $fStaffelVPE[$isNet],
                $cStaffelVPE[$isNet],
                $wrapper
            );
        }

        if (!empty($newProductNr)) {
            $ioResponse->callEvoProductFunction('setProductNumber', $newProductNr, $wrapper);
        }

        return $ioResponse;
    }

    /**
     * @param array<mixed> $items
     * @param array<mixed> $quantities
     * @param array<mixed> $itemQuantities
     * @return array{array<int, stdClass>, array<Item>, array<int>}
     */
    private function getErrorsItemsAndGroups(
        int $languageID,
        int $customerGroupID,
        array $items,
        array $quantities,
        array $itemQuantities,
        int|float $amount,
    ): array {
        $itemErrors        = [];
        $invalidGroups     = [];
        $configItems       = [];
        $configGroups      = $items;
        $configGroupCounts = $quantities;
        $configItemCounts  = $itemQuantities;
        foreach ($configGroups as $itemList) {
            foreach ($itemList ?? [] as $configItemID) {
                $configItemID = (int)$configItemID;
                // 1.0
                if ($configItemID <= 0) {
                    continue;
                }
                $configItem    = (new Item($configItemID, $languageID, $customerGroupID))
                    ->setQuantities(
                        $amount,
                        $configItemCounts,
                        $configGroupCounts
                    );
                $configItems[] = $configItem;
                // Alle Artikel können in den WK gelegt werden?
                if ($configItem->getPosTyp() !== \KONFIG_ITEM_TYP_ARTIKEL) {
                    continue;
                }
                // Varikombi
                $configItem->oEigenschaftwerte_arr = [];
                /** @var Artikel $tmpProduct */
                $tmpProduct = $configItem->getArtikel();
                if (
                    $tmpProduct !== null
                    && $tmpProduct->kVaterArtikel > 0
                    && isset($tmpProduct->kEigenschaftKombi)
                    && $tmpProduct->kEigenschaftKombi > 0
                ) {
                    $configItem->oEigenschaftwerte_arr = Product::getVarCombiAttributeValues(
                        $tmpProduct->kArtikel ?? 0,
                        false
                    );
                }
                $tmpProduct->isKonfigItem = true;
                $redirectParam            = CartHelper::addToCartCheck(
                    $tmpProduct,
                    $configItem->fAnzahlWK ?? 0,
                    $configItem->oEigenschaftwerte_arr
                );
                if (\count($redirectParam) > 0) {
                    $productMessages = Product::getProductMessages(
                        $redirectParam,
                        true,
                        $configItem->getArtikel(),
                        $configItem->fAnzahlWK,
                        $configItem->getKonfigitem()
                    );

                    $itemErrors[$configItem->getKonfigitem()] = (object)[
                        'message' => $productMessages[0],
                        'group'   => $configItem->getKonfiggruppe()
                    ];

                    $invalidGroups[] = $configItem->getKonfiggruppe();
                }
            }
        }

        return [$itemErrors, $configItems, $invalidGroups];
    }

    /**
     * @param stdClass[]          $products
     * @param array<int, int>     $freetextValues
     * @param string|array<mixed> $layout
     * @param string|array<mixed> $wrapper
     */
    private function setChildProductContent(
        IOResponse $ioResponse,
        Artikel $product,
        array $products,
        array $freetextValues,
        int $childProductID,
        int $parentProductID,
        string|array $layout,
        string|array $wrapper,
    ): IOResponse {
        $tmpProduct              = $products[0];
        $gesetzteEigeschaftWerte = [];
        foreach ($freetextValues as $cKey => $cValue) {
            $gesetzteEigeschaftWerte[] = (object)[
                'key'   => $cKey,
                'value' => $cValue
            ];
        }
        $childHasOPCContent = $this->db->getSingleInt(
            "SELECT COUNT(kPage) AS count
                    FROM topcpage
                    WHERE cPageId LIKE '%\"type\":\"product\"%'
                        AND (
                            cPageId LIKE CONCAT('%\"id\":', :id,'%')
                            OR cPageId LIKE CONCAT('%\"id\":', :last_id,'%')
                            OR cPageId LIKE CONCAT('%\"id\":', :father_id,'%'))",
            'count',
            [
                'id'        => (int)$tmpProduct->kArtikel,
                'last_id'   => $childProductID,
                'father_id' => $parentProductID
            ]
        );
        if ($layout === 'gallery' || $childHasOPCContent > 0) {
            $ioResponse->callEvoProductFunction(
                'redirectToArticle',
                $parentProductID,
                $tmpProduct->kArtikel,
                URL::buildURL($tmpProduct, \URLART_ARTIKEL, true),
                $gesetzteEigeschaftWerte,
                $wrapper
            );
        } else {
            $ioResponse->callEvoProductFunction(
                'setArticleContent',
                $parentProductID,
                $tmpProduct->kArtikel,
                URL::buildURL($tmpProduct, \URLART_ARTIKEL, true),
                $gesetzteEigeschaftWerte,
                $wrapper
            );
        }
        \executeHook(\HOOK_TOOLSAJAXSERVER_PAGE_TAUSCHEVARIATIONKOMBI, [
            'objResponse' => &$ioResponse,
            'oArtikel'    => &$product,
            'bIO'         => true
        ]);

        return $ioResponse;
    }

    /**
     * @param string|array<mixed> $wrapper
     * @param array<mixed>        $set
     * @param array<mixed>        $possibleVariations
     */
    private function setVariationStockInfo(
        IOResponse $ioResponse,
        VariationValue $value,
        string|array $wrapper,
        int $parentProductID,
        array $set,
        array $possibleVariations,
    ): IOResponse {
        $id               = $value->kEigenschaft ?? 0;
        $stockInfo        = (object)[
            'stock'  => true,
            'status' => 2,
            'text'   => '',
        ];
        $stockInfo->stock = true;
        $stockInfo->text  = '';
        if (\in_array($value->kEigenschaftWert, ($possibleVariations[$id] ?? [])) === false) {
            $stockInfo->stock  = false;
            $stockInfo->status = 0;
            $stockInfo->text   = Shop::Lang()->get('notAvailableInSelection');
        } else {
            $ioResponse->callEvoProductFunction(
                'variationEnable',
                $id,
                $value->kEigenschaftWert,
                $wrapper
            );

            if (
                \array_key_exists($id, $set) === false
                && empty($set) === false
                && (\count($set) === \count($possibleVariations) - 1)
            ) {
                $set[$id] = $value->kEigenschaftWert;

                $products  = $this->getArticleByVariations($parentProductID, $set);
                $stockInfo = \count($products) === 1
                    ? $this->getArticleStockInfo((int)$products[0]->kArtikel)
                    : $stockInfo;
                unset($set[$id]);
            }
        }
        if ($value->notExists || !$value->inStock) {
            $stockInfo->stock  = false;
            $stockInfo->status = 0;
            $stockInfo->text   = $value->notExists
                ? Shop::Lang()->get('notAvailableInSelection')
                : Shop::Lang()->get('ampelRot');
        }
        if (!$stockInfo->stock) {
            $ioResponse->callEvoProductFunction(
                'variationInfo',
                $value->kEigenschaftWert,
                $stockInfo->status,
                $stockInfo->text,
                $value->notExists,
                $wrapper
            );
        }

        return $ioResponse;
    }

    /**
     * @param string|array<mixed> $wrapper
     * @return array{IOResponse, array<int, string>, bool}
     */
    private function selectedInvalidVariation(
        IOResponse $ioResponse,
        Artikel $product,
        int $propertyID,
        int $propertyValueID,
        int $childProductID,
        string|array $wrapper,
    ): array {
        $ioResponse->callEvoProductFunction('variationResetAll', $wrapper);
        $set               = [$propertyID => (string)$propertyValueID];
        $invalidVariations = $product->getVariationsBySelection($set, true);
        // Auswählter EigenschaftWert ist ebenfalls nicht vorhanden
        if (\in_array($propertyValueID, ($invalidVariations[$propertyID] ?? []))) {
            $set = [];
            // Wir befinden uns im Kind-Artikel -> Weiterleitung auf Vater-Artikel
            if ($childProductID > 0) {
                $ioResponse->callEvoProductFunction(
                    'setArticleContent',
                    $product->kArtikel,
                    0,
                    $product->cURL,
                    [],
                    $wrapper
                );

                return [$ioResponse, $set, true];
            }
        }

        return [$ioResponse, $set, false];
    }
}
