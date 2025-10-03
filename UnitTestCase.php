<?php

declare(strict_types=1);

namespace Tests\Unit;

use Exception;
use JTL\Cache\JTLCacheInterface;
use JTL\Catalog\Currency;
use JTL\DB\DbInterface;
use JTL\DB\NiceDB;
use JTL\Language\LanguageModel;
use JTL\Shop;
use JTL\Shopsetting;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;
use Tests\Unit\TestHelper\TestLogger;

/**
 * Class UnitTestCase
 * @package Tests
 */
class UnitTestCase extends TestCase
{
    private array $sessionState = [];

    private static array $shopSettings = [
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'systemlog_flag',
            'cWert'                 => '100',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'admin_login_logger_mode',
            'cWert'                 => '1',
            'type'                  => 'listbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_wizard_done',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'configgroup_1_routing',
            'cWert'                 => 'Routing',
            'type'                  => ''
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'routing_scheme',
            'cWert'                 => 'F',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'routing_default_language',
            'cWert'                 => 'F',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'artikel_artikelanzeigefilter',
            'cWert'                 => '1',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'artikel_artikelanzeigefilter_seo',
            'cWert'                 => 'seo',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'artikel_lagerampel_gruen',
            'cWert'                 => '5',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'artikel_lagerampel_rot',
            'cWert'                 => '0',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'artikel_ampel_lagernull_gruen',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_preis0',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'artikeldetails_variationswertlager',
            'cWert'                 => '2',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_versandfrei_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_warenkorb_weiterleitung',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_lieferverzoegerung_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_versandkostenfrei_darstellung',
            'cWert'                 => 'M',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_bestseller_minanzahl',
            'cWert'                 => '10',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_bestseller_tage',
            'cWert'                 => '90',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'kategorien_anzeigefilter',
            'cWert'                 => '1',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_shopname',
            'cWert'                 => 'shop522',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'artikel_lagerampel_keinlager',
            'cWert'                 => '2',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_sichtbarkeit',
            'cWert'                 => '1',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_kundenkonto_aktiv',
            'cWert'                 => 'S',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'kaufabwicklung_ssl_nutzen',
            'cWert'                 => 'P',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_versandhinweis',
            'cWert'                 => 'zzgl',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_erscheinende_kaeuflich',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_cancellation_time',
            'cWert'                 => '999',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_rma_enabled',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_zaehler_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_dezimaltrennzeichen_sonstigeangaben',
            'cWert'                 => ',',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'garbagecollector_wawiabgleich',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_merkmalwert_url_indexierung',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'redirect_save_404',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_ust_auszeichnung',
            'cWert'                 => 'auto',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_fusszeilehinweis',
            'cWert'                 => '',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_steuerpos_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_show_shipto_dropdown',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'consistent_gross_prices',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'routing_duplicates',
            'cWert'                 => 'F',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_wunschliste_weiterleitung',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_wunschliste_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_wunschliste_freunde_aktiv',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_wunschliste_max_email',
            'cWert'                 => '11',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_wunschliste_artikel_loeschen_nach_kauf',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'wartungsmodus_aktiviert',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_versandermittlung_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_versandermittlung_lieferdauer_anzeigen',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_cookie_lifetime',
            'cWert'                 => '0',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_cookie_path',
            'cWert'                 => '',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_cookie_domain',
            'cWert'                 => '',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_cookie_secure',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_cookie_httponly',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '1',
            'cName'                 => 'global_cookie_samesite',
            'cWert'                 => 'S',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '2',
            'cName'                 => 'startseite_neuimsortiment_anzahl',
            'cWert'                 => '10',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '2',
            'cName'                 => 'startseite_neuimsortiment_sortnr',
            'cWert'                 => '1',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '2',
            'cName'                 => 'startseite_topangebote_anzahl',
            'cWert'                 => '10',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '2',
            'cName'                 => 'startseite_topangebote_sortnr',
            'cWert'                 => '2',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '2',
            'cName'                 => 'startseite_sonderangebote_anzahl',
            'cWert'                 => '10',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '2',
            'cName'                 => 'startseite_sonderangebote_sortnr',
            'cWert'                 => '3',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '2',
            'cName'                 => 'startseite_bestseller_anzahl',
            'cWert'                 => '10',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '2',
            'cName'                 => 'startseite_bestseller_sortnr',
            'cWert'                 => '5',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '3',
            'cName'                 => 'email_methode',
            'cWert'                 => 'smtp',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '3',
            'cName'                 => 'email_sendmail_pfad',
            'cWert'                 => '',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '3',
            'cName'                 => 'email_smtp_hostname',
            'cWert'                 => 'smtp4dev',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '3',
            'cName'                 => 'email_smtp_port',
            'cWert'                 => '0',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '3',
            'cName'                 => 'email_smtp_auth',
            'cWert'                 => '1',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '3',
            'cName'                 => 'email_smtp_user',
            'cWert'                 => 'admin',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '3',
            'cName'                 => 'email_smtp_pass',
            'cWert'                 => 'admin',
            'type'                  => 'pass'
        ],
        [
            'kEinstellungenSektion' => '3',
            'cName'                 => 'email_smtp_verschluesselung',
            'cWert'                 => '',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '3',
            'cName'                 => 'email_master_absender',
            'cWert'                 => 'max@mustermann.de',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '3',
            'cName'                 => 'email_master_absender_name',
            'cWert'                 => 'Tim Niko Tegtmeyer',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '3',
            'cName'                 => 'email_send_immediately',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'suche_fulltext',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'suche_prio_name',
            'cWert'                 => '10',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'suche_prio_suchbegriffe',
            'cWert'                 => '9',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'suche_prio_artikelnummer',
            'cWert'                 => '8',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'suche_prio_kurzbeschreibung',
            'cWert'                 => '7',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'suche_prio_beschreibung',
            'cWert'                 => '6',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'suche_prio_ean',
            'cWert'                 => '5',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'suche_prio_isbn',
            'cWert'                 => '4',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'suche_prio_han',
            'cWert'                 => '3',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'suche_prio_anmerkung',
            'cWert'                 => '0',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'suche_min_zeichen',
            'cWert'                 => '4',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'suche_sortierprio_name',
            'cWert'                 => '10',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'suche_sortierprio_name_ab',
            'cWert'                 => '9',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'suche_sortierprio_preis',
            'cWert'                 => '8',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'suche_sortierprio_preis_ab',
            'cWert'                 => '7',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'suche_sortierprio_ean',
            'cWert'                 => '1',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'suche_sortierprio_erstelldatum',
            'cWert'                 => '3',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'suche_sortierprio_artikelnummer',
            'cWert'                 => '4',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'suche_sortierprio_gewicht',
            'cWert'                 => '5',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'suche_sortierprio_erscheinungsdatum',
            'cWert'                 => '4',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'suche_sortierprio_bestseller',
            'cWert'                 => '1',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'suche_sortierprio_bewertung',
            'cWert'                 => '2',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'suche_max_treffer',
            'cWert'                 => '300',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'suche_ajax_anzahl',
            'cWert'                 => '5',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'artikeluebersicht_lagerbestandsanzeige',
            'cWert'                 => 'ampel',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'artikeluebersicht_kurzbeschreibung_anzeigen',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'artikeluebersicht_lagerbestandanzeige_anzeigen',
            'cWert'                 => 'U',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'artikeluebersicht_hersteller_anzeigen',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'artikeluebersicht_artikelproseite',
            'cWert'                 => '15',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'artikeluebersicht_artikelsortierung',
            'cWert'                 => '100',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'artikeluebersicht_max_seitenzahl',
            'cWert'                 => '9',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'artikeluebersicht_rabattanzeige',
            'cWert'                 => '1',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'artikeluebersicht_sonderpreisanzeige',
            'cWert'                 => '1',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'articleoverview_pricerange_width',
            'cWert'                 => '150',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'topbest_anzeigen',
            'cWert'                 => 'TopBest',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'artikelubersicht_topbest_anzahl',
            'cWert'                 => '6',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'artikelubersicht_bestseller_gruppieren',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'artikeluebersicht_bestseller_anzahl',
            'cWert'                 => '3',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'artikeluebersicht_gewicht_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'artikeluebersicht_artikelgewicht_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'artikeluebersicht_vergleichsliste_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'artikeluebersicht_wunschzettel_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'artikeluebersicht_artikelintervall_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'livesuche_max_ip_count',
            'cWert'                 => '5',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'suchfilter_anzeigen_ab',
            'cWert'                 => '0',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'artikeluebersicht_erw_darstellung',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'artikeluebersicht_erw_darstellung_stdansicht',
            'cWert'                 => '2',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'products_per_page_list',
            'cWert'                 => '10,20,30,40,50',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '4',
            'cName'                 => 'products_per_page_gallery',
            'cWert'                 => '10,20,30,40,50',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_fragezumprodukt_anzeigen',
            'cWert'                 => 'P',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_fragezumprodukt_email',
            'cWert'                 => '',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'produktfrage_abfragen_anrede',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'produktfrage_abfragen_vorname',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'produktfrage_abfragen_nachname',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'produktfrage_abfragen_firma',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'produktfrage_abfragen_tel',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'produktfrage_abfragen_fax',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'produktfrage_abfragen_mobil',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'produktfrage_kopiekunde',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'produktfrage_sperre_minuten',
            'cWert'                 => '1',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'produktfrage_abfragen_captcha',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'benachrichtigung_nutzen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'benachrichtigung_abfragen_vorname',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'benachrichtigung_abfragen_nachname',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'benachrichtigung_sperre_minuten',
            'cWert'                 => '2',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'benachrichtigung_abfragen_captcha',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'benachrichtigung_min_lagernd',
            'cWert'                 => '0',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_xselling_standard_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_xselling_kauf_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_xselling_kauf_anzahl',
            'cWert'                 => '6',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_xselling_kauf_parent',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikel_variationspreisanzeige',
            'cWert'                 => '1',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikel_lagerbestandsanzeige',
            'cWert'                 => 'ampel',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_lieferantenbestand_anzeigen',
            'cWert'                 => 'U',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_kurzbeschreibung_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_uvp_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_hersteller_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_lieferstatus_anzeigen',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_artikelintervall_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikel_weitere_artikel_hersteller_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_rabattanzeige',
            'cWert'                 => '4',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_sonderpreisanzeige',
            'cWert'                 => '2',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_kategorie_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_tabs_nutzen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_navi_blaettern',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'sie_sparen_x_anzeigen',
            'cWert'                 => '1',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_canonicalurl_varkombikind',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_warenkorbmatrix_anzeige',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_warenkorbmatrix_anzeigeformat',
            'cWert'                 => 'Q',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_warenkorbmatrix_lagerbeachten',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'show_shelf_life_expiration_date',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'isbn_display',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'adr_hazard_display',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'gtin_display',
            'cWert'                 => 'always',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'upload_modul_limit',
            'cWert'                 => '11',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_vergleichsliste_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'mediendatei_anzeigen',
            'cWert'                 => 'YM',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_aehnlicheartikel_anzahl',
            'cWert'                 => '10',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_stueckliste_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_produktbundle_nutzen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'merkmale_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_gewicht_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_inhalt_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_artikelgewicht_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_abmessungen_anzeigen',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '5',
            'cName'                 => 'artikeldetails_attribute_anhaengen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'configgroup_6_forgot_password',
            'cWert'                 => 'Passwort vergessen',
            'type'                  => ''
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'kundenregistrierung_abfragen_titel',
            'cWert'                 => 'O',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'kundenregistrierung_abfragen_anrede',
            'cWert'                 => 'O',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'kundenregistrierung_pflicht_vorname',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'kundenregistrierung_abfragen_firma',
            'cWert'                 => 'O',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'kundenregistrierung_abfragen_firmazusatz',
            'cWert'                 => 'O',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'kundenregistrierung_abfragen_adresszusatz',
            'cWert'                 => 'O',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'kundenregistrierung_abfragen_mobil',
            'cWert'                 => 'O',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'kundenregistrierung_abfragen_fax',
            'cWert'                 => 'O',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'kundenregistrierung_abfragen_tel',
            'cWert'                 => 'O',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'kundenregistrierung_abfragen_www',
            'cWert'                 => 'O',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'kundenregistrierung_abfragen_geburtstag',
            'cWert'                 => 'O',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'kundenregistrierung_passwortlaenge',
            'cWert'                 => '8',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'kundenregistrierung_standardland',
            'cWert'                 => 'DE',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'kundenregistrierung_abfragen_bundesland',
            'cWert'                 => 'O',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'kundenregistrierung_pruefen_ort',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'kundenregistrierung_pruefen_name',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'kundenregistrierung_pruefen_zeit',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'kundenregistrierung_pruefen_email',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'kundenregistrierung_nur_lieferlaender',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'kundenregistrierung_abgleichen_plz',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'registrieren_captcha',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'kundenlogin_max_loginversuche',
            'cWert'                 => '0',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'direct_advertising',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'lieferadresse_abfragen_anrede',
            'cWert'                 => 'O',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'lieferadresse_abfragen_titel',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'lieferadresse_abfragen_firma',
            'cWert'                 => 'O',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'lieferadresse_abfragen_firmazusatz',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'lieferadresse_abfragen_adresszusatz',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'lieferadresse_abfragen_bundesland',
            'cWert'                 => 'O',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'lieferadresse_abfragen_tel',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'lieferadresse_abfragen_email',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'lieferadresse_abfragen_mobil',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'lieferadresse_abfragen_fax',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'lieferadresse_abfragen_standardland',
            'cWert'                 => 'DE',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'shop_ustid',
            'cWert'                 => 'XXXXXXXXX',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'kundenregistrierung_abfragen_ustid',
            'cWert'                 => 'O',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'shop_ustid_bzstpruefung',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'shop_ustid_force_remote_check',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '6',
            'cName'                 => 'forgot_password_captcha',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '7',
            'cName'                 => 'warenkorb_produktbilder_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '7',
            'cName'                 => 'warenkorb_versandermittlung_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '7',
            'cName'                 => 'warenkorb_kupon_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '7',
            'cName'                 => 'warenkorb_xselling_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '7',
            'cName'                 => 'warenkorb_xselling_anzahl',
            'cWert'                 => '3',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '7',
            'cName'                 => 'warenkorb_varianten_varikombi_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '7',
            'cName'                 => 'warenkorb_gesamtgewicht_anzeigen',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '7',
            'cName'                 => 'warenkorb_warenkorb2pers_merge',
            'cWert'                 => 'P',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '7',
            'cName'                 => 'warenkorbpers_nutzen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '7',
            'cName'                 => 'general_child_item_bulk_pricing',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '7',
            'cName'                 => 'bestellvorgang_kaufabwicklungsmethode',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '7',
            'cName'                 => 'bestellvorgang_wrb_anzeigen',
            'cWert'                 => '1',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '7',
            'cName'                 => 'bestellvorgang_einzelpreise_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '7',
            'cName'                 => 'bestellvorgang_lieferstatus_anzeigen',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '7',
            'cName'                 => 'bestellvorgang_unregistriert',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '7',
            'cName'                 => 'bestellvorgang_unregneukundenkupon_zulassen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '7',
            'cName'                 => 'bestellvorgang_versand_steuersatz',
            'cWert'                 => 'HS',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '7',
            'cName'                 => 'bestellvorgang_artikelmerkmale',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '7',
            'cName'                 => 'bestellvorgang_artikelattribute',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '7',
            'cName'                 => 'bestellvorgang_artikelkurzbeschreibung',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '7',
            'cName'                 => 'bestellvorgang_partlist',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '7',
            'cName'                 => 'bestellabschluss_bestellnummer_praefix',
            'cWert'                 => '',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '7',
            'cName'                 => 'bestellabschluss_bestellnummer_anfangsnummer',
            'cWert'                 => '0',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '7',
            'cName'                 => 'bestellabschluss_bestellnummer_suffix',
            'cWert'                 => '',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '7',
            'cName'                 => 'bestellabschluss_runden5',
            'cWert'                 => '0',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '7',
            'cName'                 => 'bestellabschluss_spamschutz_nutzen',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '7',
            'cName'                 => 'bestellabschluss_abschlussseite',
            'cWert'                 => 'S',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '8',
            'cName'                 => 'box_bestseller_anzahl_anzeige',
            'cWert'                 => '3',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '8',
            'cName'                 => 'box_bestseller_anzahl_basis',
            'cWert'                 => '10',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '8',
            'cName'                 => 'box_sonderangebote_anzahl_anzeige',
            'cWert'                 => '2',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '8',
            'cName'                 => 'box_neuimsortiment_anzahl_anzeige',
            'cWert'                 => '3',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '8',
            'cName'                 => 'box_neuimsortiment_alter_tage',
            'cWert'                 => '31',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '8',
            'cName'                 => 'box_topangebot_anzahl_anzeige',
            'cWert'                 => '3',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '8',
            'cName'                 => 'box_erscheinende_anzahl_anzeige',
            'cWert'                 => '3',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '8',
            'cName'                 => 'box_zuletztangesehen_anzahl',
            'cWert'                 => '3',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '8',
            'cName'                 => 'boxen_topbewertet_anzahl',
            'cWert'                 => '3',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '8',
            'cName'                 => 'boxen_topbewertet_basisanzahl',
            'cWert'                 => '20',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '8',
            'cName'                 => 'boxen_topbewertet_minsterne',
            'cWert'                 => '3',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '8',
            'cName'                 => 'boxen_livesuche_count',
            'cWert'                 => '10',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '8',
            'cName'                 => 'box_erscheinende_anzahl_basis',
            'cWert'                 => '10',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '8',
            'cName'                 => 'box_sonderangebote_anzahl_basis',
            'cWert'                 => '10',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '8',
            'cName'                 => 'box_neuimsortiment_anzahl_basis',
            'cWert'                 => '10',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '8',
            'cName'                 => 'box_topangebot_anzahl_basis',
            'cWert'                 => '10',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '8',
            'cName'                 => 'configgroup_8_box_manufacturers',
            'cWert'                 => 'Hersteller',
            'type'                  => ''
        ],
        [
            'kEinstellungenSektion' => '8',
            'cName'                 => 'box_hersteller_anzahl_anzeige',
            'cWert'                 => '20',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '8',
            'cName'                 => 'boxen_wunschzettel_anzahl',
            'cWert'                 => '3',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '8',
            'cName'                 => 'boxen_wunschzettel_bilder',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '8',
            'cName'                 => 'boxen_vergleichsliste_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_hersteller_mini_breite',
            'cWert'                 => '120',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_hersteller_mini_hoehe',
            'cWert'                 => '40',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_hersteller_gross_breite',
            'cWert'                 => '1800',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_hersteller_gross_hoehe',
            'cWert'                 => '600',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_merkmal_mini_breite',
            'cWert'                 => '120',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_merkmal_mini_hoehe',
            'cWert'                 => '40',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_merkmal_gross_breite',
            'cWert'                 => '1800',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_merkmal_gross_hoehe',
            'cWert'                 => '600',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_merkmalwert_mini_breite',
            'cWert'                 => '40',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_merkmalwert_mini_hoehe',
            'cWert'                 => '40',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_merkmalwert_gross_breite',
            'cWert'                 => '1800',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_merkmalwert_gross_hoehe',
            'cWert'                 => '600',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_konfiggruppe_mini_breite',
            'cWert'                 => '120',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_konfiggruppe_mini_hoehe',
            'cWert'                 => '40',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_konfiggruppe_normal_breite',
            'cWert'                 => '1200',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_konfiggruppe_normal_hoehe',
            'cWert'                 => '400',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_konfiggruppe_gross_breite',
            'cWert'                 => '1800',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_konfiggruppe_gross_hoehe',
            'cWert'                 => '600',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_kategorien_mini_breite',
            'cWert'                 => '120',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_kategorien_mini_hoehe',
            'cWert'                 => '40',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_kategorien_gross_breite',
            'cWert'                 => '1800',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_kategorien_gross_hoehe',
            'cWert'                 => '600',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_kategorien_klein_breite',
            'cWert'                 => '600',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_kategorien_klein_hoehe',
            'cWert'                 => '200',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_variationen_klein_breite',
            'cWert'                 => '200',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_variationen_klein_hoehe',
            'cWert'                 => '200',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_kategorien_breite',
            'cWert'                 => '1200',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_kategorien_hoehe',
            'cWert'                 => '400',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_variationen_gross_breite',
            'cWert'                 => '1200',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_variationen_gross_hoehe',
            'cWert'                 => '1200',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_variationen_breite',
            'cWert'                 => '600',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_variationen_hoehe',
            'cWert'                 => '600',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_variationen_mini_breite',
            'cWert'                 => '40',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_variationen_mini_hoehe',
            'cWert'                 => '40',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_artikel_gross_breite',
            'cWert'                 => '1200',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_artikel_gross_hoehe',
            'cWert'                 => '8001200',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_artikel_normal_breite',
            'cWert'                 => '600',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_artikel_normal_hoehe',
            'cWert'                 => '600',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_artikel_klein_breite',
            'cWert'                 => '200',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_artikel_klein_hoehe',
            'cWert'                 => '200',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_artikel_mini_breite',
            'cWert'                 => '40',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_artikel_mini_hoehe',
            'cWert'                 => '40',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_hersteller_normal_breite',
            'cWert'                 => '1200',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_hersteller_normal_hoehe',
            'cWert'                 => '400',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_hersteller_klein_breite',
            'cWert'                 => '600',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_hersteller_klein_hoehe',
            'cWert'                 => '200',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_merkmal_normal_breite',
            'cWert'                 => '1200',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_merkmal_normal_hoehe',
            'cWert'                 => '400',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_merkmal_klein_breite',
            'cWert'                 => '600',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_merkmal_klein_hoehe',
            'cWert'                 => '200',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_merkmalwert_normal_breite',
            'cWert'                 => '1200',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_merkmalwert_normal_hoehe',
            'cWert'                 => '400',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_konfiggruppe_klein_breite',
            'cWert'                 => '600',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_merkmalwert_klein_breite',
            'cWert'                 => '150',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_konfiggruppe_klein_hoehe',
            'cWert'                 => '200',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_merkmalwert_klein_hoehe',
            'cWert'                 => '150',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_opc_mini_breite',
            'cWert'                 => '480',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_opc_mini_hoehe',
            'cWert'                 => '480',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_opc_klein_breite',
            'cWert'                 => '720',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_opc_klein_hoehe',
            'cWert'                 => '720',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_opc_normal_breite',
            'cWert'                 => '1080',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_opc_normal_hoehe',
            'cWert'                 => '1080',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_opc_gross_breite',
            'cWert'                 => '1440',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_opc_gross_hoehe',
            'cWert'                 => '1440',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_news_mini_breite',
            'cWert'                 => '120',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_newskategorie_mini_breite',
            'cWert'                 => '120',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_news_mini_hoehe',
            'cWert'                 => '40',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_newskategorie_mini_hoehe',
            'cWert'                 => '40',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_news_klein_breite',
            'cWert'                 => '600',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_newskategorie_klein_breite',
            'cWert'                 => '600',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_news_klein_hoehe',
            'cWert'                 => '200',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_newskategorie_klein_hoehe',
            'cWert'                 => '200',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_news_normal_breite',
            'cWert'                 => '1200',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_newskategorie_normal_breite',
            'cWert'                 => '1200',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_news_normal_hoehe',
            'cWert'                 => '400',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_newskategorie_normal_hoehe',
            'cWert'                 => '400',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_news_gross_breite',
            'cWert'                 => '1800',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_newskategorie_gross_breite',
            'cWert'                 => '1800',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_news_gross_hoehe',
            'cWert'                 => '600',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_newskategorie_gross_hoehe',
            'cWert'                 => '600',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_jpg_quali',
            'cWert'                 => '75',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_artikel_namen',
            'cWert'                 => '2',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_kategorie_namen',
            'cWert'                 => '1',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_variation_namen',
            'cWert'                 => '3',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_hersteller_namen',
            'cWert'                 => '1',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_merkmal_namen',
            'cWert'                 => '1',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_merkmalwert_namen',
            'cWert'                 => '1',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_externe_bildschnittstelle',
            'cWert'                 => 'A',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_dateiformat',
            'cWert'                 => 'AUTO',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'container_verwenden',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '9',
            'cName'                 => 'bilder_hintergrundfarbe',
            'cWert'                 => 'rgb(255, 255, 255)',
            'type'                  => 'color'
        ],
        [
            'kEinstellungenSektion' => '10',
            'cName'                 => 'sonstiges_livesuche_all_top_count',
            'cWert'                 => '3',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '10',
            'cName'                 => 'sonstiges_livesuche_all_last_count',
            'cWert'                 => '50',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '10',
            'cName'                 => 'sonstiges_gratisgeschenk_checkout_hinweis_anzeigen',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '10',
            'cName'                 => 'sonstiges_gratisgeschenk_nutzen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '10',
            'cName'                 => 'sonstiges_gratisgeschenk_anzahl',
            'cWert'                 => '30',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '10',
            'cName'                 => 'sonstiges_gratisgeschenk_sortierung',
            'cWert'                 => 'B',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '10',
            'cName'                 => 'sonstiges_gratisgeschenk_wk_hinweis_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '10',
            'cName'                 => 'sonstiges_gratisgeschenk_noch_nicht_verfuegbar_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '100',
            'cName'                 => 'zahlungsart_nachnahme_min_bestellungen',
            'cWert'                 => '0',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '100',
            'cName'                 => 'zahlungsart_lastschrift_max',
            'cWert'                 => '0',
            'type'                  => 'kommazahl'
        ],
        [
            'kEinstellungenSektion' => '100',
            'cName'                 => 'zahlungsart_lastschrift_min',
            'cWert'                 => '0',
            'type'                  => 'kommazahl'
        ],
        [
            'kEinstellungenSektion' => '100',
            'cName'                 => 'zahlungsart_lastschrift_min_bestellungen',
            'cWert'                 => '0',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '100',
            'cName'                 => 'zahlungsart_rechnung_max',
            'cWert'                 => '0',
            'type'                  => 'kommazahl'
        ],
        [
            'kEinstellungenSektion' => '100',
            'cName'                 => 'zahlungsart_rechnung_min',
            'cWert'                 => '0',
            'type'                  => 'kommazahl'
        ],
        [
            'kEinstellungenSektion' => '100',
            'cName'                 => 'zahlungsart_rechnung_min_bestellungen',
            'cWert'                 => '0',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '100',
            'cName'                 => 'zahlungsart_nachnahme_max',
            'cWert'                 => '0',
            'type'                  => 'kommazahl'
        ],
        [
            'kEinstellungenSektion' => '100',
            'cName'                 => 'zahlungsart_ueberweisung_min_bestellungen',
            'cWert'                 => '0',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '100',
            'cName'                 => 'zahlungsart_ueberweisung_min',
            'cWert'                 => '0',
            'type'                  => 'kommazahl'
        ],
        [
            'kEinstellungenSektion' => '100',
            'cName'                 => 'zahlungsart_ueberweisung_max',
            'cWert'                 => '0',
            'type'                  => 'kommazahl'
        ],
        [
            'kEinstellungenSektion' => '100',
            'cName'                 => 'zahlungsart_nachnahme_min',
            'cWert'                 => '0',
            'type'                  => 'kommazahl'
        ],
        [
            'kEinstellungenSektion' => '100',
            'cName'                 => 'zahlungsart_lastschrift_bic_abfrage',
            'cWert'                 => 'O',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '100',
            'cName'                 => 'zahlungsart_lastschrift_kontoinhaber_abfrage',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '100',
            'cName'                 => 'zahlungsart_lastschrift_kreditinstitut_abfrage',
            'cWert'                 => 'O',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '101',
            'cName'                 => 'exportformate_preis_ueber_null',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '101',
            'cName'                 => 'exportformate_lager_ueber_null',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '101',
            'cName'                 => 'exportformate_lieferland',
            'cWert'                 => '',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '101',
            'cName'                 => 'exportformate_beschreibung',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '101',
            'cName'                 => 'exportformate_line_ending',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '101',
            'cName'                 => 'exportformate_quot',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '101',
            'cName'                 => 'exportformate_equot',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '101',
            'cName'                 => 'exportformate_semikolon',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '102',
            'cName'                 => 'kontakt_abfragen_anrede',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '102',
            'cName'                 => 'kontakt_abfragen_vorname',
            'cWert'                 => 'O',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '102',
            'cName'                 => 'kontakt_abfragen_nachname',
            'cWert'                 => 'O',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '102',
            'cName'                 => 'kontakt_abfragen_firma',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '102',
            'cName'                 => 'kontakt_abfragen_tel',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '102',
            'cName'                 => 'kontakt_abfragen_fax',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '102',
            'cName'                 => 'kontakt_abfragen_mobil',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '102',
            'cName'                 => 'kontakt_kopiekunde',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '102',
            'cName'                 => 'kontakt_sperre_minuten',
            'cWert'                 => '1',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '102',
            'cName'                 => 'kontakt_abfragen_captcha',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '104',
            'cName'                 => 'rss_bewertungen_beachten',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '104',
            'cName'                 => 'rss_news_beachten',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '104',
            'cName'                 => 'rss_wawiabgleich',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '104',
            'cName'                 => 'rss_artikel_beachten',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '104',
            'cName'                 => 'rss_alterTage',
            'cWert'                 => '',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '104',
            'cName'                 => 'rss_logoURL',
            'cWert'                 => '',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '104',
            'cName'                 => 'rss_nutzen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '104',
            'cName'                 => 'rss_titel',
            'cWert'                 => '',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '104',
            'cName'                 => 'rss_copyright',
            'cWert'                 => '',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '104',
            'cName'                 => 'rss_description',
            'cWert'                 => '',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '105',
            'cName'                 => 'preisverlauf_anzeigen',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '105',
            'cName'                 => 'preisverlauf_anzahl_monate',
            'cWert'                 => '0',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '106',
            'cName'                 => 'vergleichsliste_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '106',
            'cName'                 => 'vergleichsliste_anzahl',
            'cWert'                 => '10',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '106',
            'cName'                 => 'vergleichsliste_verfuegbarkeit',
            'cWert'                 => '7',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '106',
            'cName'                 => 'vergleichsliste_lieferzeit',
            'cWert'                 => '6',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '106',
            'cName'                 => 'vergleichsliste_artikelnummer',
            'cWert'                 => '0',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '106',
            'cName'                 => 'vergleichsliste_hersteller',
            'cWert'                 => '5',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '106',
            'cName'                 => 'vergleichsliste_kurzbeschreibung',
            'cWert'                 => '1',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '106',
            'cName'                 => 'vergleichsliste_beschreibung',
            'cWert'                 => '10',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '106',
            'cName'                 => 'vergleichsliste_artikelgewicht',
            'cWert'                 => '9',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '106',
            'cName'                 => 'vergleichsliste_versandgewicht',
            'cWert'                 => '0',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '106',
            'cName'                 => 'vergleichsliste_variationen',
            'cWert'                 => '10',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '106',
            'cName'                 => 'vergleichsliste_merkmale',
            'cWert'                 => '9',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '106',
            'cName'                 => 'vergleichsliste_spaltengroesse',
            'cWert'                 => '300',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '106',
            'cName'                 => 'vergleichsliste_target',
            'cWert'                 => 'popup',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '107',
            'cName'                 => 'bewertungserinnerung_kundengruppen',
            'cWert'                 => '',
            'type'                  => 'listbox'
        ],
        [
            'kEinstellungenSektion' => '107',
            'cName'                 => 'bewertung_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '107',
            'cName'                 => 'bewertung_freischalten',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '107',
            'cName'                 => 'bewertung_anzahlseite',
            'cWert'                 => '10',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '107',
            'cName'                 => 'bewertung_sortierung',
            'cWert'                 => '0',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '107',
            'cName'                 => 'bewertung_hilfreich_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '107',
            'cName'                 => 'bewertung_alle_sprachen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '107',
            'cName'                 => 'bewertungserinnerung_nutzen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '107',
            'cName'                 => 'bewertungserinnerung_versandtage',
            'cWert'                 => '3',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '107',
            'cName'                 => 'bewertung_guthaben_nutzen',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '107',
            'cName'                 => 'bewertung_stufe2_anzahlzeichen',
            'cWert'                 => '120',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '107',
            'cName'                 => 'bewertung_stufe1_guthaben',
            'cWert'                 => '1',
            'type'                  => 'kommazahl'
        ],
        [
            'kEinstellungenSektion' => '107',
            'cName'                 => 'bewertung_stufe2_guthaben',
            'cWert'                 => '2',
            'type'                  => 'kommazahl'
        ],
        [
            'kEinstellungenSektion' => '107',
            'cName'                 => 'bewertung_max_guthaben',
            'cWert'                 => '10',
            'type'                  => 'kommazahl'
        ],
        [
            'kEinstellungenSektion' => '107',
            'cName'                 => 'bewertung_artikel_gekauft',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '108',
            'cName'                 => 'newsletter_active',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '108',
            'cName'                 => 'newsletter_emailmethode',
            'cWert'                 => 'sendmail',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '108',
            'cName'                 => 'newsletter_sendmailpfad',
            'cWert'                 => '/usr/bin/msmtp -t  --host host.docker.internal --from msmtp@localhost',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '108',
            'cName'                 => 'newsletter_smtp_host',
            'cWert'                 => '',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '108',
            'cName'                 => 'newsletter_smtp_port',
            'cWert'                 => '0',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '108',
            'cName'                 => 'newsletter_smtp_authnutzen',
            'cWert'                 => '1',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '108',
            'cName'                 => 'newsletter_smtp_benutzer',
            'cWert'                 => '',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '108',
            'cName'                 => 'newsletter_smtp_pass',
            'cWert'                 => '',
            'type'                  => 'pass'
        ],
        [
            'kEinstellungenSektion' => '108',
            'cName'                 => 'newsletter_smtp_verschluesselung',
            'cWert'                 => '',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '108',
            'cName'                 => 'newsletter_emailadresse',
            'cWert'                 => 'max@mustermann.de',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '108',
            'cName'                 => 'newsletter_emailtest',
            'cWert'                 => 'max@mustermann.de',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '108',
            'cName'                 => 'newsletter_emailabsender',
            'cWert'                 => 'Tim Niko Tegtmeyer',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '108',
            'cName'                 => 'newsletter_doubleopt',
            'cWert'                 => 'A',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '108',
            'cName'                 => 'newsletter_sicherheitscode',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '108',
            'cName'                 => 'newsletter_send_delay',
            'cWert'                 => '0',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '109',
            'cName'                 => 'kundenfeld_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'configgroup_110_manufacturer_filter',
            'cWert'                 => 'Herstellerfilter',
            'type'                  => ''
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'configgroup_110_availability_filter',
            'cWert'                 => 'Verfügbarkeitsfilter',
            'type'                  => ''
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'allgemein_weiterleitung',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'allgemein_suchspecialfilter_benutzen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'search_special_filter_type',
            'cWert'                 => 'A',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'kategorie_bild_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'kategorie_beschreibung_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'artikeluebersicht_bild_anzeigen',
            'cWert'                 => 'BT',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'unterkategorien_lvl2_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'unterkategorien_beschreibung_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'allgemein_availabilityfilter_benutzen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'allgemein_herstellerfilter_benutzen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'manufacturer_filter_type',
            'cWert'                 => 'A',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'hersteller_anzeigen_als',
            'cWert'                 => 'T',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'allgemein_kategoriefilter_benutzen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'kategoriefilter_anzeigen_als',
            'cWert'                 => 'KA',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'category_filter_type',
            'cWert'                 => 'A',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'bewertungsfilter_benutzen',
            'cWert'                 => 'box',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'suchtrefferfilter_nutzen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'suchtrefferfilter_anzahl',
            'cWert'                 => '0',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'merkmalfilter_verwenden',
            'cWert'                 => 'box',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'merkmal_anzeigen_als',
            'cWert'                 => 'BT',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'merkmalfilter_trefferanzahl_anzeigen',
            'cWert'                 => 'E',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'merkmal_label_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'merkmalfilter_maxmerkmale',
            'cWert'                 => '0',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'merkmalfilter_maxmerkmalwerte',
            'cWert'                 => '0',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'preisspannenfilter_benutzen',
            'cWert'                 => 'box',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'preisspannenfilter_spannen_ausblenden',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'preisspannenfilter_anzeige_berechnung',
            'cWert'                 => 'A',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'hersteller_bild_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'hersteller_beschreibung_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'merkmalwert_bild_anzeigen',
            'cWert'                 => 'BT',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '110',
            'cName'                 => 'merkmalwert_beschreibung_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '111',
            'cName'                 => 'blacklist_benutzen',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '112',
            'cName'                 => 'global_meta_publisher',
            'cWert'                 => '',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '112',
            'cName'                 => 'global_meta_copyright',
            'cWert'                 => '',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '112',
            'cName'                 => 'global_meta_title_anhaengen',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '112',
            'cName'                 => 'global_meta_title_preis',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '112',
            'cName'                 => 'global_meta_keywords_laenge',
            'cWert'                 => '',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '112',
            'cName'                 => 'global_meta_maxlaenge_title',
            'cWert'                 => '69',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '112',
            'cName'                 => 'global_meta_maxlaenge_description',
            'cWert'                 => '156',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '113',
            'cName'                 => 'news_kategorie_unternewsanzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '113',
            'cName'                 => 'news_kommentare_anzahlprobesucher',
            'cWert'                 => '0',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '113',
            'cName'                 => 'news_kommentare_anzahlproseite',
            'cWert'                 => '0',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '113',
            'cName'                 => 'news_kommentare_freischalten',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '113',
            'cName'                 => 'news_anzahl_box',
            'cWert'                 => '0',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '113',
            'cName'                 => 'news_anzahl_content',
            'cWert'                 => '0',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '113',
            'cName'                 => 'news_kommentare_nutzen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '113',
            'cName'                 => 'news_benutzen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '113',
            'cName'                 => 'news_anzahl_uebersicht',
            'cWert'                 => '10',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '113',
            'cName'                 => 'news_kommentare_anzahl_antwort_kommentare_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '114',
            'cName'                 => 'sitemap_google_ping',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '114',
            'cName'                 => 'sitemap_seiten_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '114',
            'cName'                 => 'sitemap_kategorien_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '114',
            'cName'                 => 'sitemap_hersteller_anzeigen',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '114',
            'cName'                 => 'sitemap_news_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '114',
            'cName'                 => 'sitemap_newskategorien_anzeigen',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '114',
            'cName'                 => 'sitemap_livesuche_anzeigen',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '114',
            'cName'                 => 'sitemap_googleimage_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '114',
            'cName'                 => 'sitemap_varkombi_children_export',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '114',
            'cName'                 => 'sitemap_insert_changefreq',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '114',
            'cName'                 => 'sitemap_insert_priority',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '114',
            'cName'                 => 'sitemap_insert_lastmod',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '114',
            'cName'                 => 'sitemap_wawiabgleich',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '114',
            'cName'                 => 'sitemap_images_categories',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '114',
            'cName'                 => 'sitemap_images_manufacturers',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '114',
            'cName'                 => 'sitemap_images_newscategory_items',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '114',
            'cName'                 => 'sitemap_images_news_items',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '114',
            'cName'                 => 'sitemap_images_attributes',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '119',
            'cName'                 => 'suchspecials_sortierung_inkuerzeverfuegbar',
            'cWert'                 => '-1',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '119',
            'cName'                 => 'suchspecials_sortierung_topangebote',
            'cWert'                 => '-1',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '119',
            'cName'                 => 'suchspecials_sortierung_neuimsortiment',
            'cWert'                 => '-1',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '119',
            'cName'                 => 'suchspecials_sortierung_topbewertet',
            'cWert'                 => '-1',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '119',
            'cName'                 => 'suchspecials_sortierung_sonderangebote',
            'cWert'                 => '-1',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '119',
            'cName'                 => 'suchspecials_sortierung_bestseller',
            'cWert'                 => '-1',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '121',
            'cName'                 => 'auswahlassistent_allefragen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '121',
            'cName'                 => 'auswahlassistent_anzahl_anzeigen',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '121',
            'cName'                 => 'auswahlassistent_nutzen',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '121',
            'cName'                 => 'auswahlassistent_anzeigeformat',
            'cWert'                 => 'T',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '121',
            'cName'                 => 'configgroup_121_selectionwizard',
            'cWert'                 => 'Auswahlassistent',
            'type'                  => ''
        ],
        [
            'kEinstellungenSektion' => '124',
            'cName'                 => 'caching_types_disabled',
            'cWert'                 => '',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '124',
            'cName'                 => 'configgroup_124_cache',
            'cWert'                 => 'Consent manager',
            'type'                  => ''
        ],
        [
            'kEinstellungenSektion' => '124',
            'cName'                 => 'caching_activated',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '124',
            'cName'                 => 'caching_method',
            'cWert'                 => 'redis',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '124',
            'cName'                 => 'caching_redis_host',
            'cWert'                 => 'cache',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '124',
            'cName'                 => 'caching_redis_port',
            'cWert'                 => '6379',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '124',
            'cName'                 => 'caching_redis_db',
            'cWert'                 => '0',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '124',
            'cName'                 => 'caching_redis_user',
            'cWert'                 => '',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '124',
            'cName'                 => 'caching_redis_pass',
            'cWert'                 => 'eYVX7EwVmmxKPCDmwMtyKVge8oLd2t81',
            'type'                  => 'pass'
        ],
        [
            'kEinstellungenSektion' => '124',
            'cName'                 => 'caching_redis_persistent',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '124',
            'cName'                 => 'caching_memcache_port',
            'cWert'                 => '11211',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '124',
            'cName'                 => 'caching_memcache_host',
            'cWert'                 => 'localhost',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '124',
            'cName'                 => 'caching_lifetime',
            'cWert'                 => '86400',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '124',
            'cName'                 => 'caching_debug',
            'cWert'                 => 'N',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '124',
            'cName'                 => 'caching_debug_method',
            'cWert'                 => 'echo',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '124',
            'cName'                 => 'caching_rediscluster_hosts',
            'cWert'                 => '',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '124',
            'cName'                 => 'caching_rediscluster_strategy',
            'cWert'                 => '1',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '124',
            'cName'                 => 'compile_check',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '125',
            'cName'                 => 'shop_logo',
            'cWert'                 => 'Nova-Logo.svg',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '127',
            'cName'                 => 'ftp_header',
            'cWert'                 => 'FTP Verbindung',
            'type'                  => ''
        ],
        [
            'kEinstellungenSektion' => '127',
            'cName'                 => 'fs_general_header',
            'cWert'                 => 'General',
            'type'                  => ''
        ],
        [
            'kEinstellungenSektion' => '127',
            'cName'                 => 'sftp_header',
            'cWert'                 => 'SFTP Verbindung',
            'type'                  => ''
        ],
        [
            'kEinstellungenSektion' => '127',
            'cName'                 => 'configgroup_127_filesystem',
            'cWert'                 => 'Filesystem',
            'type'                  => ''
        ],
        [
            'kEinstellungenSektion' => '127',
            'cName'                 => 'fs_adapter',
            'cWert'                 => 'local',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '127',
            'cName'                 => 'fs_timeout',
            'cWert'                 => '10',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '127',
            'cName'                 => 'ftp_hostname',
            'cWert'                 => 'localhost',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '127',
            'cName'                 => 'ftp_port',
            'cWert'                 => '21',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '127',
            'cName'                 => 'ftp_user',
            'cWert'                 => '',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '127',
            'cName'                 => 'ftp_pass',
            'cWert'                 => '',
            'type'                  => 'pass'
        ],
        [
            'kEinstellungenSektion' => '127',
            'cName'                 => 'ftp_ssl',
            'cWert'                 => '1',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '127',
            'cName'                 => 'ftp_path',
            'cWert'                 => '/',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '127',
            'cName'                 => 'sftp_hostname',
            'cWert'                 => 'localhost',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '127',
            'cName'                 => 'sftp_port',
            'cWert'                 => '22',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '127',
            'cName'                 => 'sftp_user',
            'cWert'                 => '',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '127',
            'cName'                 => 'sftp_pass',
            'cWert'                 => '',
            'type'                  => 'pass'
        ],
        [
            'kEinstellungenSektion' => '127',
            'cName'                 => 'sftp_privkey',
            'cWert'                 => '',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '127',
            'cName'                 => 'sftp_path',
            'cWert'                 => '/',
            'type'                  => 'text'
        ],
        [
            'kEinstellungenSektion' => '128',
            'cName'                 => 'cron_freq',
            'cWert'                 => '10',
            'type'                  => 'number'
        ],
        [
            'kEinstellungenSektion' => '128',
            'cName'                 => 'cron_type',
            'cWert'                 => 's2s',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '128',
            'cName'                 => 'configgroup_128_cron',
            'cWert'                 => 'Cron',
            'type'                  => ''
        ],
        [
            'kEinstellungenSektion' => '129',
            'cName'                 => 'consent_manager_active',
            'cWert'                 => 'Y',
            'type'                  => 'selectbox'
        ],
        [
            'kEinstellungenSektion' => '129',
            'cName'                 => 'configgroup_129_consentmanager',
            'cWert'                 => 'Cache',
            'type'                  => ''
        ],
        [
            'kEinstellungenSektion' => '129',
            'cName'                 => 'consent_manager_show_banner',
            'cWert'                 => '1',
            'type'                  => 'selectbox'
        ]
    ];

    /**
     * This method is called before the class gets tested.
     *
     * @throws Exception
     * */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $unitTestCase = new UnitTestCase('utc');
        $db           = $unitTestCase->createMock(DbInterface::class);
        $cache        = $unitTestCase->createMock(JTLCacheInterface::class);

        // Replace DB and Cache in Shop::Container() by empty mocks
        Shop::Container()->setSingleton(LoggerInterface::class, function () {
            return new TestLogger();
        });
        Shop::Container()->offsetUnset(DbInterface::class);
        Shop::Container()->singleton(
            abstract: DbInterface::class,
            concrete: fn() => $db
        );
        Shop::Container()->offsetUnset(JTLCacheInterface::class);
        Shop::Container()->singleton(
            abstract: JTLCacheInterface::class,
            concrete: fn() => $cache
        );

        // Set Shop settings
        $cache->method('get')
            ->willReturn(false);
        $db->expects($unitTestCase->once())->method('getArrays')
            ->willReturn(self::$shopSettings);
        $shopSettings = Shopsetting::getInstance($db, $cache);
        foreach ($shopSettings->getAll() as $section => $settings) {
            $shopSettings->offsetSet($section, $settings);
        }
        $pluginPaymentSettings = [
            'pluginzahlungsarten' => [
                'kPlugin_54_rechnungskaufmitratepay_min_bestellungen'               => 0,
                'kPlugin_54_rechnungskaufmitratepay_min'                            => 0,
                'kPlugin_54_rechnungskaufmitratepay_max'                            => 0,
                'kPlugin_54_paypalkreditkarte_min_bestellungen'                     => 0,
                'kPlugin_54_paypalkreditkarte_min'                                  => 0,
                'kPlugin_54_paypalkreditkarte_max'                                  => 0,
                'kPlugin_54_googlepay_min_bestellungen'                             => 0,
                'kPlugin_54_googlepay_min'                                          => 0,
                'kPlugin_54_googlepay_max'                                          => 0,
                'kPlugin_54_applepay_min_bestellungen'                              => 0,
                'kPlugin_54_applepay_min'                                           => 0,
                'kPlugin_54_applepay_max'                                           => 0,
                'jtl_paypal_commerce_expressBuyDisplay_showInMiniCart'              => 'Y',
                'jtl_paypal_commerce_expressBuyDisplay_miniCart_phpqSelector'       => '.cart-dropdown-buttons:first',
                'jtl_paypal_commerce_expressBuyDisplay_miniCart_phpqMethod'         => 'append',
                'jtl_paypal_commerce_instalmentBannerDisplay_showInMiniCart'        => 'Y',
                'jtl_paypal_commerce_instalmentBannerDisplay_miniCart_layout'       => 'text',
                'jtl_paypal_commerce_instalmentBannerDisplay_miniCart_logoType'     => 'primary',
                'jtl_paypal_commerce_instalmentBannerDisplay_miniCart_textSize'     => 12,
                'jtl_paypal_commerce_instalmentBannerDisplay_miniCart_textColor'    => 'black',
                'jtl_paypal_commerce_instalmentBannerDisplay_miniCart_layoutRatio'  => '8x1',
                'jtl_paypal_commerce_instalmentBannerDisplay_miniCart_layoutType'   => 'white',
                'jtl_paypal_commerce_instalmentBannerDisplay_miniCart_phpqSelector' => '.cart-dropdown-buttons:first',
                'jtl_paypal_commerce_instalmentBannerDisplay_miniCart_phpqMethod'   => 'after',
                'kPlugin_54_paypalcheckout_min_bestellungen'                        => 0,
                'kPlugin_54_paypalcheckout_min'                                     => 20,
                'kPlugin_54_paypalcheckout_max'                                     => 0,
            ]
        ];
        foreach ($pluginPaymentSettings as $section => $settings) {
            $shopSettings->offsetSet($section, $settings);
        }

        // Set some standard session variables
        $lang = new LanguageModel($db);
        $lang->setId(1);
        $lang->setIso('ger');
        $lang->setShopDefault('Y');
        $_SESSION['Sprachen']       = [$lang];
        $_SESSION['cISOSprache']    = 'ger';
        $_SESSION['kSprache']       = 1;
        $_SESSION['cLieferlandISO'] = 'DE';
        $currencyData               = (object)[
            'cISO'                 => 'EUR',
            'cName'                => 'EUR',
            'cNameHTML'            => '&euro;',
            'fFaktor'              => 1.0,
            'cStandard'            => true,
            'cVorBetrag'           => false,
            'cTrennzeichenCent'    => ',',
            'cTrennzeichenTausend' => '.',
            'cURL'                 => '',
            'cURLFull'             => '',
        ];
        $_SESSION['Waehrung']       = new Currency(0);
        $_SESSION['Waehrung']->extract($currencyData);
        $_SESSION['Waehrung']->setID(1);
        Shop::Container()->getGetText();
    }

    /**
     * This method is called before each test.
     */
    public function setUp(): void
    {
        parent::setUp();
        // Save session state
        $this->sessionState = $_SESSION ?? [];
        // Save settings state
        self::$shopSettings = Shopsetting::getInstance()->getAll();
    }

    /**
     * This method is called after each test.
     */
    public function tearDown(): void
    {
        parent::tearDown();
        $logger = Shop::Container()->getLogService();
        if ($logger instanceof TestLogger) {
            $logger->logEntries = [];
        }
        // Restore session state
        $_SESSION = $this->sessionState;
        // Restore settings state
        $shopSettings = Shopsetting::getInstance();
        foreach (self::$shopSettings as $section => $settings) {
            $shopSettings->offsetSet($section, $settings);
        }
        // Restore db and cache in DI
        $this->setDatabaseMockInDI($this->createMock(NiceDB::class));
        $cache = $this->getCacheMock();
        $cache->method('get')->willReturn(false);
        $this->setCacheMockInDI($cache);
    }

    public function getDatabaseMock(): MockObject
    {
        return $this->getMockBuilder(DbInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function setDatabaseMockInDI(MockObject|DbInterface|NiceDB $db): void
    {
        Shop::Container()->offsetUnset(DbInterface::class);
        Shop::Container()->singleton(
            abstract: DbInterface::class,
            concrete: fn() => $db
        );
    }

    public function getCacheMock(): MockObject
    {
        return $this->getMockBuilder(JTLCacheInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function setCacheMockInDI(MockObject|JTLCacheInterface $cache): void
    {
        Shop::Container()->offsetUnset(JTLCacheInterface::class);
        Shop::Container()->singleton(
            abstract: JTLCacheInterface::class,
            concrete: fn() => $cache
        );
    }

    public function getLoggerMock(): MockObject
    {
        return $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function setLoggerMockInDI(MockObject|LoggerInterface $logger): void
    {
        Shop::Container()->offsetUnset(LoggerInterface::class);
        Shop::Container()->singleton(
            abstract: LoggerInterface::class,
            concrete: fn() => $logger
        );
    }

    /**
     * Helper method to get private property values
     */
    public function getPrivateProperty(object $object, string $propertyName): mixed
    {
        try {
            $property = (new ReflectionClass($object))->getProperty($propertyName);
            $property->setAccessible(true);

            return $property->getValue($object);
        } catch (ReflectionException $e) {
            die($e->getMessage());
        }
    }

    /**
     * Helper method to set private property values
     */
    public function setPrivateProperty(
        object|string $object,
        string $propertyName,
        mixed $value,
        string|null $originalClassName = null
    ): void {
        if ($originalClassName !== null) {
            // For static properties or properties that only gets set inside the constructor
            try {
                $property = (new ReflectionClass($originalClassName))->getProperty($propertyName);
                $property->setAccessible(true);
                $property->setValue($object, $value);
            } catch (ReflectionException $e) {
                die($e->getMessage());
            }
        } else {
            try {
                $property = (new ReflectionClass($object))->getProperty($propertyName);
                $property->setAccessible(true);
                $property->setValue($object, $value);
            } catch (ReflectionException $e) {
                die($e->getMessage());
            }
        }
    }
}
