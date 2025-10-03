<?php

declare(strict_types=1);

namespace JTL\Customer\Registration\Validator;

use JTL\Helpers\Text;

/**
 * Class ShippingAddress
 * @package JTL\Customer\Registration\Validator
 */
class ShippingAddress extends AbstractValidator
{
    /**
     * @inheritdoc
     */
    public function validate(): bool
    {
        $this->validateBaseData();
        $this->validateOptionalData();
        $this->validateEmail();
        $this->validatePhoneData();
        $this->validateAddress();
        \executeHook(\HOOK_VALIDATE_SHIPPING_ADDRESS_FORM, [
            'validator' => $this
        ]);

        return \count($this->errors) === 0;
    }

    private function validateBaseData(): void
    {
        foreach (['nachname', 'strasse', 'hausnummer', 'plz', 'ort', 'land'] as $dataKey) {
            $this->data[$dataKey] = isset($this->data[$dataKey]) ? \trim($this->data[$dataKey]) : null;
            if (!isset($this->data[$dataKey]) || !$this->data[$dataKey]) {
                $this->errors[$dataKey] = 1;
            }
        }
    }

    private function validateOptionalData(): void
    {
        $conf = [
            'lieferadresse_abfragen_titel'        => 'titel',
            'lieferadresse_abfragen_adresszusatz' => 'adresszusatz',
            'lieferadresse_abfragen_bundesland'   => 'bundesland',
        ];
        foreach ($conf as $confKey => $dataKey) {
            if ($this->config[$confKey] !== 'Y') {
                continue;
            }
            $this->data[$dataKey] = isset($this->data[$dataKey]) ? \trim($this->data[$dataKey]) : null;
            if (!$this->data[$dataKey]) {
                $this->errors[$dataKey] = 1;
            }
        }
    }

    private function validateEmail(): void
    {
        if ($this->config['lieferadresse_abfragen_email'] === 'N') {
            return;
        }
        $this->data['email'] = \trim($this->data['email']);

        if (empty($this->data['email'])) {
            if ($this->config['lieferadresse_abfragen_email'] === 'Y') {
                $this->errors['email'] = 1;
            }
        } elseif (Text::filterEmailAddress($this->data['email']) === false) {
            $this->errors['email'] = 2;
        }
    }

    private function validatePhoneData(): void
    {
        foreach (['tel', 'mobil', 'fax'] as $telType) {
            if ($this->config['lieferadresse_abfragen_' . $telType] === 'N') {
                continue;
            }
            $result = Text::checkPhoneNumber($this->data[$telType]);
            if ($result === 1 && $this->config['lieferadresse_abfragen_' . $telType] === 'Y') {
                $this->errors[$telType] = 1;
            } elseif ($result > 1) {
                $this->errors[$telType] = $result;
            }
        }
    }

    private function validateAddress(): void
    {
        if (empty($_SESSION['check_liefer_plzort']) && $this->config['kundenregistrierung_abgleichen_plz'] === 'Y') {
            if (!self::isValidAddress($this->data['plz'], $this->data['ort'], $this->data['land'])) {
                $this->errors['plz']             = 2;
                $this->errors['ort']             = 2;
                $_SESSION['check_liefer_plzort'] = 1;
            }
        } else {
            unset($_SESSION['check_liefer_plzort']);
        }
    }
}
