<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum Review: string implements OptionInterface
{
    case DO_USE               = 'bewertung_anzeigen';
    case ACTIVATE             = 'bewertung_freischalten';
    case ITEMS_PER_PAGE       = 'bewertung_anzahlseite';
    case SORT                 = 'bewertung_sortierung';
    case HELPFUL_SHOW         = 'bewertung_hilfreich_anzeigen';
    case ALL_LANGUAGES        = 'bewertung_alle_sprachen';
    case REMINDER_USE         = 'bewertungserinnerung_nutzen';
    case CUSTOMER_GROUPS      = 'bewertungserinnerung_kundengruppen';
    case SHIPPING_DAYS        = 'bewertungserinnerung_versandtage';
    case CREDIT_BONUS_USE     = 'bewertung_guthaben_nutzen';
    case LEVEL2_MIN_CHARS     = 'bewertung_stufe2_anzahlzeichen';
    case LEVEL1_CREDIT_BONUS  = 'bewertung_stufe1_guthaben';
    case LEVEL2_CREDIT_BONUS  = 'bewertung_stufe2_guthaben';
    case MAX_CREDIT_BONUS     = 'bewertung_max_guthaben';
    case ONLY_PURCHASED_ITEMS = 'bewertung_artikel_gekauft';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::REVIEW;
    }
}
