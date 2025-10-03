<?php

declare(strict_types=1);

// Version
const APPLICATION_VERSION         = '5.6.0';
const APPLICATION_BUILD_SHA       = '#DEV#';
const RELEASE_TYPE                = 'BETA';
const JTL_MIN_WAWI_VERSION        = 100000;
const JTL_MIN_SHOP_UPDATE_VERSION = '4.2.0';

const SMARTY_MBSTRING = true;
// Einstellungssektionen
const CONF_GLOBAL            = 1;
const CONF_STARTSEITE        = 2;
const CONF_EMAILS            = 3;
const CONF_ARTIKELUEBERSICHT = 4;
const CONF_ARTIKELDETAILS    = 5;
const CONF_KUNDEN            = 6;
const CONF_KAUFABWICKLUNG    = 7;
const CONF_BOXEN             = 8;
const CONF_BILDER            = 9;
const CONF_SONSTIGES         = 10;
const CONF_TEMPLATE          = 11;
const CONF_BRANDING          = 12;
const CONF_ZAHLUNGSARTEN     = 100;
const CONF_EXPORTFORMATE     = 101;
const CONF_KONTAKTFORMULAR   = 102;
const CONF_SHOPINFO          = 103;
const CONF_RSS               = 104;
const CONF_PREISVERLAUF      = 105;
const CONF_VERGLEICHSLISTE   = 106;
const CONF_BEWERTUNG         = 107;
const CONF_NEWSLETTER        = 108;
const CONF_KUNDENFELD        = 109;
const CONF_NAVIGATIONSFILTER = 110;
const CONF_EMAILBLACKLIST    = 111;
const CONF_METAANGABEN       = 112;
const CONF_NEWS              = 113;
const CONF_SITEMAP           = 114;
const CONF_SUCHSPECIAL       = 119;
const CONF_AUSWAHLASSISTENT  = 121;
const CONF_CACHING           = 124;
const CONF_FS                = 127;
const CONF_CRON              = 128;
const CONF_CONSENTMANAGER    = 129;
/**
 * @deprecated
 */
const CONF_CHECKBOX            = 120;
const CONF_KUNDENWERBENKUNDEN  = 116;
const CONF_LOGO                = 125;
const CONF_PLUGINZAHLUNGSARTEN = 126;

const C_WARENKORBPOS_TYP_ARTIKEL                  = 1;
const C_WARENKORBPOS_TYP_VERSANDPOS               = 2;
const C_WARENKORBPOS_TYP_KUPON                    = 3;
const C_WARENKORBPOS_TYP_GUTSCHEIN                = 4;
const C_WARENKORBPOS_TYP_ZAHLUNGSART              = 5;
const C_WARENKORBPOS_TYP_VERSANDZUSCHLAG          = 6;
const C_WARENKORBPOS_TYP_NEUKUNDENKUPON           = 7;
const C_WARENKORBPOS_TYP_NACHNAHMEGEBUEHR         = 8;
const C_WARENKORBPOS_TYP_VERSAND_ARTIKELABHAENGIG = 9;
const C_WARENKORBPOS_TYP_VERPACKUNG               = 10;
const C_WARENKORBPOS_TYP_GRATISGESCHENK           = 11;

const C_WARENKORBPOS_TYP_ZINSAUFSCHLAG       = 13;
const C_WARENKORBPOS_TYP_BEARBEITUNGSGEBUEHR = 14;
const C_WARENKORBPOS_TYP_RESERVED1           = 15; // reserviert für Wawi intern - SHOP-3545
const C_WARENKORBPOS_TYP_RESERVED2           = 16; // reserviert für Retoure in POS - SHOP-3545
const C_WARENKORBPOS_TYP_RESERVED3           = 17; // reserviert für Mehrzweckgutschein
const C_WARENKORBPOS_TYP_RESERVED4           = 18; // reserviert für MehrzweckgutscheinDigital

const KONFIG_ITEM_TYP_ARTIKEL = 0;
const KONFIG_ITEM_TYP_SPEZIAL = 1;

const KONFIG_ANZEIGE_TYP_CHECKBOX       = 0;
const KONFIG_ANZEIGE_TYP_RADIO          = 1;
const KONFIG_ANZEIGE_TYP_DROPDOWN       = 2;
const KONFIG_ANZEIGE_TYP_DROPDOWN_MULTI = 3;

const KONFIG_AUSWAHL_TYP_BELIEBIG = -1;
const KONFIG_AUSWAHL_TYP_MIN1     = 0;

const URLART_ARTIKEL        = 1;
const URLART_KATEGORIE      = 2;
const URLART_SEITE          = 3;
const URLART_HERSTELLER     = 4;
const URLART_LIVESUCHE      = 5;
const URLART_MERKMAL        = 7;
const URLART_NEWS           = 8;
const URLART_NEWSMONAT      = 9;
const URLART_NEWSKATEGORIE  = 10;
const URLART_SEARCHSPECIALS = 12;
const URLART_NEWSLETTER     = 13;
// bestellstatus
const BESTELLUNG_STATUS_STORNO                 = -1;
const BESTELLUNG_STATUS_OFFEN                  = 1;
const BESTELLUNG_STATUS_IN_BEARBEITUNG         = 2;
const BESTELLUNG_STATUS_BEZAHLT                = 3;
const BESTELLUNG_STATUS_VERSANDT               = 4;
const BESTELLUNG_STATUS_TEILVERSANDT           = 5;
const BESTELLUNG_VERSANDBESTAETIGUNG_MAX_TAGE  = 7;
const BESTELLUNG_ZAHLUNGSBESTAETIGUNG_MAX_TAGE = 7;

// Retouren
const RETURN_STATUS_REJECTED    = -1;
const RETURN_STATUS_OPEN        = 1;
const RETURN_STATUS_IN_PROGRESS = 2;
const RETURN_STATUS_ACCEPTED    = 3;
const RETURN_STATUS_COMPLETED   = 4;

// zahlungsart mails
const ZAHLUNGSART_MAIL_EINGANG  = 0x0001;
const ZAHLUNGSART_MAIL_STORNO   = 0x0010;
const ZAHLUNGSART_MAIL_RESTORNO = 0x0100;
// mailtemplates
const MAILTEMPLATE_GUTSCHEIN                         = 'core_jtl_gutschein';
const MAILTEMPLATE_BESTELLBESTAETIGUNG               = 'core_jtl_bestellbestaetigung';
const MAILTEMPLATE_PASSWORT_VERGESSEN                = 'core_jtl_passwort_vergessen';
const MAILTEMPLATE_ADMINLOGIN_PASSWORT_VERGESSEN     = 'core_jtl_admin_passwort_vergessen';
const MAILTEMPLATE_NEUKUNDENREGISTRIERUNG            = 'core_jtl_neukundenregistrierung';
const MAILTEMPLATE_ACCOUNTERSTELLUNG_DURCH_BETREIBER = 'core_jtl_accounterstellung_durch_betreiber';
const MAILTEMPLATE_BESTELLUNG_BEZAHLT                = 'core_jtl_bestellung_bezahlt';
const MAILTEMPLATE_BESTELLUNG_VERSANDT               = 'core_jtl_bestellung_versandt';
const MAILTEMPLATE_BESTELLUNG_AKTUALISIERT           = 'core_jtl_bestellung_aktualisiert';
const MAILTEMPLATE_BESTELLUNG_STORNO                 = 'core_jtl_bestellung_storno';
const MAILTEMPLATE_BESTELLUNG_RESTORNO               = 'core_jtl_bestellung_restorno';
const MAILTEMPLATE_KUNDENACCOUNT_GELOESCHT           = 'core_jtl_account_geloescht';
const MAILTEMPLATE_KUPON                             = 'core_jtl_kupon';
const MAILTEMPLATE_KUNDENGRUPPE_ZUWEISEN             = 'core_jtl_kdgrp_zuweisung';
const MAILTEMPLATE_KONTAKTFORMULAR                   = 'core_jtl_kontaktformular';
const MAILTEMPLATE_PRODUKTANFRAGE                    = 'core_jtl_produktanfrage';
const MAILTEMPLATE_PRODUKT_WIEDER_VERFUEGBAR         = 'core_jtl_verfuegbarkeitsbenachrichtigung';
const MAILTEMPLATE_WUNSCHLISTE                       = 'core_jtl_wunschliste';
const MAILTEMPLATE_BEWERTUNGERINNERUNG               = 'core_jtl_bewertungerinnerung';
const MAILTEMPLATE_NEWSLETTERANMELDEN                = 'core_jtl_newsletteranmelden';
/**
 * @deprecated
 */
const MAILTEMPLATE_KUNDENWERBENKUNDEN = 'core_jtl_kundenwerbenkunden';
/**
 * @deprecated
 */
const MAILTEMPLATE_KUNDENWERBENKUNDENBONI          = 'core_jtl_kundenwerbenkundenboni';
const MAILTEMPLATE_STATUSEMAIL                     = 'core_jtl_statusemail';
const MAILTEMPLATE_CHECKBOX_SHOPBETREIBER          = 'core_jtl_checkbox_shopbetreiber';
const MAILTEMPLATE_BEWERTUNG_GUTHABEN              = 'core_jtl_bewertung_guthaben';
const MAILTEMPLATE_BESTELLUNG_TEILVERSANDT         = 'core_jtl_bestellung_teilversandt';
const MAILTEMPLATE_ANBIETERKENNZEICHNUNG           = 'core_jtl_anbieterkennzeichnung';
const MAILTEMPLATE_PRODUKT_WIEDER_VERFUEGBAR_OPTIN = 'core_jtl_verfuegbarkeitsbenachrichtigung_optin';
const MAILTEMPLATE_FOOTER                          = 'core_jtl_footer';
const MAILTEMPLATE_HEADER                          = 'core_jtl_header';
const MAILTEMPLATE_AKZ                             = 'core_jtl_anbieterkennzeichnung';
// Suche
const SEARCH_SORT_NONE         = -1;
const SEARCH_SORT_STANDARD     = 100;
const SEARCH_SORT_NAME_ASC     = 1;
const SEARCH_SORT_NAME_DESC    = 2;
const SEARCH_SORT_PRICE_ASC    = 3;
const SEARCH_SORT_PRICE_DESC   = 4;
const SEARCH_SORT_EAN          = 5;
const SEARCH_SORT_NEWEST_FIRST = 6;
const SEARCH_SORT_PRODUCTNO    = 7;
/**
 * @deprecated
 */
const SEARCH_SORT_AVAILABILITY = 8;
const SEARCH_SORT_WEIGHT       = 9;
const SEARCH_SORT_DATEOFISSUE  = 10;
const SEARCH_SORT_BESTSELLER   = 11;
const SEARCH_SORT_RATING       = 12;
//
const SEARCH_SORT_CRITERION_NAME      = 'artikelname';
const SEARCH_SORT_CRITERION_NAME_ASC  = 'artikelname aufsteigend';
const SEARCH_SORT_CRITERION_NAME_DESC = 'artikelname absteigend';
const SEARCH_SORT_CRITERION_PRODUCTNO = 'artikelnummer';
/**
 * @deprecated
 */
const SEARCH_SORT_CRITERION_AVAILABILITY = 'lagerbestand';
const SEARCH_SORT_CRITERION_WEIGHT       = 'gewicht';
const SEARCH_SORT_CRITERION_PRICE        = 'preis';
const SEARCH_SORT_CRITERION_PRICE_ASC    = 'preis aufsteigend';
const SEARCH_SORT_CRITERION_PRICE_DESC   = 'preis absteigend';
const SEARCH_SORT_CRITERION_EAN          = 'ean';
const SEARCH_SORT_CRITERION_NEWEST_FIRST = 'neuste zuerst';
const SEARCH_SORT_CRITERION_DATEOFISSUE  = 'erscheinungsdatum';
const SEARCH_SORT_CRITERION_BESTSELLER   = 'bestseller';
const SEARCH_SORT_CRITERION_RATING       = 'bewertungen';
// Einstellungen
const EINSTELLUNGEN_ARTIKELANZEIGEFILTER_ALLE         = 1;
const EINSTELLUNGEN_ARTIKELANZEIGEFILTER_LAGER        = 2;
const EINSTELLUNGEN_ARTIKELANZEIGEFILTER_LAGERNULL    = 3;
const EINSTELLUNGEN_KATEGORIEANZEIGEFILTER_ALLE       = 1;
const EINSTELLUNGEN_KATEGORIEANZEIGEFILTER_NICHTLEERE = 2;
// Linktypen
const LINKTYP_EIGENER_CONTENT    = 1;
const LINKTYP_EXTERNE_URL        = 2;
const LINKTYP_STARTSEITE         = 5;
const LINKTYP_VERSAND            = 6;
const LINKTYP_LOGIN              = 7;
const LINKTYP_REGISTRIEREN       = 8;
const LINKTYP_WARENKORB          = 9;
const LINKTYP_PASSWORD_VERGESSEN = 10;
const LINKTYP_AGB                = 11;
const LINKTYP_DATENSCHUTZ        = 12;
const LINKTYP_KONTAKT            = 13;
/**
 * @deprecated
 */
const LINKTYP_TAGGING          = 14;
const LINKTYP_LIVESUCHE        = 15;
const LINKTYP_HERSTELLER       = 16;
const LINKTYP_NEWSLETTER       = 17;
const LINKTYP_NEWSLETTERARCHIV = 18;
const LINKTYP_NEWS             = 19;
/**
 * @deprecated
 */
const LINKTYP_NEWSARCHIV = 20;
const LINKTYP_SITEMAP    = 21;
/**
 * @deprecated
 */
const LINKTYP_UMFRAGE                 = 22;
const LINKTYP_GRATISGESCHENK          = 23;
const LINKTYP_WRB                     = 24;
const LINKTYP_PLUGIN                  = 25;
const LINKTYP_AUSWAHLASSISTENT        = 26;
const LINKTYP_IMPRESSUM               = 27;
const LINKTYP_404                     = 29;
const LINKTYP_BATTERIEGESETZ_HINWEISE = 30;
const LINKTYP_WRB_FORMULAR            = 31;
const LINKTYP_BESTELLVORGANG          = 32;
const LINKTYP_BESTELLABSCHLUSS        = 33;
const LINKTYP_WUNSCHLISTE             = 34;
const LINKTYP_VERGLEICHSLISTE         = 35;
const LINKTYP_REFERENZ                = 36;
const LINKTYP_WARTUNG                 = 37;
const LINKTYP_BESTELLSTATUS           = 38;
const LINKTYP_BEWERTUNG               = 39;
// Artikel
const INWKNICHTLEGBAR_LAGER              = -1;
const INWKNICHTLEGBAR_LAGERVAR           = -2;
const INWKNICHTLEGBAR_NICHTVORBESTELLBAR = -3;
const INWKNICHTLEGBAR_PREISAUFANFRAGE    = -4;
const INWKNICHTLEGBAR_UNVERKAEUFLICH     = -5;
// Attribute
const KAT_ATTRIBUT_KATEGORIEBOX          = 'kategoriebox';
const KAT_ATTRIBUT_ARTIKELSORTIERUNG     = 'artikelsortierung';
const KAT_ATTRIBUT_METATITLE             = 'meta_title';
const KAT_ATTRIBUT_METADESCRIPTION       = 'meta_description';
const KAT_ATTRIBUT_METAKEYWORDS          = 'meta_keywords';
const KAT_ATTRIBUT_BILDNAME              = 'bildname';
const KAT_ATTRIBUT_DARSTELLUNG           = 'darstellung';
const KAT_ATTRIBUT_CSSKLASSE             = 'css_klasse';
const KAT_ATTRIBUT_MERKMALFILTER         = 'merkmalfilter';
const KAT_ATTRIBUT_NO_INDEX              = 'noindex';
const ART_ATTRIBUT_STEUERTEXT            = 'steuertext';
const ART_ATTRIBUT_METATITLE             = 'meta_title';
const ART_ATTRIBUT_METADESCRIPTION       = 'meta_description';
const ART_ATTRIBUT_METAKEYWORDS          = 'meta_keywords';
const ART_ATTRIBUT_BILDLINK              = 'artikelbildlink';
const ART_ATTRIBUT_GRATISGESCHENKAB      = 'gratisgeschenk ab';
const ART_ATTRIBUT_AMPELTEXT_GRUEN       = 'ampel_text_gruen';
const ART_ATTRIBUT_AMPELTEXT_GELB        = 'ampel_text_gelb';
const ART_ATTRIBUT_AMPELTEXT_ROT         = 'ampel_text_rot';
const ART_ATTRIBUT_SHORTNAME             = 'shortname';
const KNDGRP_ATTRIBUT_MINDESTBESTELLWERT = 'mindestbestellwert';
// Fkt Attribute
const FKT_ATTRIBUT_KEINE_PREISSUCHMASCHINEN = 'keine preissuchmaschinen';
const FKT_ATTRIBUT_BILDNAME                 = 'bildname';
const FKT_ATTRIBUT_UNVERKAEUFLICH           = 'unverkaeuflich';
const FKT_ATTRIBUT_VERSANDKOSTEN            = 'versandkosten';
const FKT_ATTRIBUT_VERSANDKOSTEN_GESTAFFELT = 'versandkosten gestaffelt';
const FKT_ATTRIBUT_MAXBESTELLMENGE          = 'max bestellmenge';
const FKT_ATTRIBUT_GRATISGESCHENK           = 'gratisgeschenk ab';
const FKT_ATTRIBUT_GRUNDPREISGENAUIGKEIT    = 'grundpreis genauigkeit';
const FKT_ATTRIBUT_WARENKORBMATRIX          = 'warenkorbmatrix';
const FKT_ATTRIBUT_MEDIENDATEIEN            = 'mediendateien';
const FKT_ATTRIBUT_ATTRIBUTEANHAENGEN       = 'attribute anhaengen';
const FKT_ATTRIBUT_STUECKLISTENKOMPONENTEN  = 'stuecklistenkomponenten';
const FKT_ATTRIBUT_INHALT                   = 'inhalt';
const FKT_ATTRIBUT_CANONICALURL_VARKOMBI    = 'varkombi_canonicalurl';
const FKT_ATTRIBUT_VOUCHER                  = 'jtl_voucher';
const FKT_ATTRIBUT_VOUCHER_FLEX             = 'jtl_voucher_flex';
const FKT_ATTRIBUT_NO_GAL_VAR_PREVIEW       = 'no_gall_var_preview';
const FKT_ATTRIBUT_NO_TOPSELLER             = 'no_topseller';
const FKT_ATTRIBUT_CUSTOM_ITEM_BADGE        = 'custom_item_badge';
const FKT_ATTRIBUT_PRODUCT_NOT_RETURNABLE   = 'itemNotReturnable';
const FKT_ATTRIBUT_NO_INDEX                 = 'noindex';

/**
 * @deprecated
 */
const FKT_ATTRIBUT_KONFIG_MAX_ITEMS = 'konfig_max_items';
// Special Content
const SC_KONTAKTFORMULAR = '1';
// Suchspecials
const SEARCHSPECIALS_CUSTOMBADGE      = 0;
const SEARCHSPECIALS_BESTSELLER       = 1;
const SEARCHSPECIALS_SPECIALOFFERS    = 2;
const SEARCHSPECIALS_NEWPRODUCTS      = 3;
const SEARCHSPECIALS_TOPOFFERS        = 4;
const SEARCHSPECIALS_UPCOMINGPRODUCTS = 5;
const SEARCHSPECIALS_TOPREVIEWS       = 6;
const SEARCHSPECIALS_OUTOFSTOCK       = 7;
const SEARCHSPECIALS_ONSTOCK          = 8;
const SEARCHSPECIALS_PREORDER         = 9;
// Adminmenu (Backend)
const LINKTYP_BACKEND_PLUGINS = 5;
const LINKTYP_BACKEND_MODULE  = 7;
// Plugin
const PFAD_PLUGIN_VERSION             = 'version/';
const PFAD_PLUGIN_SQL                 = 'sql/';
const PFAD_PLUGIN_FRONTEND            = 'frontend/';
const PFAD_PLUGIN_ADMINMENU           = 'adminmenu/';
const PFAD_PLUGIN_LICENCE             = 'licence/';
const PFAD_PLUGIN_PAYMENTMETHOD       = 'paymentmethod/';
const PFAD_PLUGIN_TEMPLATE            = 'template/';
const PFAD_PLUGIN_BOXEN               = 'boxen/';
const PFAD_PLUGIN_WIDGET              = 'widget/';
const PFAD_PLUGIN_PORTLETS            = 'Portlets/';
const PFAD_PLUGIN_BLUEPRINTS          = 'blueprints/';
const PFAD_PLUGIN_EXPORTFORMAT        = 'exportformat/';
const PFAD_PLUGIN_UNINSTALL           = 'uninstall/';
const PFAD_PLUGIN_MIGRATIONS          = 'Migrations/';
const PLUGIN_DIR                      = 'plugins/';
const PLUGIN_INFO_FILE                = 'info.xml';
const PLUGIN_LICENCE_METHODE          = 'checkLicence';
const PLUGIN_LICENCE_CLASS            = 'PluginLicence';
const PLUGIN_EXPORTFORMAT_CONTENTFILE = 'PluginContentFile_';
const PLUGIN_SEITENHANDLER            = 'seite_plugin.php';
const PLUGIN_BOOTSTRAPPER             = 'Bootstrap.php';
const OLD_BOOTSTRAPPER                = 'bootstrap.php';

const JOBQUEUE_LOCKFILE = PFAD_LOGFILES . 'jobqueue.lock';

// Red. Param
const R_MINDESTMENGE            = 1;
const R_LAGER                   = 2;
const R_LOGIN                   = 3;
const R_VORBESTELLUNG           = 4;
const R_VARWAEHLEN              = 5;
const R_LAGERVAR                = 6;
const R_LOGIN_WUNSCHLISTE       = 7;
const R_MAXBESTELLMENGE         = 8;
const R_LOGIN_BEWERTUNG         = 9;
const R_LOGIN_TAG               = 10;
const R_LOGIN_NEWSCOMMENT       = 11;
const R_ARTIKELABNAHMEINTERVALL = 14;
const R_UNVERKAEUFLICH          = 15;
const R_AUFANFRAGE              = 16;
const R_EMPTY_TAG               = 17;
const R_EMPTY_VARIBOX           = 18;
const R_MISSING_TOKEN           = 19;
// Kategorietiefe
// 0 = Aus
// 1 = Tiefe 0 (Hauptkategorien)
// 2 = Tiefe 1
// 3 = Tiefe 2
const K_KATEGORIE_TIEFE = 3;
// url sep
const SEP_SEITE   = '_s';
const SEP_KAT     = ':';
const SEP_HST     = '::';
const SEP_MERKMAL = '__';
const SEP_MM_MMW  = '--';
// extract params seperator
const EXT_PARAMS_SEPERATORS_REGEX = '\&\?';
// JobQueue
defined('JOBQUEUE_LIMIT_JOBS') || define('JOBQUEUE_LIMIT_JOBS', 5);
defined('JOBQUEUE_LIMIT_M_EXPORTE') || define('JOBQUEUE_LIMIT_M_EXPORTE', 500);
defined('JOBQUEUE_LIMIT_M_NEWSLETTER') || define('JOBQUEUE_LIMIT_M_NEWSLETTER', 100);
defined('JOBQUEUE_LIMIT_M_STATUSEMAIL') || define('JOBQUEUE_LIMIT_M_STATUSEMAIL', 1);
defined('JOBQUEUE_LIMIT_M_SITEMAP_ITEMS') || define('JOBQUEUE_LIMIT_M_SITEMAP_ITEMS', 500);
defined('JOBQUEUE_LIMIT_IMAGE_CACHE_IMAGES') || define('JOBQUEUE_LIMIT_IMAGE_CACHE_IMAGES', 400);
defined('JOBQUEUE_LIMIT_M_XSELL') || define('JOBQUEUE_LIMIT_M_XSELL', 10000);
defined('JOBQUEUE_LIMIT_M_XSELL_ALL') || define('JOBQUEUE_LIMIT_M_XSELL_ALL', 50000);
// Exportformate
defined('EXPORTFORMAT_LIMIT_M') || define('EXPORTFORMAT_LIMIT_M', 2000);
defined('EXPORTFORMAT_ASYNC_LIMIT_M') || define('EXPORTFORMAT_ASYNC_LIMIT_M', 15);
// Special Exportformate
// Shop Template Logo Name
const SHOPLOGO_NAME = 'jtlshoplogo';
// Erweiterte Artikelübersicht Darstellung
const ERWDARSTELLUNG_ANSICHT_LISTE      = 1; // Standard
const ERWDARSTELLUNG_ANSICHT_GALERIE    = 2;
const ERWDARSTELLUNG_ANSICHT_ANZAHL_STD = 20; // Standard
// LastJobs
const LASTJOBS_INTERVALL             = 12; // Intervall in Stunden
const LASTJOBS_BEWERTUNGSERINNNERUNG = 1; // Bewertungserinnerungskey
const LASTJOBS_SITEMAP               = 2; // Sitemapkey
const LASTJOBS_RSS                   = 3; // RSSkey
const LASTJOBS_GARBAGECOLLECTOR      = 4; // GarbageCollector
const LASTJOBS_KATEGORIEUPDATE       = 5; // Kategorielevel update, nested set build
// Seitentypen
const PAGE_UNBEKANNT    = 0;
const PAGE_ARTIKEL      = 1; // Artikeldetails
const PAGE_ARTIKELLISTE = 2; // Artikelliste
const PAGE_WARENKORB    = 3; // Warenkorb
const PAGE_MEINKONTO    = 4; // Mein Konto
const PAGE_KONTAKT      = 5; // Kontakt
/**
 * @deprecated
 */
const PAGE_UMFRAGE        = 6; // Umfrage
const PAGE_NEWS           = 7; // News
const PAGE_NEWSLETTER     = 8; // Newsletter
const PAGE_LOGIN          = 9; // Login
const PAGE_REGISTRIERUNG  = 10; // Registrierung
const PAGE_BESTELLVORGANG = 11; // Bestellvorgang
const PAGE_BEWERTUNG      = 12; // Bewertung
/**
 * @deprecated
 */
const PAGE_DRUCKANSICHT      = 13; // Druckansicht
const PAGE_PASSWORTVERGESSEN = 14; // Passwort vergessen
const PAGE_WARTUNG           = 15; // Wartung
const PAGE_WUNSCHLISTE       = 16; // Wunschliste
const PAGE_VERGLEICHSLISTE   = 17; // Vergleichsliste
const PAGE_STARTSEITE        = 18; // Startseite
const PAGE_VERSAND           = 19; // Versand
const PAGE_AGB               = 20; // AGB
const PAGE_DATENSCHUTZ       = 21; // Datenschutz
/**
 * @deprecated
 */
const PAGE_TAGGING          = 22; // Tagging
const PAGE_LIVESUCHE        = 23; // Livesuche
const PAGE_HERSTELLER       = 24; // Hersteller
const PAGE_SITEMAP          = 25; // Sitemap
const PAGE_GRATISGESCHENK   = 26; // Gratis Geschenk
const PAGE_WRB              = 27; // WRB
const PAGE_PLUGIN           = 28; // Plugin
const PAGE_NEWSLETTERARCHIV = 29; // Newsletterarchiv
/**
 * @deprecated
 */
const PAGE_NEWSARCHIV       = 30; // Newsarchiv
const PAGE_EIGENE           = 31; // Eigene Seite
const PAGE_AUSWAHLASSISTENT = 32; // Auswahlassistent
const PAGE_BESTELLABSCHLUSS = 33; // Bestellabschluss
const PAGE_404              = 36;
const PAGE_IO               = 37;
const PAGE_BESTELLSTATUS    = 38;
const PAGE_MEDIA            = 39;
const PAGE_NEWSMONAT        = 40;
const PAGE_NEWSDETAIL       = 41;
const PAGE_NEWSKATEGORIE    = 42;

// Boxen
const BOX_CONTAINER              = 0;
const BOX_BESTSELLER             = 1;
const BOX_KATEGORIEN             = 2;
const BOX_VERGLEICHSLISTE        = 3;
const BOX_WUNSCHLISTE            = 4;
const BOX_LOGIN                  = 5;
const BOX_FINANZIERUNG           = 6;
const BOX_ZULETZT_ANGESEHEN      = 7;
const BOX_HERSTELLER             = 8;
const BOX_NEUE_IM_SORTIMENT      = 9;
const BOX_NEWS_KATEGORIEN        = 10;
const BOX_NEWS_AKTUELLER_MONAT   = 11;
const BOX_SCHNELLKAUF            = 12;
const BOX_SUCHWOLKE              = 13;
const BOX_SONDERANGEBOT          = 14;
const BOX_TOP_ANGEBOT            = 15;
const BOX_TOP_BEWERTET           = 16;
const BOX_IN_KUERZE_VERFUEGBAR   = 19;
const BOX_GLOBALE_MERKMALE       = 20;
const BOX_WARENKORB              = 21;
const BOX_LINKGRUPPE             = 23;
const BOX_FILTER_PREISSPANNE     = 25;
const BOX_FILTER_BEWERTUNG       = 26;
const BOX_FILTER_MERKMALE        = 27;
const BOX_FILTER_SUCHE           = 28;
const BOX_FILTER_SUCHSPECIAL     = 29;
const BOX_FILTER_HERSTELLER      = 101;
const BOX_FILTER_KATEGORIE       = 102;
const BOX_FILTER_AVAILABILITY    = 103;
const BOX_EIGENE_BOX_OHNE_RAHMEN = 30;
const BOX_EIGENE_BOX_MIT_RAHMEN  = 31;
const BOX_KONFIGURATOR           = 33;
// Kampagnentypen
const KAMPAGNE_DEF_HIT                    = 1;
const KAMPAGNE_DEF_VERKAUF                = 2;
const KAMPAGNE_DEF_ANMELDUNG              = 3;
const KAMPAGNE_DEF_VERKAUFSSUMME          = 4;
const KAMPAGNE_DEF_FRAGEZUMPRODUKT        = 5;
const KAMPAGNE_DEF_VERFUEGBARKEITSANFRAGE = 6;
const KAMPAGNE_DEF_LOGIN                  = 7;
const KAMPAGNE_DEF_WUNSCHLISTE            = 8;
const KAMPAGNE_DEF_WARENKORB              = 9;
const KAMPAGNE_DEF_NEWSLETTER             = 10;
// Interne Kampagnen
const KAMPAGNE_INTERN_VERFUEGBARKEIT        = 1;
const KAMPAGNE_INTERN_OEFFENTL_WUNSCHZETTEL = 2;
const KAMPAGNE_INTERN_GOOGLE                = 3;
// Backend Statistiktypen
const STATS_ADMIN_TYPE_BESUCHER        = 1;
const STATS_ADMIN_TYPE_KUNDENHERKUNFT  = 2;
const STATS_ADMIN_TYPE_SUCHMASCHINE    = 3;
const STATS_ADMIN_TYPE_UMSATZ          = 4;
const STATS_ADMIN_TYPE_EINSTIEGSSEITEN = 5;
const STATS_ADMIN_TYPE_CONSENT         = 6;
// Newsletter URL_SHOP Parsevariable für Bilder in der Standardvorlage
const NEWSLETTER_STD_VORLAGE_URLSHOP = '$#URL_SHOP#$';
// CheckBox
const CHECKBOX_ORT_REGISTRIERUNG        = 1;
const CHECKBOX_ORT_BESTELLABSCHLUSS     = 2;
const CHECKBOX_ORT_NEWSLETTERANMELDUNG  = 3;
const CHECKBOX_ORT_KUNDENDATENEDITIEREN = 4;
const CHECKBOX_ORT_KONTAKT              = 5;
const CHECKBOX_ORT_FRAGE_ZUM_PRODUKT    = 6;
const CHECKBOX_ORT_FRAGE_VERFUEGBARKEIT = 7;
// JTLLOG Levels
const JTLLOG_LEVEL_EMERGENCY = 600;
const JTLLOG_LEVEL_ALERT     = 550;
const JTLLOG_LEVEL_CRITICAL  = 500;
const JTLLOG_LEVEL_ERROR     = 400;
const JTLLOG_LEVEL_WARNING   = 300;
const JTLLOG_LEVEL_NOTICE    = 250;
const JTLLOG_LEVEL_INFO      = 200;
const JTLLOG_LEVEL_DEBUG     = 100;
// JTL Trennzeichen
/**
 * @deprecated
 * @since 5.4.0
 */
const JTLSEPARATER_WEIGHT = 1;
/**
 * @deprecated
 * @since 5.4.0
 */
const JTLSEPARATER_LENGTH = 2;
/**
 * @deprecated
 * @since 5.4.0
 */
const JTLSEPARATER_AMOUNT  = 3;
const JTL_SEPARATOR_WEIGHT = 1;
const JTL_SEPARATOR_LENGTH = 2;
const JTL_SEPARATOR_AMOUNT = 3;
// JTL Support Email
const JTLSUPPORT_EMAIL = 'support@jtl-software.de';
// Globale Arten von generierte Nummern (z.b. Bestellnummer)
const JTL_GENNUMBER_ORDERNUMBER = 1;
// JTL URLS
const JTLURL_BASE            = 'https://ext.jtl-software.de/';
const JTLURL_HP              = 'https://www.jtl-software.de/';
const JTLURL_GET_SHOPNEWS    = 'https://feed.jtl-software.de/websitenews';
const JTLURL_GET_SHOPPATCH   = JTLURL_BASE . 'json_patch.php';
const JTLURL_GET_SHOPHELP    = JTLURL_BASE . 'jtlhelp.php';
const JTLURL_GET_SHOPVERSION = JTLURL_BASE . 'json_version.php';
// Log-Levels
const LOGLEVEL_ERROR  = 1;
const LOGLEVEL_NOTICE = 2;
const LOGLEVEL_DEBUG  = 3;
// Auswahlassistent
const AUSWAHLASSISTENT_ORT_STARTSEITE = 'kStartseite';
const AUSWAHLASSISTENT_ORT_KATEGORIE  = 'kKategorie';
const AUSWAHLASSISTENT_ORT_LINK       = 'kLink';
// Upload
const UPLOAD_TYP_KUNDE         = 1;
const UPLOAD_TYP_BESTELLUNG    = 2;
const UPLOAD_TYP_WARENKORBPOS  = 3;
const UPLOAD_ERROR_NEED_UPLOAD = 12;
const UPLOAD_CHECK_NEED_UPLOAD = 13;
// Template
const TEMPLATE_XML = 'template.xml';
// Seo
const SHOP_SEO = true;
// Sessionspeicherung 1 => DB, sonst => Dateien
// Max Anzahl an Variationswerten für Warenkorbmatrix

const BROWSER_UNKNOWN  = 0;
const BROWSER_MSIE     = 1;
const BROWSER_FIREFOX  = 2;
const BROWSER_CHROME   = 3;
const BROWSER_SAFARI   = 4;
const BROWSER_OPERA    = 5;
const BROWSER_NETSCAPE = 6;

const FREQ_ALWAYS  = 'always';
const FREQ_HOURLY  = 'hourly';
const FREQ_DAILY   = 'daily';
const FREQ_WEEKLY  = 'weekly';
const FREQ_MONTHLY = 'monthly';
const FREQ_YEARLY  = 'yearly';
const FREQ_NEVER   = 'never';

const PRIO_VERYHIGH = '1.0';
const PRIO_HIGH     = '0.7';
const PRIO_NORMAL   = '0.5';
const PRIO_LOW      = '0.3';
const PRIO_VERYLOW  = '0.0';

const SPM_PORT    = 443;
const SPM_TIMEOUT = 30;

const CACHING_GROUP_ARTICLE               = 'art';
const CACHING_GROUP_PRODUCT               = 'art';
const CACHING_GROUP_CATEGORY              = 'cat';
const CACHING_GROUP_SHIPPING              = 'ship';
const CACHING_GROUP_LANGUAGE              = 'lang';
const CACHING_GROUP_TEMPLATE              = 'tpl';
const CACHING_GROUP_OPTION                = 'opt';
const CACHING_GROUP_PLUGIN                = 'plgn';
const CACHING_GROUP_CORE                  = 'core';
const CACHING_GROUP_LICENSES              = 'lic';
const CACHING_GROUP_RECOMMENDATIONS       = 'rec';
const CACHING_GROUP_OBJECT                = 'obj';
const CACHING_GROUP_BOX                   = 'bx';
const CACHING_GROUP_NEWS                  = 'nws';
const CACHING_GROUP_ATTRIBUTE             = 'attr';
const CACHING_GROUP_MANUFACTURER          = 'mnf';
const CACHING_GROUP_FILTER                = 'fltr';
const CACHING_GROUP_FILTER_CHARACTERISTIC = 'fltrchr';
const CACHING_GROUP_STATUS                = 'status';
const CACHING_GROUP_OPC                   = 'opc';

const QUERY_PARAM_CONFIG_ITEM           = 'ek';
const QUERY_PARAM_SEARCH_SPECIAL        = 'q';
const QUERY_PARAM_SEARCH_SPECIAL_FILTER = 'qf';
const QUERY_PARAM_PRODUCT               = 'a';
const QUERY_PARAM_PRODUCTS_PER_PAGE     = 'af';
const QUERY_PARAM_CHILD_PRODUCT         = 'a2';
const QUERY_PARAM_CATEGORY              = 'k';
const QUERY_PARAM_CATEGORY_FILTER       = 'kf';
const QUERY_PARAM_AVAILABILITY          = 'availability';
const QUERY_PARAM_MANUFACTURER          = 'h';
const QUERY_PARAM_MANUFACTURER_FILTER   = 'hf';
const QUERY_PARAM_PRICE_FILTER          = 'pf';
const QUERY_PARAM_RATING_FILTER         = 'bf';
const QUERY_PARAM_SEARCH_FILTER         = 'sf';
const QUERY_PARAM_CHARACTERISTIC_VALUE  = 'm';
const QUERY_PARAM_CHARACTERISTIC_FILTER = 'mf';
const QUERY_PARAM_DUMMY                 = 'ds';
const QUERY_PARAM_SEARCH                = 'suche';
const QUERY_PARAM_SEARCH_QUERY          = 'qs';
const QUERY_PARAM_SEARCH_TERM           = 'suchausdruck';
const QUERY_PARAM_LINK                  = 's';
const QUERY_PARAM_PAGE                  = 's'; // yes, it's the same as QUERY_PARAM_LINK
const QUERY_PARAM_SEARCH_QUERY_ID       = 'l';
const QUERY_PARAM_NEWS_ITEM             = 'n';
const QUERY_PARAM_NEWS_OVERVIEW         = 'nm';
const QUERY_PARAM_NEWS_CATEGORY         = 'nk';
const QUERY_PARAM_VIEW_MODE             = 'ed';
const QUERY_PARAM_SORT                  = 'Sortierung';
const QUERY_PARAM_SHOW                  = 'show';
const QUERY_PARAM_COMPARELIST           = 'vla';
const QUERY_PARAM_STARS                 = 'nSterne';
const QUERY_PARAM_QTY                   = 'nAnzahl';
const QUERY_PARAM_DATE                  = 'cDatum';
const QUERY_PARAM_OPTIN_CODE            = 'oc';
const QUERY_PARAM_COMPARELIST_PRODUCT   = 'vlplo';

const ADMINGROUP                          = 1;
const MAX_LOGIN_ATTEMPTS                  = 3;
const LOCK_TIME                           = 5;
const SHIPPING_CLASS_MAX_VALIDATION_COUNT = 10;
