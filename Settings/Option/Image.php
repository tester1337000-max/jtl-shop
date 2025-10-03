<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum Image: string implements OptionInterface
{
    case MANUFACTURER_XS_WIDTH          = 'bilder_hersteller_mini_breite';
    case MANUFACTURER_XS_HEIGHT         = 'bilder_hersteller_mini_hoehe';
    case MANUFACTURER_SM_WIDTH          = 'bilder_hersteller_klein_breite';
    case MANUFACTURER_SM_HEIGHT         = 'bilder_hersteller_klein_hoehe';
    case MANUFACTURER_MD_WIDTH          = 'bilder_hersteller_normal_breite';
    case MANUFACTURER_MD_HEIGHT         = 'bilder_hersteller_normal_hoehe';
    case MANUFACTURER_LG_WIDTH          = 'bilder_hersteller_gross_breite';
    case MANUFACTURER_LG_HEIGHT         = 'bilder_hersteller_gross_hoehe';
    case CHARACTERISTIC_XS_WIDTH        = 'bilder_merkmal_mini_breite';
    case CHARACTERISTIC_XS_HEIGHT       = 'bilder_merkmal_mini_hoehe';
    case CHARACTERISTIC_SM_WIDTH        = 'bilder_merkmal_normal_breite';
    case CHARACTERISTIC_SM_HEIGHT       = 'bilder_merkmal_normal_hoehe';
    case CHARACTERISTIC_MD_WIDTH        = 'bilder_merkmal_klein_breite';
    case CHARACTERISTIC_MD_HEIGHT       = 'bilder_merkmal_klein_hoehe';
    case CHARACTERISTIC_LG_WIDTH        = 'bilder_merkmal_gross_breite';
    case CHARACTERISTIC_LG_HEIGHT       = 'bilder_merkmal_gross_hoehe';
    case CHARACTERISTIC_VALUE_XS_WIDTH  = 'bilder_merkmalwert_mini_breite';
    case CHARACTERISTIC_VALUE_XS_HEIGHT = 'bilder_merkmalwert_mini_hoehe';
    case CHARACTERISTIC_VALUE_SM_WIDTH  = 'bilder_merkmalwert_klein_breite';
    case CHARACTERISTIC_VALUE_SM_HEIGHT = 'bilder_merkmalwert_normal_hoehe';
    case CHARACTERISTIC_VALUE_MD_WIDTH  = 'bilder_merkmalwert_normal_breite';
    case CHARACTERISTIC_VALUE_MD_HEIGHT = 'bilder_merkmalwert_klein_hoehe';
    case CHARACTERISTIC_VALUE_LG_WIDTH  = 'bilder_merkmalwert_gross_breite';
    case CHARACTERISTIC_VALUE_LG_HEIGHT = 'bilder_merkmalwert_gross_hoehe';
    case CONFIGGROUP_XS_WIDTH           = 'bilder_konfiggruppe_mini_breite';
    case CONFIGGROUP_XS_HEIGHT          = 'bilder_konfiggruppe_mini_hoehe';
    case CONFIGGROUP_SM_WIDTH           = 'bilder_konfiggruppe_klein_breite';
    case CONFIGGROUP_SM_HEIGHT          = 'bilder_konfiggruppe_klein_hoehe';
    case CONFIGGROUP_MD_WIDTH           = 'bilder_konfiggruppe_normal_breite';
    case CONFIGGROUP_MD_HEIGHT          = 'bilder_konfiggruppe_normal_hoehe';
    case CONFIGGROUP_LG_WIDTH           = 'bilder_konfiggruppe_gross_breite';
    case CONFIGGROUP_LG_HEIGHT          = 'bilder_konfiggruppe_gross_hoehe';
    case CATEGORY_XS_WIDTH              = 'bilder_kategorien_mini_breite';
    case CATEGORY_XS_HEIGHT             = 'bilder_kategorien_mini_hoehe';
    case CATEGORY_SM_WIDTH              = 'bilder_kategorien_klein_breite';
    case CATEGORY_SM_HEIGHT             = 'bilder_kategorien_klein_hoehe';
    case CATEGORY_MD_WIDTH              = 'bilder_kategorien_breite';
    case CATEGORY_MD_HEIGHT             = 'bilder_kategorien_hoehe';
    case CATEGORY_LG_WIDTH              = 'bilder_kategorien_gross_breite';
    case CATEGORY_LG_HEIGHT             = 'bilder_kategorien_gross_hoehe';
    case VARIATION_XS_WIDTH             = 'bilder_variationen_mini_breite';
    case VARIATION_XS_HEIGHT            = 'bilder_variationen_mini_hoehe';
    case VARIATION_SM_WIDTH             = 'bilder_variationen_klein_breite';
    case VARIATION_SM_HEIGHT            = 'bilder_variationen_klein_hoehe';
    case VARIATION_MD_WIDTH             = 'bilder_variationen_breite';
    case VARIATION_MD_HEIGHT            = 'bilder_variationen_hoehe';
    case VARIATION_LG_WIDTH             = 'bilder_variationen_gross_breite';
    case VARIATION_LG_HEIGHT            = 'bilder_variationen_gross_hoehe';
    case PRODUCT_XS_WIDTH               = 'bilder_artikel_mini_breite';
    case PRODUCT_XS_HEIGHT              = 'bilder_artikel_mini_hoehe';
    case PRODUCT_SM_WIDTH               = 'bilder_artikel_klein_breite';
    case PRODUCT_SM_HEIGHT              = 'bilder_artikel_klein_hoehe';
    case PRODUCT_MD_WIDTH               = 'bilder_artikel_normal_breite';
    case PRODUCT_MD_HEIGHT              = 'bilder_artikel_normal_hoehe';
    case PRODUCT_LG_WIDTH               = 'bilder_artikel_gross_breite';
    case PRODUCT_LG_HEIGHT              = 'bilder_artikel_gross_hoehe';
    case OPC_XS_WIDTH                   = 'bilder_opc_mini_breite';
    case OPC_XS_HEIGHT                  = 'bilder_opc_mini_hoehe';
    case OPC_SM_WIDTH                   = 'bilder_opc_klein_breite';
    case OPC_SM_HEIGHT                  = 'bilder_opc_klein_hoehe';
    case OPC_MD_WIDTH                   = 'bilder_opc_normal_breite';
    case OPC_MD_HEIGHT                  = 'bilder_opc_normal_hoehe';
    case OPC_LG_WIDTH                   = 'bilder_opc_gross_breite';
    case OPC_LG_HEIGHT                  = 'bilder_opc_gross_hoehe';
    case NEWS_XS_WIDTH                  = 'bilder_news_mini_breite';
    case NEWS_XS_HEIGHT                 = 'bilder_news_mini_hoehe';
    case NEWS_SM_WIDTH                  = 'bilder_news_klein_breite';
    case NEWS_SM_HEIGHT                 = 'bilder_news_klein_hoehe';
    case NEWS_MD_WIDTH                  = 'bilder_news_normal_breite';
    case NEWS_MD_HEIGHT                 = 'bilder_news_normal_hoehe';
    case NEWS_LG_WIDTH                  = 'bilder_news_gross_breite';
    case NEWS_LG_HEIGHT                 = 'bilder_news_gross_hoehe';
    case NEWS_CATEGORY_XS_WIDTH         = 'bilder_newskategorie_mini_breite';
    case NEWS_CATEGORY_XS_HEIGHT        = 'bilder_newskategorie_mini_hoehe';
    case NEWS_CATEGORY_SM_WIDTH         = 'bilder_newskategorie_klein_breite';
    case NEWS_CATEGORY_SM_HEIGHT        = 'bilder_newskategorie_klein_hoehe';
    case NEWS_CATEGORY_MD_WIDTH         = 'bilder_newskategorie_normal_breite';
    case NEWS_CATEGORY_MD_HEIGHT        = 'bilder_newskategorie_normal_hoehe';
    case NEWS_CATEGORY_LG_WIDTH         = 'bilder_newskategorie_gross_breite';
    case NEWS_CATEGORY_LG_HEIGHT        = 'bilder_newskategorie_gross_hoehe';
    case JPG_QUALITY                    = 'bilder_jpg_quali';
    case PRODUCT_NAMES                  = 'bilder_artikel_namen';
    case CATEGORY_NAMES                 = 'bilder_kategorie_namen';
    case VARIATION_NAMES                = 'bilder_variation_namen';
    case MANUFACTURER_NAMES             = 'bilder_hersteller_namen';
    case CHARACTERISTIC_NAMES           = 'bilder_merkmal_namen';
    case CHARACTERISTIC_VALUE_NAMES     = 'bilder_merkmalwert_namen';
    case EXTERNAL_INTERFACE_ENABLED     = 'bilder_externe_bildschnittstelle';
    case IMAGE_FORMAT                   = 'bilder_dateiformat';
    case USE_CONTAINER                  = 'container_verwenden';
    case BACKGROUND                     = 'bilder_hintergrundfarbe';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::IMAGE;
    }
}
