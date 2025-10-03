<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum Customer: string implements OptionInterface
{
    case TITLE_PROMPT                      = 'kundenregistrierung_abfragen_titel';
    case SALUATION_PROMPT                  = 'kundenregistrierung_abfragen_anrede';
    case FIRSTNAME_REQUIRED                = 'kundenregistrierung_pflicht_vorname';
    case COMPANY_PROMPT                    = 'kundenregistrierung_abfragen_firma';
    case COMPANY_2_PROMPT                  = 'kundenregistrierung_abfragen_firmazusatz';
    case ADDRESS_2_PROMPT                  = 'kundenregistrierung_abfragen_adresszusatz';
    case MOBILE_PROMPT                     = 'kundenregistrierung_abfragen_mobil';
    case FAX_PROMPT                        = 'kundenregistrierung_abfragen_fax';
    case TEL_PROMPT                        = 'kundenregistrierung_abfragen_tel';
    case WWW_PROMPT                        = 'kundenregistrierung_abfragen_www';
    case BIRTHDAY_PROMPT                   = 'kundenregistrierung_abfragen_geburtstag';
    case PASSWORD_MIN_LENGTH               = 'kundenregistrierung_passwortlaenge';
    case DEFAULT_COUNTRY                   = 'kundenregistrierung_standardland';
    case STATE_PROMPT                      = 'kundenregistrierung_abfragen_bundesland';
    case NAME_CHECK                        = 'kundenregistrierung_pruefen_name';
    case TIME_CHECK                        = 'kundenregistrierung_pruefen_zeit';
    case EMAIL_CHECK                       = 'kundenregistrierung_pruefen_email';
    case ALLOW_ONLY_SHIPPING_COUNTRIES     = 'kundenregistrierung_nur_lieferlaender';
    case ZIP_CHECK                         = 'kundenregistrierung_abgleichen_plz';
    case CAPTCHA_SHOW                      = 'registrieren_captcha';
    case MAX_LOGIN_TRIES                   = 'kundenlogin_max_loginversuche';
    case ENABLE_2FA                        = 'enable_2fa';
    case DIRECT_ADVERTISING_INFO_SHOW      = 'direct_advertising';
    case DELIVERY_ADDRESS_SALUTAION_PROMPT = 'lieferadresse_abfragen_anrede';
    case DELIVERY_ADDRESS_TITLE_PROMPT     = 'lieferadresse_abfragen_titel';
    case DELIVERY_ADDRESS_COMPANY_PROMPT   = 'lieferadresse_abfragen_firma';
    case DELIVERY_ADDRESS_COMPANY_2_PROMPT = 'lieferadresse_abfragen_firmazusatz';
    case DELIVERY_ADDRESS_2_PROMPT         = 'lieferadresse_abfragen_adresszusatz';
    case DELIVERY_ADDRESS_STATE_PROMPT     = 'lieferadresse_abfragen_bundesland';
    case DELIVERY_ADDRESS_TEL_PROMPT       = 'lieferadresse_abfragen_tel';
    case DELIVERY_ADDRESS_EMAIL_PROMPT     = 'lieferadresse_abfragen_email';
    case DELIVERY_ADDRESS_FAX_PROMPT       = 'lieferadresse_abfragen_fax';
    case DELIVERY_ADDRESS_MOBILE_PROMPT    = 'lieferadresse_abfragen_mobil';
    case DELIVERY_ADDRESS_COUNTRY_PROMPT   = 'lieferadresse_abfragen_standardland';
    case USTID                             = 'shop_ustid';
    case USTID_PROMPT                      = 'kundenregistrierung_abfragen_ustid';
    case USTID_CHECK                       = 'shop_ustid_bzstpruefung';
    case USTID_CHECK_REQUIRE               = 'shop_ustid_force_remote_check';
    case FORGOT_PASSWORD_CAPTCHA_SHOW      = 'forgot_password_captcha';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::CUSTOMER;
    }
}
