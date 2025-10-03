<?php

declare(strict_types=1);

namespace JTL\Customer\Registration\Validator;

use DateTime;
use JTL\Customer\CustomerField;
use JTL\Customer\CustomerFields;
use JTL\Helpers\Form;
use JTL\Helpers\Text;
use JTL\Session\Frontend;
use JTL\Shop;
use JTL\SimpleMail;
use JTL\VerificationVAT\VATCheck;
use JTL\VerificationVAT\VATCheckInterface;

/**
 * Class RegistrationForm
 * @package JTL\Customer\Registration\Validator
 */
class RegistrationForm extends AbstractValidator
{
    /**
     * @inheritdoc
     * @former checkKundenFormularArray()
     */
    public function validate(): bool
    {
        $this->validateBaseData();
        $this->validateOptionalData();
        $this->validateURLData();
        $this->validateEmail();
        $this->validateAddress();
        $this->validatePhoneData();
        $this->validateTaxData();
        $this->validateBirthday();
        $this->validateCustomerFields();
        $this->validateTime();
        $this->validateCaptcha();
        \executeHook(\HOOK_VALIDATE_REGISTRATION_FORM, [
            'validator' => $this
        ]);

        return \count($this->errors) === 0;
    }

    private function validateBaseData(): void
    {
        foreach (['nachname', 'strasse', 'hausnummer', 'plz', 'ort', 'land', 'email'] as $dataKey) {
            $this->data[$dataKey] = isset($this->data[$dataKey]) ? \trim($this->data[$dataKey]) : null;
            if (!isset($this->data[$dataKey]) || !$this->data[$dataKey]) {
                $this->errors[$dataKey] = 1;
            }
        }
        if (
            $this->config['kunden']['kundenregistrierung_pruefen_name'] === 'Y'
            && \preg_match('#\d+#', $this->data['nachname'])
        ) {
            $this->errors['nachname'] = 2;
        }
    }

    private function validateOptionalData(): void
    {
        $conf = [
            'kundenregistrierung_abfragen_anrede'       => 'anrede',
            'kundenregistrierung_pflicht_vorname'       => 'vorname',
            'kundenregistrierung_abfragen_firma'        => 'firma',
            'kundenregistrierung_abfragen_firmazusatz'  => 'firmazusatz',
            'kundenregistrierung_abfragen_titel'        => 'titel',
            'kundenregistrierung_abfragen_adresszusatz' => 'adresszusatz',
            'kundenregistrierung_abfragen_www'          => 'www',
            'kundenregistrierung_abfragen_bundesland'   => 'bundesland',
            'kundenregistrierung_abfragen_geburtstag'   => 'geburtstag',
            'kundenregistrierung_abfragen_fax'          => 'fax',
            'kundenregistrierung_abfragen_tel'          => 'tel',
            'kundenregistrierung_abfragen_mobil'        => 'mobil'
        ];
        foreach ($conf as $confKey => $dataKey) {
            if ($this->config['kunden'][$confKey] !== 'Y') {
                continue;
            }
            $this->data[$dataKey] = isset($this->data[$dataKey]) ? \trim($this->data[$dataKey]) : null;
            if (!isset($this->data[$dataKey]) || !$this->data[$dataKey]) {
                $this->errors[$dataKey] = 1;
            }
        }
    }

    private function validateURLData(): void
    {
        if (!empty($this->data['www']) && !Text::filterURL($this->data['www'], true, true)) {
            $this->errors['www'] = 2;
        }
    }

    private function validateEmail(): void
    {
        if (Text::filterEmailAddress($this->data['email']) === false) {
            $this->errors['email'] = 2;
        } elseif (SimpleMail::checkBlacklist($this->data['email'])) {
            $this->errors['email'] = 3;
        } elseif (
            isset($this->config['kunden']['kundenregistrierung_pruefen_email'])
            && $this->config['kunden']['kundenregistrierung_pruefen_email'] === 'Y'
            && !\checkdnsrr(\mb_substr($this->data['email'], \mb_strpos($this->data['email'], '@') + 1))
        ) {
            $this->errors['email'] = 4;
        }
    }

    private function validateAddress(): void
    {
        if (
            empty($_SESSION['check_plzort'])
            && empty($_SESSION['check_liefer_plzort'])
            && $this->config['kunden']['kundenregistrierung_abgleichen_plz'] === 'Y'
        ) {
            if (!self::isValidAddress($this->data['plz'], $this->data['ort'], $this->data['land'])) {
                $this->errors['plz']      = 2;
                $this->errors['ort']      = 2;
                $_SESSION['check_plzort'] = 1;
            }
        } else {
            unset($_SESSION['check_plzort']);
        }
    }

    private function validatePhoneData(): void
    {
        $conf = [
            'kundenregistrierung_abfragen_tel'   => 'tel',
            'kundenregistrierung_abfragen_mobil' => 'mobil',
            'kundenregistrierung_abfragen_fax'   => 'fax'
        ];
        foreach ($conf as $confKey => $dataKey) {
            if (!isset($this->data[$dataKey])) {
                continue;
            }
            $errCode = Text::checkPhoneNumber($this->data[$dataKey], $this->config['kunden'][$confKey] === 'Y');
            if ($errCode > 0) {
                $this->errors[$dataKey] = $errCode;
            }
        }
    }

    private function validateTaxData(): void
    {
        $deliveryCountry = ($this->config['kunden']['kundenregistrierung_abfragen_ustid'] !== 'N')
            ? Shop::Container()->getCountryService()->getCountry($this->data['land'])
            : null;

        if (
            $deliveryCountry !== null
            && !$deliveryCountry->isEU()
            && $this->config['kunden']['kundenregistrierung_abfragen_ustid'] !== 'N'
        ) {
            return;
        }
        if (empty($this->data['ustid']) && $this->config['kunden']['kundenregistrierung_abfragen_ustid'] === 'Y') {
            $this->errors['ustid'] = 1;
        } else {
            $this->validateUstIdNr();
        }
    }

    private function validateUstIdNr(): void
    {
        if ($this->config['kunden']['kundenregistrierung_abfragen_ustid'] === 'N') {
            return;
        }
        if (!isset($this->data['ustid']) || $this->data['ustid'] === '') {
            return;
        }
        if (Frontend::getCustomer()->cUSTID === $this->data['ustid']) {
            return;
        }
        $resultVatCheck = null;
        if ($this->config['kunden']['shop_ustid_bzstpruefung'] === 'Y') {
            $vatCheck       = new VATCheck(\trim($this->data['ustid']));
            $resultVatCheck = $vatCheck->doCheckID();
            if ($resultVatCheck['success'] === true) {
                $this->errors['ustid'] = 0;

                return;
            }
        }
        $this->handleVatCheckError($resultVatCheck);
    }

    /**
     * @param array{success: bool, errortype: string, errorcode: int, errorinfo: string}|null $resultVatCheck
     */
    private function handleVatCheckError(?array $resultVatCheck): void
    {
        /** @var int $errorCode */
        $errorCode = $resultVatCheck['errorcode'] ?? 0;
        switch ($resultVatCheck['errortype'] ?? '') {
            case 'vies':
                // vies-error: the ID is invalid according to the VIES-system
                $this->errors['ustid'] = $errorCode; // (old value 5)
                break;
            case 'parse':
                // parse-error: the ID-string is misspelled in any way
                if ($errorCode === 1) {
                    $this->errors['ustid'] = 1; // parse-error: no id was given
                } elseif ($errorCode > 1) {
                    $this->errors['ustid']     = 2; // parse-error: with the position of error in given ID-string
                    $this->errors['ustid_err'] = match ($errorCode) {
                        VATCheckInterface::ERR_PATTERN_MISMATCH,
                        VATCheckInterface::ERR_COUNTRY_NOT_FOUND => $errorCode
                            . ',' . $resultVatCheck['errorinfo'],
                        default                                  => $errorCode,
                    };
                }
                break;
            case 'time':
                // according to the backend-setting:
                // "Einstellungen -> (Formular)einstellungen -> UstID-Nummer"-check active
                if ($this->config['kunden']['shop_ustid_force_remote_check'] === 'Y') {
                    // parsing ok, but the remote-service is in a down slot and unreachable
                    $this->errors['ustid']     = 4;
                    $this->errors['ustid_err'] = $errorCode . ',' . $resultVatCheck['errorinfo'];
                }
                break;
            case 'maxrequests':
                if ($this->config['kunden']['shop_ustid_force_remote_check'] === 'Y') {
                    $this->errors['ustid']     = 6;
                    $this->errors['ustid_err'] = $errorCode . ',' . $resultVatCheck['errorinfo'];
                }
                break;
            case 'core':
                // if we have problems like "no module php_soap" we create a log entry
                // (use case: the module and the vat-check was formerly activated yet
                // but the php-module is disabled now)
                Shop::Container()->getLogService()->warning($resultVatCheck['errorinfo']);
                break;
            default:
                break;
        }
    }

    private function validateBirthday(): void
    {
        if (!isset($this->data['geburtstag'])) {
            return;
        }
        $enDate  = DateTime::createFromFormat('Y-m-d', $this->data['geburtstag']);
        $errCode = Text::checkDate(
            $enDate === false ? $this->data['geburtstag'] : $enDate->format('d.m.Y'),
            $this->config['kunden']['kundenregistrierung_abfragen_geburtstag'] === 'Y'
        );
        if ($errCode > 0) {
            $this->errors['geburtstag'] = $errCode;
        }
    }

    private function validateCustomerFields(): void
    {
        if (($this->config['kundenfeld']['kundenfeld_anzeigen'] ?? 'N') !== 'Y') {
            return;
        }
        $customerFields = new CustomerFields(Shop::getLanguageID());
        /** @var CustomerField $customerField */
        foreach ($customerFields as $customerField) {
            // Kundendaten ändern?
            $customerFieldIdx = 'custom_' . $customerField->getID();
            if (
                isset($this->data[$customerFieldIdx])
                && ($check = $customerField->validate($this->data[$customerFieldIdx])) !== CustomerField::VALIDATE_OK
            ) {
                $this->errors['custom'][$customerField->getID()] = $check;
            }
        }
    }

    private function validateTime(): void
    {
        if (
            (int)($this->data['editRechnungsadresse'] ?? 0) !== 1
            && ($this->config['kunden']['kundenregistrierung_pruefen_zeit'] ?? 'N') === 'Y'
        ) {
            $regTime = (int)($_SESSION['dRegZeit'] ?? 0);
            if (!($regTime + 5 < \time())) {
                $this->errors['formular_zeit'] = 1;
            }
        }
    }

    private function validateCaptcha(): void
    {
        if (($this->config['kunden']['registrieren_captcha'] ?? 'N') !== 'N' && !Form::validateCaptcha($this->data)) {
            $this->errors['captcha'] = 2;
        }
    }

    public function validateCustomerAccount(bool $checkpass): void
    {
        if ($checkpass === true) {
            if ($this->data['pass'] !== $this->data['pass2']) {
                $this->errors['pass_ungleich'] = 1;
            }
            if (\mb_strlen($this->data['pass']) < $this->config['kunden']['kundenregistrierung_passwortlaenge']) {
                $this->errors['pass_zu_kurz'] = 1;
            }
            if (\mb_strlen($this->data['pass']) > 255) {
                $this->errors['pass_zu_lang'] = 1;
            }
        }
        // existiert diese email bereits?
        $customerID = Frontend::getCustomer()->getID();
        if (!isset($this->errors['email']) && !self::isEmailAvailable($this->data['email'], $customerID)) {
            if ($customerID <= 0) {
                $this->errors['email_vorhanden'] = 1;
            }
            $this->errors['email'] = 5;
        }
    }
}
