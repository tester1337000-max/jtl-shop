<?php

declare(strict_types=1);

namespace JTL\Customer;

use DateTime;
use Exception;
use Illuminate\Support\Collection;
use JTL\Alert\Alert;
use JTL\Campaign;
use JTL\Cart\CartHelper;
use JTL\Cart\PersistentCart;
use JTL\Catalog\ComparisonList;
use JTL\Catalog\Product\Artikel;
use JTL\Catalog\Product\Preise;
use JTL\Catalog\Wishlist\Wishlist;
use JTL\CheckBox;
use JTL\Checkout\Bestellung;
use JTL\Checkout\DeliveryAddressTemplate;
use JTL\Checkout\Kupon;
use JTL\Customer\Registration\Form as CustomerForm;
use JTL\DB\DbInterface;
use JTL\Extensions\Config\Item;
use JTL\Extensions\Download\Download;
use JTL\Extensions\Upload\File;
use JTL\GeneralDataProtection\Journal;
use JTL\Helpers\Date;
use JTL\Helpers\Form;
use JTL\Helpers\Product;
use JTL\Helpers\Request;
use JTL\Helpers\Tax;
use JTL\Helpers\Text;
use JTL\Language\LanguageHelper;
use JTL\Language\Texts;
use JTL\Link\SpecialPageNotFoundException;
use JTL\Pagination\Pagination;
use JTL\RMA\Services\RMAHistoryService;
use JTL\RMA\Services\RMAReasonService;
use JTL\RMA\Services\RMAReturnAddressService;
use JTL\RMA\Services\RMAService;
use JTL\Services\JTL\AlertServiceInterface;
use JTL\Services\JTL\LinkServiceInterface;
use JTL\Session\Frontend;
use JTL\Settings\Option\Checkout;
use JTL\Settings\Option\Customer as CustomerOption;
use JTL\Settings\Option\Globals;
use JTL\Settings\Settings;
use JTL\Shipping\Services\ShippingService;
use JTL\Shop;
use JTL\Shopsetting;
use JTL\Smarty\JTLSmarty;
use JTL\TwoFA\FrontendTwoFA;
use JTL\TwoFA\FrontendUserData;
use stdClass;

use function Functional\some;

/**
 * Class AccountController
 * @package JTL\Customer
 */
class AccountController
{
    private Settings $settings;

    /**
     * @var array<int, stdClass>
     */
    private array $currencies = [];

    private ShippingService $shippingService;

    public function __construct(
        private readonly DbInterface $db,
        private readonly AlertServiceInterface $alertService,
        private readonly LinkServiceInterface $linkService,
        private readonly JTLSmarty $smarty,
        private readonly RMAService $rmaService = new RMAService(),
        private readonly RMAReasonService $rmaReasonService = new RMAReasonService(),
        ?ShippingService $shippingService = null,
    ) {
        $this->shippingService = $shippingService ?? Shop::Container()->getShippingService();
        $this->settings        = Settings::fromAll();
    }

    /**
     * @throws Exception
     */
    public function handleRequest(): Customer
    {
        $this->smarty->assign('showTwoFAForm', false);
        Shop::setPageType(\PAGE_MEINKONTO);
        $step = 'login';
        $this->handleRedirects();
        $customer   = $this->handleLoginRequest();
        $customerID = $customer->getID();
        if ($customerID > 0) {
            $step = $this->handleCustomerRequest($customer);
        }
        $this->handleMessages($step, $customerID);
        $this->setCurrentLink();
        $this->smarty->assign('twoFAEnabled', false)
            ->assign('step', $step);
        $this->assignTwoFAData($customerID);

        return $customer;
    }

    /**
     * @throws Exception
     */
    private function handleCustomerRequest(Customer $customer): string
    {
        Shop::setPageType(\PAGE_MEINKONTO);
        $customerID = $customer->getID();
        $step       = 'mein Konto';
        $valid      = Form::validateToken();
        if (Request::verifyGPCDataInt('logout') === 1) {
            $this->logout();
        }
        if ($valid && ($uploadID = Request::verifyGPCDataInt('kUpload')) > 0) {
            $this->getFile($uploadID, $customerID);
        }
        if (Request::verifyGPCDataInt('del') === 1) {
            $this->checkOpenOrders();
            $step = 'account loeschen';
        }
        $this->checkPersistentCartActions($customerID);
        if ($valid && Request::verifyGPCDataInt('wllo') > 0) {
            $step = 'mein Konto';
            $this->alertService->addNotice(Wishlist::delete(Request::verifyGPCDataInt('wllo')), 'wllo');
        }
        if ($valid && Request::pInt('wls') > 0) {
            $step = 'mein Konto';
            $this->alertService->addNotice(Wishlist::setDefault(Request::verifyGPCDataInt('wls')), 'wls');
        }
        if ($valid && Request::pInt('wlh') > 0) {
            $step = 'mein Konto';
            $name = Text::htmlentities(Text::filterXSS(Request::pString('cWunschlisteName')));
            $this->alertService->addNotice(Wishlist::save($name), 'saveWL');
        }
        $wishlistID = Request::verifyGPCDataInt('wl');
        if ($wishlistID > 0) {
            $step = $this->modifyWishlist($customerID, $wishlistID);
        }
        if (Request::verifyGPCDataInt('editRechnungsadresse') > 0) {
            $step = 'rechnungsdaten';
        }
        if (Request::gInt('pass') === 1) {
            $step = 'passwort aendern';
        } elseif (Request::gInt('twofa') === 1 && $this->settings->bool(CustomerOption::ENABLE_2FA)) {
            $step = 'manageTwoFA';
        }
        if (Request::verifyGPCDataInt('editLieferadresse') > 0 || Request::gInt('editAddress') > 0) {
            $step = 'lieferadressen';
        }
        $rmaID = Request::verifyGPCDataInt('showRMA');
        if ($rmaID > 0) {
            $step = $this->showRMA($rmaID, $customerID);
        }
        if (Request::hasGPCData('newRMA')) {
            $step = $this->newRMA($customerID, Request::verifyGPCDataInt('newRMA'));
        }
        if (Request::verifyGPCDataInt('returns') > 0) {
            $step = $this->rmaOrders();
        }
        if (Request::hasGPCData('rmaCreateDateHash')) {
            $step = $this->saveRMA($customerID, Request::verifyGPDataString('rmaCreateDateHash'));
        }
        $this->checkEditDeliveryAddress($customer);
        if ($valid && Request::pInt('edit') === 1) {
            $customer = $this->changeCustomerData($customer);
        }
        if ($valid && Request::postInt('manage_two_fa') === 1 && Request::pInt('twoFACustomerID') > 0) {
            $this->saveTwoFA(Request::pInt('twoFACustomerID'));
            $step = 'mein Konto';
        }
        if ($valid && Request::pInt('pass_aendern') > 0) {
            $step = $this->changePassword($customerID);
        }
        if (Request::verifyGPCDataInt('bestellungen') > 0) {
            $step = 'bestellungen';
        }
        if (Request::verifyGPCDataInt('wllist') > 0) {
            $step = 'wunschliste';
        }
        if (Request::verifyGPCDataInt('bewertungen') > 0) {
            $step = 'bewertungen';
        }
        if (Request::verifyGPCDataInt('bestellung') > 0) {
            $step = $this->viewOrder($customerID);
        }
        if ($valid && Request::pInt('del_acc') === 1) {
            $this->deleteAccount($customer);
        }
        if ($step === 'mein Konto' || $step === 'bestellungen') {
            $this->viewOrders($customerID);
        }
        if ($step === 'mein Konto' || $step === 'wunschliste') {
            $this->smarty->assign('oWunschliste_arr', Wishlist::getWishlists());
        }
        if ($step === 'mein Konto') {
            $this->getRmaOverview($customerID);
        }
        if ($step === 'rechnungsdaten') {
            $this->getCustomerFields($customer);
        }
        if ($step === 'lieferadressen') {
            $this->getDeliveryAddresses();
        }
        $this->getRatings($customerID, $step);
        $customer->cGuthabenLocalized = Preise::getLocalizedPriceString($customer->fGuthaben, Frontend::getCurrency());
        $this->smarty->assign('Kunde', $customer)
            ->assign('customerAttributes', $customer->getCustomerAttributes())
            ->assignDeprecated('BESTELLUNG_STATUS_BEZAHLT', \BESTELLUNG_STATUS_BEZAHLT, '5.4.0')
            ->assignDeprecated('BESTELLUNG_STATUS_VERSANDT', \BESTELLUNG_STATUS_VERSANDT, '5.4.0')
            ->assignDeprecated('BESTELLUNG_STATUS_OFFEN', \BESTELLUNG_STATUS_OFFEN, '5.4.0')
            ->assign('nAnzeigeOrt', \CHECKBOX_ORT_KUNDENDATENEDITIEREN);

        return $step;
    }

    private function checkEditDeliveryAddress(Customer $customer): void
    {
        if (Request::verifyGPCDataInt('editLieferadresse') <= 0) {
            return;
        }
        $valid = Form::validateToken();
        if ($valid && Request::verifyGPDataString('editAddress') === 'neu') {
            $this->saveShippingAddress($customer);
        }
        if (Request::gInt('editAddress') > 0) {
            $this->loadShippingAddress($customer);
        }
        if ($valid && Request::pInt('updateAddress') > 0) {
            $this->updateShippingAddress($customer);
        }
        if ($valid && Request::gInt('deleteAddress') > 0) {
            $this->deleteShippingAddress($customer);
        }
        if ($valid && Request::gInt('setAddressAsDefault') > 0) {
            $this->setShippingAddressAsDefault($customer);
        }
    }

    public function login(
        string $userLogin,
        #[\SensitiveParameter] string $passLogin,
        ?string $twoFACode = null
    ): Customer {
        $customer = new Customer();
        if (Form::validateToken() === false) {
            $this->alertService->addNotice(Shop::Lang()->get('csrfValidationFailed'), 'csrfValidationFailed');
            Shop::Container()->getLogService()->warning(
                'CSRF-Warnung für Login: {name}',
                ['name' => Request::pString('login')]
            );

            return $customer;
        }
        $captchaState = $customer->verifyLoginCaptcha($_POST);
        if ($captchaState === true) {
            $returnCode = $customer->holLoginKunde($userLogin, $passLogin, $twoFACode);
        } else {
            $returnCode               = Customer::ERROR_CAPTCHA;
            $customer->nLoginversuche = $captchaState;
        }
        if ($returnCode === Customer::OK && $customer->getID() > 0) {
            $this->initCustomer($customer);
            $_SESSION['loginDate'] = (new DateTime())->getTimestamp();

            return $customer;
        }
        $this->handleError($returnCode, $customer);

        return $customer;
    }

    private function handleError(int $returnCode, Customer $customer): void
    {
        switch ($returnCode) {
            case Customer::ERROR_LOCKED:
                $this->alertService->addNotice(Shop::Lang()->get('accountLocked'), 'accountLocked');
                break;
            case Customer::ERROR_INACTIVE:
                $this->alertService->addNotice(Shop::Lang()->get('accountInactive'), 'accountInactive');
                break;
            case Customer::ERROR_NOT_ACTIVATED_YET:
                $this->alertService->addNotice(Shop::Lang()->get('loginNotActivated'), 'loginNotActivated');
                break;
            case Customer::ERROR_DO_TWO_FA:
                $_SESSION['oldPost'] = $_POST;
                $this->alertService->addAlert(
                    Alert::TYPE_NOTE,
                    Shop::Lang()->get('accountSetTwoFA'),
                    'accountRequires2FA'
                );
                $this->smarty->assign('showTwoFAForm', true);
                break;
            case Customer::ERROR_INVALID_TWO_FA:
                $this->alertService->addAlert(
                    Alert::TYPE_NOTE,
                    Shop::Lang()->get('accountInvalidTwoFA'),
                    'accountInv2FA'
                );
                $this->smarty->assign('showTwoFAForm', true);
                break;
            default:
                $this->checkLoginCaptcha($customer->nLoginversuche);
                $this->alertService->addNotice(Shop::Lang()->get('incorrectLogin'), 'incorrectLogin');
                break;
        }
    }

    private function checkLoginCaptcha(int $tries): void
    {
        $maxAttempts = $this->settings->int(CustomerOption::MAX_LOGIN_TRIES);
        if ($maxAttempts > 1 && $tries >= $maxAttempts) {
            $_SESSION['showLoginCaptcha'] = true;
        }
    }

    /**
     * @throws Exception
     */
    public function initCustomer(Customer $customer): void
    {
        unset($_SESSION['showLoginCaptcha'], $_SESSION['oldPost']);
        $coupons = $this->getCoupons();
        // create new session id to prevent session hijacking
        \session_regenerate_id();
        $visitor = Frontend::getVisitor();
        if ($visitor !== null && $visitor->kBesucher > 0) {
            $this->db->update(
                'tbesucher',
                'kBesucher',
                (int)$visitor->kBesucher,
                (object)['kKunde' => $customer->getID()]
            );
        }
        $this->updateCustomerLanguage($customer->getLanguageID());
        if ($customer->cAktiv !== 'Y') {
            $customer->kKunde = 0;
            $this->alertService->addNotice(Shop::Lang()->get('loginNotActivated'), 'loginNotActivated');
            return;
        }
        $this->updateSession($customer->getID());
        $session = Frontend::getInstance();
        $session->setCustomer($customer);
        if (Frontend::getCustomer()->getGroupID() !== Frontend::getCustomerGroup()->getID()) {
            Frontend::getCustomer()->kKundengruppe = Frontend::getCustomerGroup()->getID();
            $this->alertService->addWarning(Shop::Lang()->get('accountInvalidGroup'), 'accountInvalidGroup');
        }
        Wishlist::persistInSession();
        $persCartLoaded = $this->settings->bool(Checkout::SAVE_BASKET_ENABLED)
            && $this->loadPersistentCart($customer);
        $this->pruefeWarenkorbArtikelSichtbarkeit($customer->getGroupID());
        \executeHook(\HOOK_JTL_PAGE_REDIRECT);
        CartHelper::checkAdditions();
        $this->checkURLRedirect();
        if (!$persCartLoaded && $this->settings->bool(Checkout::SAVE_BASKET_ENABLED)) {
            if ($this->settings->string(Checkout::COMBINE_BASKETS) === 'Y') {
                $this->setzeWarenkorbPersInWarenkorb($customer->getID());
            } elseif ($this->settings->string(Checkout::COMBINE_BASKETS) === 'P') {
                $persCart = new PersistentCart($customer->getID(), false, $this->db);
                if (\count($persCart->getItems()) > 0) {
                    $this->smarty->assign('nWarenkorb2PersMerge', 1);
                } else {
                    $this->setzeWarenkorbPersInWarenkorb($customer->getID());
                }
            }
        }
        $this->checkCoupons($coupons);
        Shop::Container()->getLinkService()->reset();
    }

    private function updateCustomerLanguage(int $languageID): void
    {
        $isoLang = Shop::Lang()->getIsoFromLangID($languageID);
        if ((int)$_SESSION['kSprache'] !== $languageID && $isoLang !== null && !empty($isoLang->cISO)) {
            $_SESSION['kSprache']        = $languageID;
            $_SESSION['cISOSprache']     = $isoLang->cISO;
            $_SESSION['currentLanguage'] = LanguageHelper::getAllLanguages(1)[$languageID];
            Shop::setLanguage($languageID, $isoLang->cISO);
            Shop::Lang()->setzeSprache($isoLang->cISO);
        }
    }

    private function checkURLRedirect(): void
    {
        $url = Text::filterXSS(Request::verifyGPDataString('cURL'));
        if (\mb_strlen($url) > 0) {
            if (!\str_starts_with($url, 'http')) {
                $url = Shop::getURL() . '/' . \ltrim($url, '/');
            }
            \header('Location: ' . $url, true, 301);
            exit;
        }
    }

    private function updateSession(int $customerID): void
    {
        unset(
            $_SESSION['Zahlungsart'],
            $_SESSION['Versandart'],
            $_SESSION['Lieferadresse'],
            $_SESSION['Lieferadressevorlage'],
            $_SESSION['ks'],
            $_SESSION['VersandKupon'],
            $_SESSION['NeukundenKupon'],
            $_SESSION['Kupon']
        );
        Campaign::setCampaignAction(\KAMPAGNE_DEF_LOGIN, $customerID, 1.0); // Login
    }

    /**
     * @return Kupon[]
     */
    private function getCoupons(): array
    {
        $coupons   = [];
        $coupons[] = !empty($_SESSION['VersandKupon']) ? $_SESSION['VersandKupon'] : null;
        $coupons[] = !empty($_SESSION['oVersandfreiKupon']) ? $_SESSION['oVersandfreiKupon'] : null;
        $coupons[] = !empty($_SESSION['NeukundenKupon']) ? $_SESSION['NeukundenKupon'] : null;
        $coupons[] = !empty($_SESSION['Kupon']) ? $_SESSION['Kupon'] : null;

        return \array_filter($coupons);
    }

    /**
     * @param Kupon[] $coupons
     */
    private function checkCoupons(array $coupons): void
    {
        foreach ($coupons as $coupon) {
            if (!\method_exists($coupon, 'check')) {
                continue;
            }
            $error      = $coupon->check();
            $returnCode = Form::hasNoMissingData($error);
            \executeHook(\HOOK_WARENKORB_PAGE_KUPONANNEHMEN_PLAUSI, [
                'error'        => &$error,
                'nReturnValue' => &$returnCode
            ]);
            if ($returnCode) {
                if (isset($coupon->kKupon) && $coupon->kKupon > 0 && $coupon->cKuponTyp === Kupon::TYPE_STANDARD) {
                    $coupon->accept();
                    \executeHook(\HOOK_WARENKORB_PAGE_KUPONANNEHMEN);
                } elseif (!empty($coupon->kKupon) && $coupon->cKuponTyp === Kupon::TYPE_SHIPPING) {
                    // Versandfrei Kupon
                    $_SESSION['oVersandfreiKupon'] = $coupon;
                    $this->smarty->assign(
                        'cVersandfreiKuponLieferlaender_arr',
                        \explode(';', $coupon->cLieferlaender ?? '')
                    );
                }
            } else {
                Frontend::getCart()->loescheSpezialPos(\C_WARENKORBPOS_TYP_KUPON);
                Kupon::mapCouponErrorMessage($error['ungueltig']);
            }
        }
    }

    private function loadPersistentCart(Customer $customer): bool
    {
        $cart = Frontend::getCart();
        if (\count($cart->PositionenArr) > 0) {
            return false;
        }
        $persCart = new PersistentCart($customer->getID(), false, $this->db);
        $persCart->ueberpruefePositionen(true);
        if (\count($persCart->getItems()) === 0) {
            return false;
        }
        $languageID      = Shop::getLanguageID();
        $customerGroupID = $customer->getGroupID();
        foreach ($persCart->getItems() as $item) {
            if (!empty($item->Artikel->bHasKonfig)) {
                continue;
            }
            // Gratisgeschenk in Warenkorb legen
            if ($item->nPosTyp === \C_WARENKORBPOS_TYP_GRATISGESCHENK) {
                $productID = $item->kArtikel;
                $present   = $this->db->getSingleObject(
                    'SELECT tartikelattribut.kArtikel, tartikel.fLagerbestand, 
                        tartikel.cLagerKleinerNull, tartikel.cLagerBeachten
                        FROM tartikelattribut
                        JOIN tartikel 
                            ON tartikel.kArtikel = tartikelattribut.kArtikel
                        WHERE tartikelattribut.kArtikel = :pid
                            AND tartikelattribut.cName = :atr
                            AND CAST(tartikelattribut.cWert AS DECIMAL) <= :sum',
                    [
                        'pid' => $productID,
                        'atr' => \FKT_ATTRIBUT_GRATISGESCHENK,
                        'sum' => $cart->gibGesamtsummeWarenExt([\C_WARENKORBPOS_TYP_ARTIKEL], true)
                    ]
                );
                if (
                    $present !== null && $present->kArtikel > 0
                    && ($present->fLagerbestand > 0
                        || $present->cLagerKleinerNull === 'Y'
                        || $present->cLagerBeachten === 'N')
                ) {
                    \executeHook(\HOOK_WARENKORB_PAGE_GRATISGESCHENKEINFUEGEN);
                    $cart->loescheSpezialPos(\C_WARENKORBPOS_TYP_GRATISGESCHENK)
                        ->fuegeEin($productID, 1, [], \C_WARENKORBPOS_TYP_GRATISGESCHENK);
                }
                // Konfigitems ohne Artikelbezug
            } elseif ($item->kArtikel === 0 && !empty($item->kKonfigitem)) {
                $configItem = new Item($item->kKonfigitem, $languageID, $customerGroupID);
                $cart->erstelleSpezialPos(
                    $configItem->getName(),
                    $item->fAnzahl,
                    $configItem->getPreis(),
                    $configItem->getSteuerklasse(),
                    \C_WARENKORBPOS_TYP_ARTIKEL,
                    false,
                    !Frontend::getCustomerGroup()->isMerchant(),
                    '',
                    $item->cUnique,
                    $item->kKonfigitem,
                    $item->kArtikel,
                    $item->cResponsibility
                );
            } else {
                CartHelper::addProductIDToCart(
                    $item->kArtikel,
                    $item->fAnzahl,
                    $item->oWarenkorbPersPosEigenschaft_arr,
                    1,
                    $item->cUnique,
                    $item->kKonfigitem,
                    null,
                    false,
                    $item->cResponsibility
                );
            }
        }
        $cart->setzePositionsPreise();

        return true;
    }

    /**
     * Prüfe ob Artikel im Warenkorb vorhanden sind, welche für den aktuellen Kunden nicht mehr sichtbar sein dürfen
     */
    private function pruefeWarenkorbArtikelSichtbarkeit(int $customerGroupID): void
    {
        $cart = Frontend::getCart();
        if ($customerGroupID <= 0 || empty($cart->PositionenArr)) {
            return;
        }
        foreach ($cart->PositionenArr as $i => $item) {
            if ($item->nPosTyp !== \C_WARENKORBPOS_TYP_ARTIKEL || !empty($item->cUnique)) {
                continue;
            }
            $visibility = $item->kArtikel !== null
                && Product::checkProductVisibility($item->kArtikel, $customerGroupID, $this->db);
            if ($visibility === false && (int)$item->kKonfigitem === 0) {
                unset($cart->PositionenArr[$i]);
            }
            $price = $this->db->getSingleObject(
                'SELECT tpreisdetail.fVKNetto
                    FROM tpreis
                    INNER JOIN tpreisdetail 
                        ON tpreisdetail.kPreis = tpreis.kPreis
                        AND tpreisdetail.nAnzahlAb = 0
                    WHERE tpreis.kArtikel = :productID
                        AND tpreis.kKundengruppe = :customerGroup',
                ['productID' => $item->kArtikel, 'customerGroup' => $customerGroupID]
            );
            if (!isset($price->fVKNetto)) {
                unset($cart->PositionenArr[$i]);
            }
        }
    }

    public function setzeWarenkorbPersInWarenkorb(int $customerID): bool
    {
        if (!$customerID) {
            return false;
        }
        $cart = Frontend::getCart();
        $pers = PersistentCart::getInstance($customerID, false, $this->db);
        foreach ($cart->PositionenArr as $item) {
            if ($item->nPosTyp === \C_WARENKORBPOS_TYP_GRATISGESCHENK) {
                $productID = (int)$item->kArtikel;
                $present   = $this->db->getSingleObject(
                    'SELECT tartikelattribut.kArtikel, tartikel.fLagerbestand,
                       tartikel.cLagerKleinerNull, tartikel.cLagerBeachten
                        FROM tartikelattribut
                        JOIN tartikel 
                            ON tartikel.kArtikel = tartikelattribut.kArtikel
                        WHERE tartikelattribut.kArtikel = :pid
                            AND tartikelattribut.cName = :atr
                            AND CAST(tartikelattribut.cWert AS DECIMAL) <= :sum',
                    [
                        'pid' => $productID,
                        'atr' => \FKT_ATTRIBUT_GRATISGESCHENK,
                        'sum' => $cart->gibGesamtsummeWarenExt([\C_WARENKORBPOS_TYP_ARTIKEL], true)
                    ]
                );
                if ($present !== null && $present->kArtikel > 0) {
                    $pers->check($productID, 1, [], false, 0, \C_WARENKORBPOS_TYP_GRATISGESCHENK);
                }
            } else {
                $pers->check(
                    (int)$item->kArtikel,
                    $item->nAnzahl,
                    $item->WarenkorbPosEigenschaftArr,
                    $item->cUnique,
                    (int)$item->kKonfigitem,
                    (int)$item->nPosTyp,
                    $item->cResponsibility
                );
            }
        }
        $cart->PositionenArr = [];
        $customerGroupID     = Frontend::getCustomer()->getGroupID();
        $languageID          = Shop::getLanguageID();
        foreach (PersistentCart::getInstance($customerID, false, $this->db)->getItems() as $item) {
            if ($item->nPosTyp === \C_WARENKORBPOS_TYP_GRATISGESCHENK) {
                $productID = $item->kArtikel;
                $present   = $this->db->getSingleObject(
                    'SELECT tartikelattribut.kArtikel, tartikel.fLagerbestand,
                       tartikel.cLagerKleinerNull, tartikel.cLagerBeachten
                        FROM tartikelattribut
                        JOIN tartikel 
                            ON tartikel.kArtikel = tartikelattribut.kArtikel
                        WHERE tartikelattribut.kArtikel = :pid
                            AND tartikelattribut.cName = :atr
                            AND CAST(tartikelattribut.cWert AS DECIMAL) <= :sum',
                    [
                        'pid' => $productID,
                        'atr' => \FKT_ATTRIBUT_GRATISGESCHENK,
                        'sum' => $cart->gibGesamtsummeWarenExt([\C_WARENKORBPOS_TYP_ARTIKEL], true)
                    ]
                );
                if ($present !== null && $present->kArtikel > 0) {
                    if (
                        $present->fLagerbestand <= 0
                        && $present->cLagerKleinerNull === 'N'
                        && $present->cLagerBeachten === 'Y'
                    ) {
                        break;
                    }
                    \executeHook(\HOOK_WARENKORB_PAGE_GRATISGESCHENKEINFUEGEN);
                    $cart->loescheSpezialPos(\C_WARENKORBPOS_TYP_GRATISGESCHENK)
                        ->fuegeEin($productID, 1, [], \C_WARENKORBPOS_TYP_GRATISGESCHENK);
                }
            } else {
                $tmpProduct = new Artikel($this->db);
                $tmpProduct->fuelleArtikel(
                    $item->kArtikel,
                    $item->kKonfigitem === 0
                        ? Artikel::getDefaultOptions()
                        : Artikel::getDefaultConfigOptions(),
                    $customerGroupID,
                    $languageID
                );
                $tmpProduct->isKonfigItem = ($item->kKonfigitem > 0);
                if (
                    (int)$tmpProduct->kArtikel > 0
                    && \count(
                        CartHelper::addToCartCheck(
                            $tmpProduct,
                            $item->fAnzahl,
                            $item->oWarenkorbPersPosEigenschaft_arr
                        )
                    ) === 0
                ) {
                    CartHelper::addProductIDToCart(
                        $item->kArtikel,
                        $item->fAnzahl,
                        $item->oWarenkorbPersPosEigenschaft_arr,
                        1,
                        $item->cUnique,
                        $item->kKonfigitem,
                        null,
                        true,
                        $item->cResponsibility
                    );
                } elseif ($item->kKonfigitem > 0 && $item->kArtikel === 0) {
                    $configItem = new Item($item->kKonfigitem);
                    $cart->erstelleSpezialPos(
                        $configItem->getName(),
                        $item->fAnzahl,
                        $configItem->getPreis(),
                        $configItem->getSteuerklasse(),
                        \C_WARENKORBPOS_TYP_ARTIKEL,
                        false,
                        !Frontend::getCustomerGroup()->isMerchant(),
                        '',
                        $item->cUnique,
                        $configItem->getKonfigitem(),
                        $configItem->getArtikelKey()
                    );
                } else {
                    Shop::Container()->getAlertService()->addWarning(
                        \sprintf(Shop::Lang()->get('cartPersRemoved', 'errorMessages'), $item->cArtikelName),
                        'cartPersRemoved' . $item->kArtikel,
                        ['saveInSession' => true]
                    );
                }
            }
        }

        return true;
    }

    /**
     * Redirect - Falls jemand eine Aktion durchführt die ein Kundenkonto beansprucht und der Gast nicht einloggt ist,
     * wird dieser hier her umgeleitet und es werden die passenden Parameter erstellt. Nach dem erfolgreichen Einloggen
     * wird die zuvor angestrebte Aktion durchgeführt.
     */
    private function getRedirect(int $code): stdClass
    {
        $redir = new stdClass();

        switch ($code) {
            case \R_LOGIN_WUNSCHLISTE:
                $redir->oParameter_arr   = [];
                $tmp                     = new stdClass();
                $tmp->Name               = \QUERY_PARAM_PRODUCT;
                $tmp->Wert               = Request::verifyGPCDataInt(\QUERY_PARAM_PRODUCT);
                $redir->oParameter_arr[] = $tmp;
                $tmp                     = new stdClass();
                $tmp->Name               = 'n';
                $tmp->Wert               = Request::verifyGPCDataInt('n');
                $redir->oParameter_arr[] = $tmp;
                $tmp                     = new stdClass();
                $tmp->Name               = 'Wunschliste';
                $tmp->Wert               = 1;
                $redir->oParameter_arr[] = $tmp;
                $redir->nRedirect        = \R_LOGIN_WUNSCHLISTE;
                $redir->cURL             = $this->linkService->getStaticRoute('wunschliste.php', false);
                $redir->cName            = Shop::Lang()->get('wishlist', 'redirect');
                break;
            case \R_LOGIN_BEWERTUNG:
                $redir->oParameter_arr   = [];
                $tmp                     = new stdClass();
                $tmp->Name               = \QUERY_PARAM_PRODUCT;
                $tmp->Wert               = Request::verifyGPCDataInt(\QUERY_PARAM_PRODUCT);
                $redir->oParameter_arr[] = $tmp;
                $tmp                     = new stdClass();
                $tmp->Name               = 'bfa';
                $tmp->Wert               = 1;
                $redir->oParameter_arr[] = $tmp;
                $redir->nRedirect        = \R_LOGIN_BEWERTUNG;
                $redir->cURL             = $this->linkService->getStaticRoute('bewertung.php')
                    . '?' . \QUERY_PARAM_PRODUCT . '='
                    . Request::verifyGPCDataInt(\QUERY_PARAM_PRODUCT)
                    . '&bfa=1&token=' . $_SESSION['jtl_token'];
                $redir->cName            = Shop::Lang()->get('review', 'redirect');
                break;
            case \R_LOGIN_TAG:
                $redir->oParameter_arr   = [];
                $tmp                     = new stdClass();
                $tmp->Name               = \QUERY_PARAM_PRODUCT;
                $tmp->Wert               = Request::verifyGPCDataInt(\QUERY_PARAM_PRODUCT);
                $redir->oParameter_arr[] = $tmp;
                $redir->nRedirect        = \R_LOGIN_TAG;
                $redir->cURL             = '?' . \QUERY_PARAM_PRODUCT
                    . '=' . Request::verifyGPCDataInt(\QUERY_PARAM_PRODUCT);
                $redir->cName            = Shop::Lang()->get('tag', 'redirect');
                break;
            case \R_LOGIN_NEWSCOMMENT:
                $redir->oParameter_arr   = [];
                $tmp                     = new stdClass();
                $tmp->Name               = \QUERY_PARAM_LINK;
                $tmp->Wert               = Request::verifyGPCDataInt(\QUERY_PARAM_LINK);
                $redir->oParameter_arr[] = $tmp;
                $tmp                     = new stdClass();
                $tmp->Name               = \QUERY_PARAM_NEWS_ITEM;
                $tmp->Wert               = Request::verifyGPCDataInt(\QUERY_PARAM_NEWS_ITEM);
                $redir->oParameter_arr[] = $tmp;
                $redir->nRedirect        = \R_LOGIN_NEWSCOMMENT;
                $redir->cURL             = '?' . \QUERY_PARAM_LINK
                    . '=' . Request::verifyGPCDataInt(\QUERY_PARAM_LINK)
                    . '&' . \QUERY_PARAM_NEWS_ITEM . '=' . Request::verifyGPCDataInt(\QUERY_PARAM_NEWS_ITEM);
                $redir->cName            = Shop::Lang()->get('news', 'redirect');
                break;
            default:
                break;
        }
        \executeHook(\HOOK_JTL_INC_SWITCH_REDIRECT, ['cRedirect' => &$code, 'oRedirect' => &$redir]);
        $_SESSION['JTL_REDIRECT'] = $redir;

        return $redir;
    }

    /**
     * @throws Exception
     */
    private function logout(): never
    {
        Frontend::getInstance()->logout();
        \header('Location: ' . $this->linkService->getStaticRoute('jtl.php') . '?loggedout=1', true, 303);
        exit;
    }

    /**
     * @throws Exception
     */
    private function changePassword(int $customerID): string
    {
        $step = 'passwort aendern';
        if (empty($_POST['altesPasswort']) || empty($_POST['neuesPasswort1'])) {
            $this->alertService->addNotice(
                Shop::Lang()->get('changepasswordFilloutForm', 'login'),
                'changepasswordFilloutForm'
            );
        }
        if (
            !isset($_POST['neuesPasswort1'], $_POST['neuesPasswort2'])
            || $_POST['neuesPasswort1'] !== $_POST['neuesPasswort2']
        ) {
            $this->alertService->addError(
                Shop::Lang()->get('changepasswordPassesNotEqual', 'login'),
                'changepasswordPassesNotEqual'
            );
        }
        $minLength = $this->settings->int(CustomerOption::PASSWORD_MIN_LENGTH);
        if (\mb_strlen(Request::pString('neuesPasswort1')) < $minLength) {
            $this->alertService->addError(
                Shop::Lang()->get('changepasswordPassTooShort', 'login') . ' '
                . Shop::Lang()->get('minCharLen', 'messages', $minLength),
                'changepasswordPassTooShort'
            );
        }
        if (
            Request::pString('neuesPasswort1') === Request::pString('neuesPasswort2')
            && \mb_strlen(Request::pString('neuesPasswort1')) >= $minLength
        ) {
            $customer = new Customer($customerID);
            $user     = $this->db->select(
                'tkunde',
                'kKunde',
                $customerID,
                null,
                null,
                null,
                null,
                false,
                'cPasswort, cMail'
            );
            if ($user !== null && isset($user->cPasswort, $user->cMail)) {
                $ok = $customer->checkCredentials($user->cMail, Request::pString('altesPasswort'));
                if ($ok !== false) {
                    $customer->updatePassword(Request::pString('neuesPasswort1'));
                    $step = 'mein Konto';
                    $this->alertService->addNotice(
                        Shop::Lang()->get('changepasswordSuccess', 'login'),
                        'changepasswordSuccess'
                    );
                    $base = $this->linkService->getStaticRoute('jtl.php');
                    Frontend::getInstance()->checkCustomerUpdate($base . '?loggedout=1&updated_pw=true');
                } else {
                    $this->alertService->addError(
                        Shop::Lang()->get('changepasswordWrongPass', 'login'),
                        'changepasswordWrongPass'
                    );
                }
            }
        }

        return $step;
    }

    private function viewOrder(int $customerID): string
    {
        $order = new Bestellung(Request::verifyGPCDataInt('bestellung'), true, $this->db);
        if ($order->kKunde === null || (int)$order->kKunde !== $customerID) {
            return 'login';
        }
        if (Request::verifyGPCDataInt('dl') > 0 && Download::checkLicense()) {
            $returnCode = Download::getFile(
                Request::verifyGPCDataInt('dl'),
                $customerID,
                (int)$order->kBestellung
            );
            if ($returnCode !== 1) {
                $this->alertService->addError(Download::mapGetFileErrorCode($returnCode), 'downloadError');
            }
        }
        $step                      = 'bestellung';
        $customer                  = Frontend::getCustomer();
        $customer->angezeigtesLand = LanguageHelper::getCountryCodeByCountryName($customer->cLand ?? '');
        $rmaLink                   = '';
        if (
            $order->kBestellung > 0
            && $this->settings->bool(Globals::RMA_ENABLED)
            && $this->rmaService->isOrderReturnable($order->kBestellung)
        ) {
            $rmaLink = $this->linkService->getStaticRoute('jtl.php') . '?newRMA=' . $order->kBestellung;
        }
        $this->smarty->assign('Bestellung', $order)
            ->assign('billingAddress', $order->oRechnungsadresse)
            ->assign('Lieferadresse', $order->Lieferadresse ?? null)
            ->assign('incommingPayments', $order->getIncommingPayments(true, true))
            ->assign('rmaLink', $rmaLink);
        if (isset($order->oEstimatedDelivery->longestMin, $order->oEstimatedDelivery->longestMax)) {
            $this->smarty->assign(
                'cEstimatedDeliveryEx',
                Date::dateAddWeekday($order->dErstellt, $order->oEstimatedDelivery->longestMin)->format('d.m.Y')
                . ' - ' .
                Date::dateAddWeekday($order->dErstellt, $order->oEstimatedDelivery->longestMax)->format('d.m.Y')
            );
        }

        return $step;
    }

    /**
     * @throws Exception
     * @since 5.3.0
     */
    private function saveRMA(int $customerID, string $rmaCreateDateHash): string
    {
        $result = 'mein Konto';
        if ($customerID !== Frontend::getCustomer()->getID()) {
            return $result;
        }
        $rmaFromSession = Frontend::get('rmaRequest');
        if ($rmaFromSession === null) {
            return $result;
        }
        $rmaService = new RMAService();
        if ($rmaCreateDateHash === $rmaService->hashCreateDate($rmaFromSession)) {
            if ($rmaService->insertRMA($rmaFromSession) > 0) {
                $this->alertService->addSuccess(
                    Shop::Lang()->get('saveRMA', 'rma'),
                    'rmaSuccess'
                );
                unset($_SESSION['rmaRequest']);
            } else {
                $this->alertService->addError(
                    Shop::Lang()->get('errorSavingRMA', 'rma'),
                    'rmaError'
                );
            }
        }

        return $result;
    }

    /**
     * @throws Exception
     * @since 5.3.0
     */
    private function newRMA(int $customerID, int $orderID = 0): string
    {
        $languageID = Shop::getLanguageID();
        $orderNo    = '';
        if ($orderID > 0) {
            $orderNo = $this->rmaService->getOrderNumbers([$orderID])[$orderID] ?? '';
        }
        $returnableProducts = $this->rmaService->getReturnableProducts(
            customerID: $customerID,
            languageID: $languageID,
            cancellationTime: Shopsetting::getInstance()->getValue(\CONF_GLOBAL, 'global_cancellation_time')
        );

        $this->getDeliveryAddresses(['shippingAddresses', 'shippingCountries']);

        $this->smarty->assign('rma', $this->rmaService->newReturn(customerID: $customerID))
            ->assign('returnableProducts', $returnableProducts)
            ->assign('reasons', $this->rmaReasonService->loadReasons($languageID)->reasons)
            ->assign('returnableOrders', $this->rmaService->getOrderArray($returnableProducts))
            ->assign('orderNo', $orderNo)
            ->assign('rmaService', $this->rmaService);

        return 'newRMA';
    }

    /**
     * @throws Exception
     * @since 5.3.0
     */
    private function showRMA(int $rmaID, int $customerID): string
    {
        $rmaService              = new RMAService();
        $rmaReturnAddressService = new RMAReturnAddressService();
        $rmaHistoryService       = new RMAHistoryService();
        $rma                     = $rmaService->getReturn(
            id: $rmaID,
            customerID: $customerID,
            langID: Shop::getLanguageID()
        );

        $this->smarty->assign('rma', $rma)
            ->assign('rmaHistory', $rmaHistoryService->getHistory($rma))
            ->assign('rmaService', $rmaService)
            ->assign('rmaReturnAddressService', $rmaReturnAddressService)
            ->assign('rmaHistoryService', $rmaHistoryService);

        return 'showRMA';
    }

    /**
     * @throws Exception
     * @since 5.3.0
     */
    private function rmaOrders(): string
    {
        $rmaService = (new RMAService())->loadReturns(
            langID: Shop::getLanguageID(),
            filter: ['customerID' => Frontend::getCustomer()->getID()]
        );
        $this->smarty->assign('rmaService', $rmaService);

        return 'rmas';
    }

    private function viewOrders(int $customerID): void
    {
        $downloads = Download::getDownloads(['kKunde' => $customerID], Shop::getLanguageID());
        $this->smarty->assign('oDownload_arr', $downloads);
        if (Request::verifyGPCDataInt('dl') > 0 && Download::checkLicense()) {
            $returnCode = Download::getFile(
                Request::verifyGPCDataInt('dl'),
                $customerID,
                Request::verifyGPCDataInt('kBestellung')
            );
            if ($returnCode !== 1) {
                $this->alertService->addError(Download::mapGetFileErrorCode($returnCode), 'downloadError');
            }
        }
        $orders = $this->db->selectAll(
            'tbestellung',
            'kKunde',
            $customerID,
            '*, date_format(dErstellt,\'%d.%m.%Y\') AS dBestelldatum',
            'kBestellung DESC'
        );
        $orders = $this->sanitizeOrders($orders, $downloads);

        $orderPagination = (new Pagination('orders'))
            ->setItemArray($orders)
            ->setItemsPerPage(10)
            ->assemble();

        $this->smarty->assign('orderPagination', $orderPagination)
            ->assign('Bestellungen', $orders);

        if ($this->settings->bool(Globals::RMA_ENABLED)) {
            $this->smarty->assign('rmaService', (new RMAService()));
        }
    }

    /**
     * @param stdClass[] $orders
     * @param Download[] $downloads
     * @return stdClass[]
     */
    private function sanitizeOrders(array $orders, array $downloads): array
    {
        foreach ($orders as $order) {
            $order->bDownload           = some(
                $downloads,
                static fn(Download $dl): bool => $dl->kBestellung === $order->kBestellung
            );
            $order->kBestellung         = (int)$order->kBestellung;
            $order->kWarenkorb          = (int)$order->kWarenkorb;
            $order->kKunde              = (int)$order->kKunde;
            $order->kLieferadresse      = (int)$order->kLieferadresse;
            $order->kRechnungsadresse   = (int)$order->kRechnungsadresse;
            $order->kZahlungsart        = (int)$order->kZahlungsart;
            $order->kVersandart         = (int)$order->kVersandart;
            $order->kSprache            = (int)$order->kSprache;
            $order->kWaehrung           = (int)$order->kWaehrung;
            $order->cStatus             = (int)$order->cStatus;
            $order->nLongestMinDelivery = (int)$order->nLongestMinDelivery;
            $order->nLongestMaxDelivery = (int)$order->nLongestMaxDelivery;
            $this->setCurrencyForOrder($order);
            $order->cBestellwertLocalized = Preise::getLocalizedPriceString(
                $order->fGesamtsumme,
                $order->Waehrung
            );
            $order->Status                = Texts::orderState($order->cStatus);
        }

        return $orders;
    }

    private function setCurrencyForOrder(stdClass $order): void
    {
        if ($order->kWaehrung <= 0) {
            return;
        }
        if (isset($this->currencies[$order->kWaehrung])) {
            $order->Waehrung = $this->currencies[$order->kWaehrung];
        } else {
            $order->Waehrung = $this->db->select(
                'twaehrung',
                'kWaehrung',
                $order->kWaehrung
            );
            if ($order->Waehrung !== null) {
                $order->Waehrung->kWaehrung          = (int)$order->Waehrung->kWaehrung;
                $this->currencies[$order->kWaehrung] = $order->Waehrung;
            }
        }
        if (isset($order->fWaehrungsFaktor, $order->Waehrung->fFaktor) && $order->fWaehrungsFaktor !== 1) {
            $order->Waehrung->fFaktor = $order->fWaehrungsFaktor;
        }
    }

    private function deleteAccount(Customer $customer): never
    {
        $auth = $customer->checkCredentials($customer->cMail, Request::pString('delete-account-password'));
        if ($auth === false) {
            \header(
                'Location: ' . $this->linkService->getStaticRoute('jtl.php') . '?del=1&accountDeleteError=1',
                true,
                303
            );
            exit;
        }
        $customer->deleteAccount(
            Journal::ISSUER_TYPE_CUSTOMER,
            $customer->getID(),
            false,
            true
        );

        \executeHook(\HOOK_JTL_PAGE_KUNDENACCOUNTLOESCHEN);
        \session_destroy();
        \header(
            'Location: ' . $this->linkService->getStaticRoute('registrieren.php') . '?accountDeleted=1',
            true,
            303
        );
        exit;
    }

    /**
     * @param string[] $smartyAssign
     * @param int      $limit
     * @return Collection<int, DeliveryAddressTemplate>
     */
    private function getDeliveryAddresses(
        array $smartyAssign = ['Lieferadressen', 'LieferLaender'],
        int $limit = 0
    ): Collection {
        $customer   = Frontend::getCustomer();
        $customerID = $customer->getID();
        if ($customerID < 1) {
            return Collection::empty();
        }
        $addresses = DeliveryAddressTemplate::getAll($customerID);
        if ($limit > 0) {
            $addresses = $addresses->slice(0, $limit);
        }
        $this->smarty->assign($smartyAssign[0], $addresses)
            ->assign(
                $smartyAssign[1],
                $this->shippingService->getPossibleShippingCountries([], $customer->getGroupID())
            );

        return $addresses;
    }

    private function loadShippingAddress(Customer $customer): void
    {
        $data = $this->db->selectSingleRow(
            'tlieferadressevorlage',
            'kLieferadresse',
            Request::gInt('editAddress'),
            'kKunde',
            $customer->getID()
        );
        if ($data === null) {
            \header(
                'Location: '
                . Shop::Container()->getLinkService()->getStaticRoute('jtl.php') . '?editLieferadresse=1'
            );
            exit;
        }
        $this->smarty->assign('Lieferadresse', new DeliveryAddressTemplate($this->db, (int)$data->kLieferadresse))
            ->assignDeprecated(
                'laender',
                Shop::Container()->getCountryService()->getCountrylist(),
                '5.5.0',
            );
    }

    private function updateShippingAddress(Customer $customer): never
    {
        $postData                            = Text::filterXSS($_POST);
        $shippingAddress                     = $postData['register']['shipping_address'];
        $template                            = new DeliveryAddressTemplate($this->db);
        $template->kLieferadresse            = (int)$postData['updateAddress'];
        $template->kKunde                    = $customer->kKunde;
        $template->cAnrede                   = $shippingAddress['anrede'] ?? '';
        $template->cTitel                    = $shippingAddress['titel'] ?? '';
        $template->cVorname                  = $shippingAddress['vorname'] ?? '';
        $template->cNachname                 = $shippingAddress['nachname'] ?? '';
        $template->cFirma                    = $shippingAddress['firma'] ?? '';
        $template->cZusatz                   = $shippingAddress['firmazusatz'] ?? '';
        $template->cStrasse                  = $shippingAddress['strasse'] ?? '';
        $template->cHausnummer               = $shippingAddress['hausnummer'] ?? '';
        $template->cAdressZusatz             = $shippingAddress['adresszusatz'] ?? '';
        $template->cLand                     = $shippingAddress['land'] ?? '';
        $template->cBundesland               = $shippingAddress['bundesland'] ?? '';
        $template->cPLZ                      = $shippingAddress['plz'] ?? '';
        $template->cOrt                      = $shippingAddress['ort'] ?? '';
        $template->cMobil                    = $shippingAddress['mobil'] ?? '';
        $template->cFax                      = $shippingAddress['fax'] ?? '';
        $template->cTel                      = $shippingAddress['tel'] ?? '';
        $template->cMail                     = $shippingAddress['email'] ?? '';
        $template->nIstStandardLieferadresse = (int)($shippingAddress['isDefault'] ?? 0);

        if ($template->update()) {
            $this->alertService->addSuccess(
                Shop::Lang()->get('updateAddressSuccessful', 'account data'),
                'updateAddressSuccessful'
            );
        }

        if (isset($postData['backToCheckout'])) {
            if ($template->kLieferadresse === 0) {
                unset($_SESSION['shippingAddressPresetID']);
            } else {
                $_SESSION['shippingAddressPresetID'] = $template->kLieferadresse;
            }
            \header(
                'Location: '
                . Shop::Container()->getLinkService()->getStaticRoute('bestellvorgang.php')
                . '?editRechnungsadresse=1'
            );
            exit;
        }
        \header(
            'Location: '
            . Shop::Container()->getLinkService()->getStaticRoute('jtl.php')
            . '?editLieferadresse=1&editAddress=' . (int)$postData['updateAddress']
        );
        exit;
    }

    private function saveShippingAddress(Customer $customer): never
    {
        $postData   = Text::filterXSS($_POST);
        $saveStatus = false;
        if (isset($postData['register']['shipping_address'])) {
            $addressData                         = $postData['register']['shipping_address'];
            $template                            = new DeliveryAddressTemplate($this->db);
            $template->kKunde                    = $customer->kKunde;
            $template->cAnrede                   = $addressData['anrede'] ?? '';
            $template->cTitel                    = $addressData['titel'] ?? '';
            $template->cVorname                  = $addressData['vorname'] ?? '';
            $template->cNachname                 = $addressData['nachname'] ?? '';
            $template->cFirma                    = $addressData['firma'] ?? '';
            $template->cZusatz                   = $addressData['firmazusatz'] ?? '';
            $template->cStrasse                  = $addressData['strasse'] ?? '';
            $template->cHausnummer               = $addressData['hausnummer'] ?? '';
            $template->cAdressZusatz             = $addressData['adresszusatz'] ?? '';
            $template->cLand                     = $addressData['land'] ?? '';
            $template->cBundesland               = $addressData['bundesland'] ?? '';
            $template->cPLZ                      = $addressData['plz'] ?? '';
            $template->cOrt                      = $addressData['ort'] ?? '';
            $template->cMobil                    = $addressData['mobil'] ?? '';
            $template->cFax                      = $addressData['fax'] ?? '';
            $template->cTel                      = $addressData['tel'] ?? '';
            $template->cMail                     = $addressData['email'] ?? '';
            $template->nIstStandardLieferadresse = (int)($addressData['isDefault'] ?? 0);
            $saveStatus                          = $template->persist();
        }

        if ($saveStatus) {
            $this->alertService->addSuccess(
                Shop::Lang()->get('saveAddressSuccessful', 'account data'),
                'saveAddressSuccessful'
            );
        }
        \header('Location: ' . Shop::Container()->getLinkService()->getStaticRoute('jtl.php') . '?editLieferadresse=1');
        exit;
    }

    private function deleteShippingAddress(Customer $customer): never
    {
        $template                 = new DeliveryAddressTemplate($this->db);
        $template->kLieferadresse = Request::gInt('deleteAddress');
        $template->kKunde         = $customer->getID();
        if ($template->delete()) {
            $this->alertService->addNotice(
                Shop::Lang()->get('deleteAddressSuccessful', 'account data'),
                'deleteAddressSuccessful'
            );
        }
        \header('Location: ' . Shop::Container()->getLinkService()->getStaticRoute('jtl.php') . '?editLieferadresse=1');
        exit;
    }

    private function setShippingAddressAsDefault(Customer $customer): never
    {
        $resetAllDefault                            = new stdClass();
        $resetAllDefault->nIstStandardLieferadresse = 0;
        $this->db->update('tlieferadressevorlage', 'kKunde', $customer->getID(), $resetAllDefault);

        $resetAllDefault                            = new stdClass();
        $resetAllDefault->nIstStandardLieferadresse = 1;
        $this->db->update(
            'tlieferadressevorlage',
            ['kLieferadresse', 'kKunde'],
            [Request::gInt('setAddressAsDefault'), $customer->getID()],
            $resetAllDefault
        );

        \header('Location: ' . Shop::Container()->getLinkService()->getStaticRoute('jtl.php') . '?editLieferadresse=1');
        exit;
    }

    private function getCustomerFields(Customer $customer): void
    {
        if (Request::pInt('edit') === 1) {
            $form               = new CustomerForm();
            $customerAttributes = $form->getCustomerAttributes($_POST);
        } else {
            $customerAttributes = $customer->getCustomerAttributes();
        }

        $this->smarty->assign('customerAttributes', $customerAttributes)
            ->assignDeprecated(
                'laender',
                Shop::Container()->getCountryService()->getCountrylist(),
                '5.5.0',
            )
            ->assign('oKundenfeld_arr', new CustomerFields(Shop::getLanguageID()));
    }

    private function modifyWishlist(int $customerID, int $wishlistID): string
    {
        $step     = 'mein Konto';
        $wishlist = new Wishlist($wishlistID, $this->db);
        if ($wishlist->getCustomerID() !== $customerID) {
            return $step;
        }
        if (isset($_REQUEST['wlAction']) && Form::validateToken()) {
            $action = Request::verifyGPDataString('wlAction');
            if ($action === 'setPrivate') {
                $wishlist->setVisibility(false);
                $this->alertService->addNotice(
                    Shop::Lang()->get('wishlistSetPrivate', 'messages'),
                    'wishlistSetPrivate'
                );
            } elseif ($action === 'setPublic') {
                $wishlist->setVisibility(true);
                $this->alertService->addNotice(
                    Shop::Lang()->get('wishlistSetPublic', 'messages'),
                    'wishlistSetPublic'
                );
            }
        }

        return $step;
    }

    /**
     * @throws Exception
     */
    private function changeCustomerData(Customer $customer): Customer
    {
        $postData = Text::filterXSS($_POST);
        $this->smarty->assign('cPost_arr', $postData);
        $form               = new CustomerForm();
        $missingData        = $form->checkKundenFormularArray($postData, true, false);
        $customerGroupID    = Frontend::getCustomerGroup()->getID();
        $checkBox           = new CheckBox(0, $this->db);
        $missingData        = \array_merge(
            $missingData,
            $checkBox->validateCheckBox(\CHECKBOX_ORT_KUNDENDATENEDITIEREN, $customerGroupID, $postData, true)
        );
        $customerData       = $form->getCustomerData($postData, false, false);
        $customerAttributes = $form->getCustomerAttributes($postData);
        $returnCode         = Form::hasNoMissingData($missingData);

        \executeHook(\HOOK_JTL_PAGE_KUNDENDATEN_PLAUSI, [
            'returnCode' => &$returnCode
        ]);

        if ($returnCode) {
            $customerData->cAbgeholt = 'N';
            $customerData->updateInDB();
            $checkBox->triggerSpecialFunction(
                \CHECKBOX_ORT_KUNDENDATENEDITIEREN,
                $customerGroupID,
                true,
                $postData,
                ['oKunde' => $customerData]
            )->checkLogging(\CHECKBOX_ORT_KUNDENDATENEDITIEREN, $customerGroupID, $postData, true);
            DataHistory::saveHistory($customer, $customerData, DataHistory::QUELLE_MEINKONTO);
            $customerAttributes->save();
            $customerData->getCustomerAttributes()->load($customerData->getID());
            $this->alertService->addNotice(Shop::Lang()->get('dataEditSuccessful', 'login'), 'dataEditSuccessful');
            Tax::setTaxRates();
            if (
                isset($_SESSION['Warenkorb']->kWarenkorb)
                && Frontend::getCart()->gibAnzahlArtikelExt([\C_WARENKORBPOS_TYP_ARTIKEL]) > 0
            ) {
                Frontend::getCart()->gibGesamtsummeWarenLocalized();
            }
            $customer = $customerData;
            Frontend::getInstance()->setCustomer($customer)->checkCustomerUpdate();
        } else {
            $this->smarty->assign('fehlendeAngaben', $missingData);
        }

        return $customer;
    }

    public function doTwoFa(string $code): Customer
    {
        if (!isset($_SESSION['oldPost']['email'], $_SESSION['oldPost']['passwort'])) {
            return new Customer();
        }

        return $this->login($_SESSION['oldPost']['email'], $_SESSION['oldPost']['passwort'], $code);
    }

    private function saveTwoFA(int $customerID): void
    {
        $customer = Frontend::getCustomer();
        if ($customer->getID() !== $customerID) {
            return;
        }
        $customer->set2FASecret(Request::pString('c2FAsecret'));
        $customer->set2FAauth(Request::pInt('b2FAauth'));
        $customer->updateInDB();
        $base = $this->linkService->getStaticRoute('jtl.php');
        Frontend::getInstance()->checkCustomerUpdate($base . '?loggedout=1&reason=2fa');
    }

    private function assignTwoFAData(int $customerID): void
    {
        if (!$this->settings->bool(CustomerOption::ENABLE_2FA)) {
            return;
        }
        $qrCode      = '';
        $knownSecret = '';
        if ($customerID > 0) {
            $twoFA = new FrontendTwoFA($this->db, FrontendUserData::getByID($customerID, $this->db));
            if ($twoFA->is2FAauthSecretExist() === true) {
                $qrCode      = $twoFA->getQRcode();
                $knownSecret = $twoFA->getSecret();
            }
        }
        $this->smarty->assign('twoFAEnabled', true)
            ->assign('QRcodeString', $qrCode)
            ->assign('cKnownSecret', $knownSecret);
    }

    private function handleRedirects(): void
    {
        /** @var stdClass|null $sessionRedirect */
        $sessionRedirect = $_SESSION['JTL_REDIRECT'] ?? null;
        if ($sessionRedirect !== null || Request::verifyGPCDataInt('r') > 0) {
            $this->smarty->assign(
                'oRedirect',
                $sessionRedirect ?? $this->getRedirect(Request::verifyGPCDataInt('r'))
            );
            \executeHook(\HOOK_JTL_PAGE_REDIRECT_DATEN);
        }
        unset($_SESSION['JTL_REDIRECT']);
    }

    private function handleMessages(string $step, int $customerID): void
    {
        if (Request::verifyGPCDataInt('wlidmsg') > 0) {
            $this->alertService->addNotice(Wishlist::mapMessage(Request::verifyGPCDataInt('wlidmsg')), 'wlidmsg');
        }
        if (Request::getVar('updated_pw') === 'true') {
            $this->alertService->addNotice(
                Shop::Lang()->get('changepasswordSuccess', 'login'),
                'changepasswordSuccess'
            );
        }
        if (Request::gInt('accountDeleteError') === 1) {
            $this->alertService->addWarning(
                Shop::Lang()->get('incorrectLogin', 'global'),
                'incorrectPasswordDeleteAccount'
            );
        }
        if (Request::gInt('loggedout') > 0) {
            if (Request::getVar('reason') === 'upw' || Request::getVar('updated_pw') === 'true') {
                $this->alertService->addNotice(Shop::Lang()->get('loggedOutDueToPasswordChange', 'login'), 'loggedOut');
            } elseif (Request::getVar('reason') === '2fa') {
                $this->alertService->addNotice(Shop::Lang()->get('loggedOutDueTo2FAChange', 'login'), 'loggedOut');
            } else {
                $this->alertService->addNotice(Shop::Lang()->get('loggedOut'), 'loggedOut');
            }
        }
        $alertNote = $this->alertService->alertTypeExists(Alert::TYPE_NOTE);
        if (!$alertNote && $step === 'mein Konto' && $customerID > 0) {
            $this->alertService->addInfo(
                Shop::Lang()->get('myAccountDesc', 'login'),
                'myAccountDesc',
                ['showInAlertListTemplate' => false]
            );
        }
        $this->smarty->assign('alertNote', $alertNote);
    }

    private function setCurrentLink(): void
    {
        try {
            $link = $this->linkService->getSpecialPage(\LINKTYP_LOGIN);
        } catch (SpecialPageNotFoundException $e) {
            Shop::Container()->getLogService()->error($e->getMessage());
            $link = null;
        }
        $this->smarty->assign('Link', $link);
    }

    private function handleLoginRequest(): Customer
    {
        $customer   = Frontend::getCustomer();
        $customerID = $customer->getID();
        if ($customerID > 0) {
            Frontend::getInstance()->setCustomer($customer);
        }
        $twoFACode = Request::pString('TwoFA_code');
        if ($twoFACode !== '' && Request::postInt('twofa') === 1) {
            $redirect = $this->smarty->getTemplateVars('oRedirect');
            if ($redirect !== null) {
                $_SESSION['JTL_REDIRECT'] = $redirect;
            }
            $customer = $this->doTwoFa($twoFACode);
        }
        $email = Request::pString('email');
        $pass  = Request::pString('passwort');
        if ($email !== '' && $pass !== '' && Request::pInt('login') === 1) {
            $customer = $this->login($email, $pass);
        }

        return $customer;
    }

    private function checkOpenOrders(): void
    {
        $openOrders = Frontend::getCustomer()->getOpenOrders();
        if (empty($openOrders)) {
            return;
        }
        if ($openOrders->ordersInCancellationTime > 0) {
            $ordersInCancellationTime = \sprintf(
                Shop::Lang()->get('customerOrdersInCancellationTime', 'account data'),
                $openOrders->ordersInCancellationTime
            );
        }
        $this->alertService->addDanger(
            \sprintf(
                Shop::Lang()->get('customerOpenOrders', 'account data'),
                $openOrders->openOrders,
                $ordersInCancellationTime ?? ''
            ),
            'customerOrdersInCancellationTime'
        );
    }

    private function checkPersistentCartActions(int $customerID): void
    {
        if (Request::verifyGPCDataInt('basket2Pers') === 1) {
            $this->setzeWarenkorbPersInWarenkorb($customerID);
            \header('Location: ' . $this->linkService->getStaticRoute('jtl.php'), true, 303);
            exit;
        }
        if (Request::verifyGPCDataInt('updatePersCart') === 1) {
            $pers = PersistentCart::getInstance($customerID, false, $this->db);
            $pers->entferneAlles();
            $pers->bauePersVonSession();
            \header('Location: ' . $this->linkService->getStaticRoute('jtl.php'), true, 303);
            exit;
        }
    }

    private function getRmaOverview(int $customerID): void
    {
        if ($this->settings->bool(Globals::RMA_ENABLED)) {
            $rmaService = new RMAService();
            $this->smarty->assign('RMAService', $rmaService)
                ->assign(
                    'rmas',
                    $rmaService->loadReturns(
                        Shop::getLanguageID(),
                        ['customerID' => $customerID],
                        3
                    )->rmas
                )
                ->assign('rmaService', $rmaService);
        }
        $deliveryAddresses = $this->getDeliveryAddresses(limit: 3);
        \executeHook(\HOOK_JTL_PAGE_MEINKKONTO, ['deliveryAddresses' => &$deliveryAddresses]);
        $this->smarty->assign('compareList', new ComparisonList());
    }

    private function getFile(int $uploadID, int $customerID): void
    {
        $file = new File($uploadID);
        if ($file->cName !== null && $file->validateOwner($customerID)) {
            File::send_file_to_browser(
                \PFAD_UPLOADS . $file->cPfad,
                'application/octet-stream',
                $file->cName
            );
        }
    }

    private function getRatings(int $customerID, string $step): void
    {
        if ($step !== 'bewertungen') {
            $this->smarty->assign('bewertungen', []);

            return;
        }
        $currency = Frontend::getCurrency();
        $ratings  = $this->db->getCollection(
            'SELECT tbewertung.kBewertung, fGuthabenBonus, nAktiv, kArtikel, cTitel, cText, 
                  tbewertung.dDatum, nSterne, cAntwort, dAntwortDatum
                  FROM tbewertung 
                  LEFT JOIN tbewertungguthabenbonus 
                      ON tbewertung.kBewertung = tbewertungguthabenbonus.kBewertung
                  WHERE tbewertung.kKunde = :customer',
            ['customer' => $customerID]
        )->each(static function (stdClass $item) use ($currency): void {
            $item->fGuthabenBonusLocalized = Preise::getLocalizedPriceString($item->fGuthabenBonus, $currency);
        });

        $this->smarty->assign('bewertungen', $ratings);
    }
}
