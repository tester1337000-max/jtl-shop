<?php

declare(strict_types=1);

namespace JTL\Customer\Registration;

use JTL\Cart\CartHelper;
use JTL\CheckBox;
use JTL\Checkout\DeliveryAddressTemplate;
use JTL\Checkout\Lieferadresse;
use JTL\Checkout\Versandart;
use JTL\Customer\Customer;
use JTL\Customer\CustomerAttribute;
use JTL\Customer\CustomerAttributes;
use JTL\Customer\Registration\Validator\RegistrationForm;
use JTL\Customer\Registration\Validator\ShippingAddress;
use JTL\Helpers\Date;
use JTL\Helpers\Order;
use JTL\Helpers\Tax;
use JTL\Helpers\Text;
use JTL\Language\LanguageHelper;
use JTL\Session\Frontend;
use JTL\Shop;
use JTL\Staat;
use stdClass;

/**
 * Class Form
 * @package JTL\Customer\Registration
 */
class Form
{
    /**
     * @param string[] $post
     * @param bool     $customerAccount
     * @param bool     $htmlentities
     * @return Customer
     * @former getKundendaten()
     * @since 5.2.0
     */
    public function getCustomerData(array $post, bool $customerAccount, bool $htmlentities = true): Customer
    {
        $mapping = [
            'anrede'         => 'cAnrede',
            'vorname'        => 'cVorname',
            'nachname'       => 'cNachname',
            'strasse'        => 'cStrasse',
            'hausnummer'     => 'cHausnummer',
            'plz'            => 'cPLZ',
            'ort'            => 'cOrt',
            'land'           => 'cLand',
            'email'          => 'cMail',
            'tel'            => 'cTel',
            'fax'            => 'cFax',
            'firma'          => 'cFirma',
            'firmazusatz'    => 'cZusatz',
            'bundesland'     => 'cBundesland',
            'titel'          => 'cTitel',
            'adresszusatz'   => 'cAdressZusatz',
            'mobil'          => 'cMobil',
            'www'            => 'cWWW',
            'ustid'          => 'cUSTID',
            'geburtstag'     => 'dGeburtstag',
            'kundenherkunft' => 'cHerkunft'
        ];

        if ($customerAccount !== false) {
            $mapping['pass'] = 'cPasswort';
        }
        $customerID = Frontend::getCustomer()->getID();
        $customer   = new Customer($customerID);
        foreach ($mapping as $external => $internal) {
            if (!isset($post[$external])) {
                continue;
            }
            $val = $external === 'pass' ? $post[$external] : Text::filterXSS($post[$external]);
            if ($htmlentities === true) {
                $val = Text::htmlentities($val);
            }
            $customer->$internal = $val;
        }

        $customer->cMail                 = \mb_convert_case($customer->cMail, \MB_CASE_LOWER);
        $customer->dGeburtstag           = Date::convertDateToMysqlStandard($customer->dGeburtstag ?? '');
        $customer->dGeburtstag_formatted = $customer->dGeburtstag === '_DBNULL_'
            ? ''
            : Date::safeDateFormat($customer->dGeburtstag, 'd.m.Y', '', 'Y-m-d');
        $customer->angezeigtesLand       = LanguageHelper::getCountryCodeByCountryName($customer->cLand ?? '');
        if (!empty($customer->cBundesland)) {
            $region = Staat::getRegionByIso($customer->cBundesland, $customer->cLand ?? '');
            if ($region !== null && $region->cName !== null) {
                $customer->cBundesland = $region->cName;
            }
        }

        return $customer;
    }

    /**
     * @param string[] $post
     * @return CustomerAttributes
     * @former getKundenattribute()
     * @since 5.2.0
     */
    public function getCustomerAttributes(array $post): CustomerAttributes
    {
        $customerAttributes = new CustomerAttributes(Frontend::getCustomer()->getID());
        /** @var CustomerAttribute $customerAttribute */
        foreach ($customerAttributes as $customerAttribute) {
            if ($customerAttribute->isEditable()) {
                $idx = 'custom_' . $customerAttribute->getCustomerFieldID();
                $customerAttribute->setValue(isset($post[$idx]) ? Text::filterXSS($post[$idx]) : null);
            }
        }

        return $customerAttributes;
    }

    /**
     * @return array<string, int>
     * @former checkKundenFormular()
     * @since 5.2.0
     */
    public function checkKundenFormular(bool $customerAccount, bool $checkpass = true): array
    {
        return $this->checkKundenFormularArray(Text::filterXSS($_POST), $customerAccount, $checkpass);
    }

    /**
     * @param array<mixed> $data
     * @param bool         $customerAccount
     * @param bool         $checkpass
     * @return array<string, int>
     * @former checkKundenFormularArray()
     * @since 5.2.0
     */
    public function checkKundenFormularArray(array $data, bool $customerAccount, bool $checkpass = true): array
    {
        $validator = new RegistrationForm($data, Shop::getSettings([\CONF_KUNDEN, \CONF_KUNDENFELD, \CONF_GLOBAL]));
        $validator->validate();
        if ($customerAccount === true) {
            $validator->validateCustomerAccount($checkpass);
        }

        return $validator->getErrors();
    }

    /**
     * Gibt mögliche fehlende Felder aus Formulareingaben zurück.
     *
     * @param array<mixed>  $post
     * @param int|null      $customerGroupId
     * @param CheckBox|null $checkBox
     * @return array<string, int>
     */
    public function getMissingInput(array $post, ?int $customerGroupId = null, ?CheckBox $checkBox = null): array
    {
        $missingInput    = $this->checkKundenFormular(false);
        $customerGroupId = $customerGroupId ?? Frontend::getCustomerGroup()->getID();
        $checkBox        = $checkBox ?? new CheckBox();

        return \array_merge(
            $missingInput,
            $checkBox->validateCheckBox(
                \CHECKBOX_ORT_REGISTRIERUNG,
                $customerGroupId,
                $post,
                true
            )
        );
    }

    /**
     * @param array<mixed>      $post
     * @param array<mixed>|null $missingData
     * @former pruefeLieferdaten()
     */
    public function pruefeLieferdaten(array $post, ?array &$missingData = null): void
    {
        unset(
            $_SESSION['Lieferadresse'],
            $_SESSION['newShippingAddressPreset'],
            $_SESSION['setAsDefaultShippingAddressPreset']
        );
        if (!isset($_SESSION['Bestellung'])) {
            $_SESSION['Bestellung'] = new stdClass();
        }
        if (isset($post['saveAsNewShippingAddressPreset'])) {
            $_SESSION['newShippingAddressPreset'] = 1;
        }
        if (isset($post['isDefault'])) {
            $_SESSION['setAsDefaultShippingAddressPreset'] = 1;
        }
        $_SESSION['Bestellung']->kLieferadresse = (int)($post['kLieferadresse'] ?? -1);
        if ((int)($post['kLieferadresse'] ?? -1) > 0) {
            $_SESSION['shippingAddressPresetID'] = (int)$post['kLieferadresse'];
        } else {
            unset($_SESSION['shippingAddressPresetID']);
        }
        Frontend::getCart()->loescheSpezialPos(\C_WARENKORBPOS_TYP_VERSANDPOS);
        unset($_SESSION['Versandart']);
        // neue lieferadresse
        if (!isset($post['kLieferadresse']) || (int)$post['kLieferadresse'] === -1) {
            $missingData               = \array_merge($missingData ?? [], $this->checkLieferFormularArray($post));
            $deliveryAddress           = Lieferadresse::createFromPost($post);
            $ok                        = \JTL\Helpers\Form::hasNoMissingData($missingData);
            $_SESSION['Lieferadresse'] = $deliveryAddress;

            $_SESSION['preferredDeliveryCountryCode'] = $deliveryAddress->cLand;
            Tax::setTaxRates();
            \executeHook(\HOOK_BESTELLVORGANG_PAGE_STEPLIEFERADRESSE_NEUELIEFERADRESSE_PLAUSI, [
                'nReturnValue'    => &$ok,
                'fehlendeAngaben' => &$missingData
            ]);
            if ($ok) {
                // Anrede mappen
                if ($deliveryAddress->cAnrede === 'm') {
                    $deliveryAddress->cAnredeLocalized = Shop::Lang()->get('salutationM');
                } elseif ($deliveryAddress->cAnrede === 'w') {
                    $deliveryAddress->cAnredeLocalized = Shop::Lang()->get('salutationW');
                }
                \executeHook(\HOOK_BESTELLVORGANG_PAGE_STEPLIEFERADRESSE_NEUELIEFERADRESSE);
                CartHelper::applyShippingFreeCoupon();
            }
        } elseif ((int)$post['kLieferadresse'] > 0) {
            // vorhandene lieferadresse
            $addressID = Shop::Container()->getDB()->getSingleInt(
                'SELECT kLieferadresse
                    FROM tlieferadressevorlage
                    WHERE kKunde = :cid
                        AND kLieferadresse = :daid',
                'kLieferadresse',
                ['cid' => Frontend::getCustomer()->getID(), 'daid' => (int)$post['kLieferadresse']]
            );
            if ($addressID > 0) {
                $template                  = new DeliveryAddressTemplate(
                    Shop::Container()->getDB(),
                    $addressID
                );
                $_SESSION['Lieferadresse'] = $template->getDeliveryAddress();

                $_SESSION['preferredDeliveryCountryCode'] = $_SESSION['Lieferadresse']->cLand;
                if (isset($_SESSION['Bestellung']->kLieferadresse)) {
                    $_SESSION['Bestellung']->kLieferadresse = -1;
                }
                \executeHook(\HOOK_BESTELLVORGANG_PAGE_STEPLIEFERADRESSE_VORHANDENELIEFERADRESSE);
            }
        } elseif ((int)$post['kLieferadresse'] === 0 && isset($_SESSION['Kunde'])) {
            // lieferadresse gleich rechnungsadresse
            Lieferadresse::createFromShippingAddress($post);
            \executeHook(\HOOK_BESTELLVORGANG_PAGE_STEPLIEFERADRESSE_RECHNUNGLIEFERADRESSE);
        }
        Tax::setTaxRates();
        // lieferland hat sich geändert und versandart schon gewählt?
        if (!empty($_SESSION['Lieferadresse']) && !empty($_SESSION['Versandart'])) {
            $delShip = \mb_stripos($_SESSION['Versandart']->cLaender, $_SESSION['Lieferadresse']->cLand) === false;
            // ist die plz im zuschlagsbereich?
            if (
                (new Versandart((int)$_SESSION['Versandart']->kVersandart))->getShippingSurchargeForZip(
                    $_SESSION['Lieferadresse']->cPLZ,
                    $_SESSION['Lieferadresse']->cLand
                ) !== null
            ) {
                $delShip = true;
            }
            if ($delShip) {
                Frontend::getCart()->loescheSpezialPos(\C_WARENKORBPOS_TYP_VERSANDPOS)
                    ->loescheSpezialPos(\C_WARENKORBPOS_TYP_VERSANDZUSCHLAG)
                    ->loescheSpezialPos(\C_WARENKORBPOS_TYP_NACHNAHMEGEBUEHR)
                    ->loescheSpezialPos(\C_WARENKORBPOS_TYP_ZAHLUNGSART)
                    ->loescheSpezialPos(\C_WARENKORBPOS_TYP_ZINSAUFSCHLAG)
                    ->loescheSpezialPos(\C_WARENKORBPOS_TYP_BEARBEITUNGSGEBUEHR);
                unset($_SESSION['Versandart'], $_SESSION['Zahlungsart']);
            } else {
                Frontend::getCart()->loescheSpezialPos(\C_WARENKORBPOS_TYP_VERSANDZUSCHLAG);
            }
        }
        Order::checkBalance($post);
    }

    /**
     * @param array<mixed> $data
     * @return array<mixed>
     * @former checkLieferFormularArray()
     * @since 5.2.0
     */
    public function checkLieferFormularArray(array $data): array
    {
        $validator = new ShippingAddress($data, Shop::getSettingSection(\CONF_KUNDEN));

        return $validator->validate() === false ? ['shippingAddress' => $validator->getErrors()] : [];
    }
}
