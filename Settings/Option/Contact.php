<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum Contact: string implements OptionInterface
{
    case SALUTATION_PROMPT = 'kontakt_abfragen_anrede';
    case FIRSTNAM_PROMPT   = 'kontakt_abfragen_vorname';
    case LASTNAME_PROMPT   = 'kontakt_abfragen_nachname';
    case COMPANY_PROMPT    = 'kontakt_abfragen_firma';
    case TEL_PROMPT        = 'kontakt_abfragen_tel';
    case FAX_PROMPT        = 'kontakt_abfragen_fax';
    case MOBILE_PROMPT     = 'kontakt_abfragen_mobil';
    case SEND_COPY         = 'kontakt_kopiekunde';
    case LOCK_FOR_MINUTES  = 'kontakt_sperre_minuten';
    case USE_CAPTCHA       = 'kontakt_abfragen_captcha';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::CONTACT;
    }
}
