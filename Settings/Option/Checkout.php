<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum Checkout: string implements OptionInterface
{
    case CHECKOUT_METHOD                       = 'bestellvorgang_kaufabwicklungsmethode';
    case WITHDRAWAL_POLICY_SHOW                = 'bestellvorgang_wrb_anzeigen';
    case INDIVIDUAL_PRICES_SHOW                = 'bestellvorgang_einzelpreise_anzeigen';
    case DELIVERY_STATUS_SHOW                  = 'bestellvorgang_lieferstatus_anzeigen';
    case GUESTS_ALLOW                          = 'bestellvorgang_unregistriert';
    case ALLOW_NEW_CUSTOMER_COUPONS_FOR_GUESTS = 'bestellvorgang_unregneukundenkupon_zulassen';
    case SHIPPING_TAX_RATE                     = 'bestellvorgang_versand_steuersatz';
    case CHARACTERISTICS_SHOW                  = 'bestellvorgang_artikelmerkmale';
    case PRODUCT_ATTRIBUTES_SHOW               = 'bestellvorgang_artikelattribute';
    case PRODUCT_SHORTDESCRIPTION_SHOW         = 'bestellvorgang_artikelkurzbeschreibung';
    case LINE_ITEMS_SHOW                       = 'bestellvorgang_partlist';
    case ORDER_ID_PREFIX                       = 'bestellabschluss_bestellnummer_praefix';
    case ORDER_ID_START                        = 'bestellabschluss_bestellnummer_anfangsnummer';
    case ORDER_ID_SUFFIX                       = 'bestellabschluss_bestellnummer_suffix';
    case ROUND_TOTAL_5                         = 'bestellabschluss_runden5';
    case SPAMPROTECTION_SHOW                   = 'bestellabschluss_spamschutz_nutzen';
    case COMPLETION_PAGE                       = 'bestellabschluss_abschlussseite';
    case PRODUCT_IMAGES_SHOW                   = 'warenkorb_produktbilder_anzeigen';
    case SHIPPING_COST_DETERMINATION_SHOW      = 'warenkorb_versandermittlung_anzeigen';
    case COUPON_PROMPT_SHOW                    = 'warenkorb_kupon_anzeigen';
    case XSELLING_SHOW                         = 'warenkorb_xselling_anzeigen';
    case XSELLING_QTY                          = 'warenkorb_xselling_anzahl';
    case VARIATION_VALUES_SHOW                 = 'warenkorb_varianten_varikombi_anzeigen';
    case TOTAL_WEIGHT_SHOW                     = 'warenkorb_gesamtgewicht_anzeigen';
    case COMBINE_BASKETS                       = 'warenkorb_warenkorb2pers_merge';
    case SAVE_BASKET_ENABLED                   = 'warenkorbpers_nutzen';
    case SCALE_PRICES_ACROSS_VARIATIONS        = 'general_child_item_bulk_pricing';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::CHECKOUT;
    }
}
