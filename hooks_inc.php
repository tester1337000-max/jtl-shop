<?php

/**
 * hook list
 */

declare(strict_types=1);

/**
 * Ende Artikeldetail
 *
 * @file artikel.php
 * @param Artikel $oArtikel
 */
const HOOK_ARTIKEL_PAGE = 1;

/**
 * Falls nicht wahrend Bestellung bezahlt wird
 *
 * @file bestellabschluss.php
 * @param Bestellung $oBestellung
 */
const HOOK_BESTELLABSCHLUSS_PAGE = 2;

/**
 * Falls während Bestellung bezahlt wird
 *
 * @file bestellabschluss.php
 * @param Bestellung $oBestellung
 */
const HOOK_BESTELLABSCHLUSS_PAGE_ZAHLUNGSVORGANG = 3;

/**
 * Accountwahl im Bestellvorgang
 *
 * @file bestellvorgang.php
 */
const HOOK_BESTELLVORGANG_PAGE_STEPACCOUNTWAHL = 4;

/**
 * Unregistriert bestellen im Bestellvorgang (Formular)
 *
 * @file bestellvorgang.php
 */
const HOOK_BESTELLVORGANG_PAGE_STEPUNREGISTRIERTBESTELLEN = 5;

/**
 * Auswahl der Lieferadresse im Bestellvorgang
 *
 * @file bestellvorgang.php
 */
const HOOK_BESTELLVORGANG_PAGE_STEPLIEFERADRESSE = 6;

/**
 * Auswahl der Versandart im Bestellvorgang
 *
 * @file bestellvorgang.php
 */
const HOOK_BESTELLVORGANG_PAGE_STEPVERSAND = 7;

/**
 * Auswahl der Zahlungsart im Bestellvorgang
 *
 * @file bestellvorgang.php
 */
const HOOK_BESTELLVORGANG_PAGE_STEPZAHLUNG = 8;

/**
 * Auswahl der Zahlungsart mit Zusatzschritt im Bestellvorgang
 *
 * @file bestellvorgang.php
 */
const HOOK_BESTELLVORGANG_PAGE_STEPZAHLUNGZUSATZSCHRITT = 9;

/**
 * Übersichtsseite im Bestellvorgang
 *
 * @file bestellvorgang.php
 */
const HOOK_BESTELLVORGANG_PAGE_STEPBESTAETIGUNG = 10;

/**
 * Plausibilitätsprüfung nach Wahl der Versandart
 *
 * @file bestellvorgang.php
 */
const HOOK_BESTELLVORGANG_PAGE_STEPVERSAND_PLAUSI = 11;

/**
 * Plausibilitätsprüfung nach Eingabe der neuen Lieferadresse
 *
 * @file bestellvorgang.php
 */
const HOOK_BESTELLVORGANG_PAGE_STEPLIEFERADRESSE_NEUELIEFERADRESSE_PLAUSI = 12;

/**
 * Setzen der neuen Lieferadresse in die Bestellung
 *
 * @file bestellvorgang.php
 */
const HOOK_BESTELLVORGANG_PAGE_STEPLIEFERADRESSE_NEUELIEFERADRESSE = 13;

/**
 * Setzen der vorhandenen Lieferadresse in die Bestellung
 *
 * @file bestellvorgang.php
 */
const HOOK_BESTELLVORGANG_PAGE_STEPLIEFERADRESSE_VORHANDENELIEFERADRESSE = 14;

/**
 * Setzen der Lieferadresse aus Rechnungsadresse in die Bestellung
 *
 * @file bestellvorgang.php
 */
const HOOK_BESTELLVORGANG_PAGE_STEPLIEFERADRESSE_RECHNUNGLIEFERADRESSE = 15;

/**
 * Plausibilitätsprüfung nach Wahl der Zahlungsart
 *
 * @file bestellvorgang.php
 */
const HOOK_BESTELLVORGANG_PAGE_STEPZAHLUNG_PLAUSI = 16;

/**
 * Setzen des Guthabens im Step Bestätigung
 *
 * @file bestellvorgang.php
 */
const HOOK_BESTELLVORGANG_PAGE_STEPBESTAETIGUNG_GUTHABENVERRECHNEN = 17;

/**
 * Plausibilitätsprüfung im Step Bestätigung ob Guthaben genutzt wurde
 *
 * @file bestellvorgang.php
 */
const HOOK_BESTELLVORGANG_PAGE_STEPBESTAETIGUNG_GUTHABEN_PLAUSI = 18;

/**
 * Kurz vor der Anzeige im Bestellvorgang
 *
 * @file bestellvorgang.php
 */
const HOOK_BESTELLVORGANG_PAGE = 19;

/**
 * Kurz vor der Anzeige eines Artikels in der Druckansicht
 *
 * @removed
 */
const HOOK_DRUCKANSICHT_PAGE_ARTIKEL = 20;

/**
 * Kurz vor der Anzeige eines Textes in der Druckansicht
 *
 * @removed
 */
const HOOK_DRUCKANSICHT_PAGE_TEXT = 21;

/**
 * Kurz vor der Anzeige in der Artikelübersicht
 *
 * @file filter.php
 */
const HOOK_FILTER_PAGE = 22;

/**
 * Kurz vor der Anzeige in der JTL Seite
 *
 * @file jtl.php
 */
const HOOK_JTL_PAGE = 23;

/**
 * Gekommen von einer Seite um sich einzuloggen und kurz vor dem Redirect zurück
 *
 * @file jtl.php
 */
const HOOK_JTL_PAGE_REDIRECT = 24;

/**
 * Plausibilitätsprüfung nach Ändern von Kundendaten
 *
 * @file jtl.php
 */
const HOOK_JTL_PAGE_KUNDENDATEN_PLAUSI = 25;

/**
 * Bei der Löschung eines Kundenkontos
 *
 * @file jtl.php
 */
const HOOK_JTL_PAGE_KUNDENACCOUNTLOESCHEN = 26;

/**
 * Anzeige des Kundenkontos
 *
 * @file jtl.php
 * @param Lieferadresse[] $deliveryAddresses - since 5.0.0
 */
const HOOK_JTL_PAGE_MEINKKONTO = 27;

/**
 * Kommt von einer Seite um sich als Kunde einzuloggen (JTL)
 *
 * @file jtl.php
 */
const HOOK_JTL_PAGE_REDIRECT_DATEN = 28;

/**
 * Kurz vor der Anzeige des Kontaktformulars
 *
 * @file kontakt.php
 */
const HOOK_KONTAKT_PAGE = 29;

/**
 * Plausibilitätsprüfung nach Abschicken des Kontaktformulars
 *
 * @file kontakt.php
 */
const HOOK_KONTAKT_PAGE_PLAUSI = 30;

/**
 * Kurz vor der Anzeige in der Artikelübersicht
 *
 * @file navi.php
 */
const HOOK_NAVI_PAGE = 31;

/**
 * Kurz vor der Anzeige in der News Detailansicht
 *
 * @file news.php
 * @param JTL\News\Item             $newsItem - since 5.0.0
 * @param JTL\Pagination\Pagination $pagination - since 5.0.0
 */
const HOOK_NEWS_PAGE_DETAILANSICHT = 32;

/**
 * Kurz vor der Anzeige in der News Übersicht
 *
 * @file news.php
 * @param JTL\News\Category             $category - since 5.0.0
 * @param Illuminate\Support\Collection $items - since 5.0.0
 */
const HOOK_NEWS_PAGE_NEWSUEBERSICHT = 33;

/**
 * Plausibilitätsprüfung nach abschicken eines Newskommentars
 *
 * @file news.php
 */
const HOOK_NEWS_PAGE_NEWSKOMMENTAR_PLAUSI = 34;

/**
 * Kurz bevor der Newskommentar in die Datenbank gelangt
 *
 * @param stdClass $comment
 */
const HOOK_NEWS_PAGE_NEWSKOMMENTAR_EINTRAGEN = 35;

/**
 * Kurz vor der Anzeige der Newsletter an- und abmeldung
 *
 * @file newsletter.php
 */
const HOOK_NEWSLETTER_PAGE = 36;

/**
 * Kurz bevor ein Newsletterempfänger in die Datenbank eingetragen wird
 *
 * @file newsletter.php
 * @param stdClass $oNewsletterEmpfaenger
 */
const HOOK_NEWSLETTER_PAGE_EMPFAENGEREINTRAGEN = 37;

/**
 * Kurz bevor ein Newsletterempfänger gelöscht wird
 *
 * @file newsletter.php
 * @param stdClass $oNewsletterEmpfaenger
 */
const HOOK_NEWSLETTER_PAGE_EMPFAENGERLOESCHEN = 38;

/**
 * Kurz bevor ein Newsletterempfänger freigeschalten wird
 *
 * @file newsletter.php
 * @param stdClass $oNewsletterEmpfaenger
 */
const HOOK_NEWSLETTER_PAGE_EMPFAENGERFREISCHALTEN = 39;

/**
 * Kurz bevor die Registrierungsseite angezeigt wird
 *
 * @file registrieren.php
 */
const HOOK_REGISTRIEREN_PAGE = 40;

/**
 * Plausibilitätsprüfung nach abschicken einer Kundenregistrierung
 *
 * @file registrieren_inc.php
 * @param int   $nReturnValue
 * @param array $fehlendeAngaben
 */
const HOOK_REGISTRIEREN_PAGE_REGISTRIEREN_PLAUSI = 41;

/**
 * Kurz bevor die Anzeige zur Seite ausgegeben wird
 *
 * @file seite.php
 */
const HOOK_SEITE_PAGE = 42;

/**
 * @deprecated since 5.0.0
 */
const HOOK_TOOLSAJAXSERVER_PAGE_KUNDENFORMULARPLZ = 43;

/**
 * @deprecated since 5.0.0
 */
const HOOK_TOOLSAJAXSERVER_PAGE_SUCHVORSCHLAG = 44;

/**
 *
 */
const HOOK_TOOLSAJAXSERVER_PAGE_TAUSCHEVARIATIONKOMBI = 45;

/**
 * @deprecated deprecated since version 4.00
 */
const HOOK_TOOLSAJAXSERVER_PAGE_ARTIKELDETAIL = 46;

/**
 * @removed in 5.0.0
 */
const HOOK_UMFRAGE_PAGE = 47;

/**
 * @removed in 5.0.0
 */
const HOOK_UMFRAGE_PAGE_UEBERSICHT = 48;

/**
 * @removed in 5.0.0
 */
const HOOK_UMFRAGE_PAGE_DURCHFUEHRUNG = 49;

/**
 * @removed in 5.0.0
 */
const HOOK_UMFRAGE_PAGE_UMFRAGEERGEBNIS = 50;

/**
 * Kurz vor der Anzeige der Vergleichsliste
 *
 * @file vergleichsliste.php
 */
const HOOK_VERGLEICHSLISTE_PAGE = 51;

/**
 * Kurz vor der Anzeige des Warenkorbs
 *
 * @file warenkorb.php
 */
const HOOK_WARENKORB_PAGE = 52;

/**
 * Nach der Ermittlung der Versandkosten im Warenkorb
 *
 * @file warenkorb.php
 */
const HOOK_WARENKORB_PAGE_ERMITTLEVERSANDKOSTEN = 53;

/**
 * Nach der Annahme eines Kupons im Warenkorb
 *
 * @file warenkorb.php
 */
const HOOK_WARENKORB_PAGE_KUPONANNEHMEN = 54;

/**
 * Plausibilitätsprüfung für die Annahme eines Kupons im Warenkorb
 *
 * @file warenkorb.php
 * @param array $error
 * @param int   $nReturnValue
 */
const HOOK_WARENKORB_PAGE_KUPONANNEHMEN_PLAUSI = 55;

/**
 * Vor dem Einfügen des Gratisgeschenkes
 *
 * @file warenkorb.php
 */
const HOOK_WARENKORB_PAGE_GRATISGESCHENKEINFUEGEN = 56;

/**
 * Vor der Rückgabe der XSelling Artikel in den Artikeldetails
 *
 * @param int      $kArtikel
 * @param stdClass $xSelling
 */
const HOOK_ARTIKEL_INC_XSELLING = 57;

/**
 * Vor der Rückgabe des MetaTitle Artikel in den Artikeldetails
 *
 * @file Artikel.php
 * @param string $cTitle
 * @since 4.0
 */
const HOOK_ARTIKEL_INC_METATITLE = 58;

/**
 * Vor der Rückgabe der MetaDescription Artikel in den Artikeldetails
 *
 * @file Artikel.php
 * @param string  $cDesc
 * @param Artikel $oArtikel
 * @since 4.0
 */
const HOOK_ARTIKEL_INC_METADESCRIPTION = 59;

/**
 * Vor der Rückgabe der MetaKeywords Artikel in den Artikeldetails
 *
 * @file Artikel.php
 * @param string $keywords
 * @since 4.0
 */
const HOOK_ARTIKEL_INC_METAKEYWORDS = 60;

/**
 * Plausibilitätsprüfung für die Versendung einer Frage zum Produkt
 *
 * @file artikel_inc.php
 */
const HOOK_ARTIKEL_INC_FRAGEZUMPRODUKT_PLAUSI = 61;

/**
 * Vor der Sendung einer Frage zum Produkt
 *
 * @file artikel_inc.php
 */
const HOOK_ARTIKEL_INC_FRAGEZUMPRODUKT = 62;

/**
 * Plausibilitätsprüfung für die Benachrichtigung Artikel in den Artikeldetails
 *
 * @file artikel_inc.php
 */
const HOOK_ARTIKEL_INC_BENACHRICHTIGUNG_PLAUSI = 65;

/**
 * Vor der Sendung der Benachrichtigung in den Artikeldetails
 *
 * @param stdClass $Benachrichtigung - since 4.07
 */
const HOOK_ARTIKEL_INC_BENACHRICHTIGUNG = 66;

/**
 * Im Switch der Artikelhinweise in den Artikeldetails
 *
 * @file artikel_inc.php
 */
const HOOK_ARTIKEL_INC_ARTIKELHINWEISSWITCH = 67;

/**
 * @removed in 5.0.0
 */
const HOOK_ARTIKEL_INC_PRODUKTTAGGING = 68;

/**
 * Im Switch der Bewertungshinweise in den Artikeldetails
 *
 * @file artikel_inc.php
 * @param string $error
 */
const HOOK_ARTIKEL_INC_BEWERTUNGHINWEISSWITCH = 69;

/**
 * Nach der Ermittlung der zuletzt angesehenden Artikel in den Artikeldetails
 *
 * @file artikel_inc.php
 */
const HOOK_ARTIKEL_INC_ZULETZTANGESEHEN = 70;

/**
 * Nach der Zusammenfassung einer Variationskombination und einem Vaterartikel in den Artikeldetails
 *
 * @param JTL\Catalog\Product\Artikel $article
 */
const HOOK_ARTIKEL_INC_FASSEVARIVATERUNDKINDZUSAMMEN = 71;

/**
 * Kurz vor der Rückgabe der ähnlichen Artikel in den Artikeldetails
 *
 * @param array $oArtikel_arr
 * @param int   $kArtikel
 */
const HOOK_ARTIKEL_INC_AEHNLICHEARTIKEL = 72;

/**
 * Kurz vor der Sendung der Email für eine Neukundenregistrierung während des Einfügens einer Bestellung
 *
 * @file bestellabschluss_inc.php
 */
const HOOK_BESTELLABSCHLUSS_INC_BESTELLUNGINDB_NEUKUNDENREGISTRIERUNG = 73;

/**
 * Kurz vor dem Eintragen der Rechnungsadresse in die Datenbank während des Einfügens einer Bestellung
 *
 * @param \JTL\Checkout\Rechnungsadresse $billingAddress - since 5.0.0
 * @file bestellabschluss_inc.php
 */
const HOOK_BESTELLABSCHLUSS_INC_BESTELLUNGINDB_RECHNUNGSADRESSE = 74;

/**
 * before saving an order to the database
 *
 * @file bestellabschluss_inc.php
 * @param JTL\Checkout\Bestellung $oBestellung
 */
const HOOK_BESTELLABSCHLUSS_INC_BESTELLUNGINDB = 75;

/**
 * Plausibilitätsprüfung für die unregistrierte Registrierung im Bestellvorgang
 *
 * @file bestellvorgang_inc.php
 * @param int                   $nReturnValue
 * @param array                 $fehlendeAngaben
 * @param JTL\Customer\Customer $Customer
 * @param array                 $cPost_arr
 */
const HOOK_BESTELLVORGANG_INC_UNREGISTRIERTBESTELLEN_PLAUSI = 76;

/**
 * at the end of pruefeUnregistriertBestellen() if successful
 *
 * @file bestellvorgang_inc.php
 */
const HOOK_BESTELLVORGANG_INC_UNREGISTRIERTBESTELLEN = 77;

/**
 * before saving a rating to the database
 *
 * @file bewertung_inc.php
 * @param JTL\Review\ReviewModel $rating
 */
const HOOK_BEWERTUNG_INC_SPEICHERBEWERTUNG = 78;

/**
 * before saving Bewertunghilfreich to database
 *
 * @param JTL\Review\ReviewModel $rating
 */
const HOOK_BEWERTUNG_INC_SPEICHERBEWERTUNGHILFREICH = 79;

/**
 * @file Boxen.php
 */
const HOOK_BOXEN_INC_SCHNELLKAUF = 80;

/**
 * @file Boxen.php
 * @param \JTL\Boxes\Items\AbstractBox $box
 */
const HOOK_BOXEN_INC_ZULETZTANGESEHEN = 81;

/**
 * @file Boxen.php
 * @param JTL\Boxes\Items\BoxInterface $box
 * @param array                        $cache_tags
 */
const HOOK_BOXEN_INC_TOPANGEBOTE = 82;

/**
 * @file Boxen.php
 * @param JTL\Boxes\Items\BoxInterface $box
 * @param array                        $cache_tags
 */
const HOOK_BOXEN_INC_NEUIMSORTIMENT = 83;

/**
 * @file Boxen.php
 * @param JTL\Boxes\Items\BoxInterface $box
 * @param array                        $cache_tags
 */
const HOOK_BOXEN_INC_SONDERANGEBOTE = 84;

/**
 * @file Boxen.php
 * @param JTL\Boxes\Items\BoxInterface $box
 * @param array                        $cache_tags
 */
const HOOK_BOXEN_INC_BESTSELLER = 85;

/**
 * @file Boxen.php
 * @param JTL\Boxes\Items\BoxInterface $box
 * @param array                        $cache_tags
 */
const HOOK_BOXEN_INC_ERSCHEINENDEPRODUKTE = 86;

/**
 * @file Boxen.php
 * @param JTL\Boxes\Items\BoxInterface $box
 * @param array                        $cache_tags
 */
const HOOK_BOXEN_INC_SUCHWOLKE = 87;

/**
 * @removed in 5.0.0
 */
const HOOK_BOXEN_INC_TAGWOLKE = 88;

/**
 * @file Boxen.php
 * @param JTL\Boxes\Items\BoxInterface $box
 */
const HOOK_BOXEN_INC_WUNSCHZETTEL = 89;

/**
 * @file Boxen.php
 * @param JTL\Boxes\Items\BoxInterface $box
 */
const HOOK_BOXEN_INC_VERGLEICHSLISTE = 90;

/**
 *
 */
const HOOK_BOXEN_INC_SUCHSPECIALURL = 91;

/**
 * @file Boxen.php
 * @param JTL\Boxes\Items\BoxInterface $box
 * @param array                        $cache_tags
 */
const HOOK_BOXEN_INC_TOPBEWERTET = 92;

/**
 * @file Boxen.php
 */
const HOOK_BOXEN_INC_NEWS = 93;

/**
 * @file Boxen.php
 * @param JTL\Boxes\Items\BoxInterface $box
 * @param array                        $cache_tags
 */
const HOOK_BOXEN_INC_NEWSKATEGORIE = 94;

/**
 * @file Boxen.php
 * @param JTL\Boxes\Items\BoxInterface $box
 * @param array                        $cache_tags
 */
const HOOK_BOXEN_INC_UMFRAGE = 95;

/**
 * @deprecated since 5.0.0
 */
const HOOK_CRON_INC_SWITCH = 96;

/**
 * @deprecated since 5.0.0
 */
const HOOK_JOBQUEUE_INC_SWITCH = 97;

/**
 * at the end of gibRedirect() before saving redirect to session
 *
 * @param int      $cRedirect
 * @param stdClass $oRedirect
 */
const HOOK_JTL_INC_SWITCH_REDIRECT = 98;

/**
 * at the end of the file
 *
 * @file letzterinclude.php
 */
const HOOK_LETZTERINCLUDE_INC = 99;

/**
 * after template switch in sendeMail()
 *
 * @param \JTL\Smarty\JTLSmarty                $mailsmarty
 * @param \JTL\Mail\Renderer\RendererInterface $renderer - since 5.0.0
 * @param object                               $mail - null since 5.0.0
 * @param int                                  $kEmailvorlage
 * @param int                                  $kSprache
 * @param string                               $cPluginBody - empty string since 5.0.0
 * @param \JTL\Mail\Template\Model             $Emailvorlage
 * @param \JTL\Mail\Template\TemplateInterface $template - since 5.0.0
 * @param \JTL\Mail\Template\Model             $model - since 5.0.0
 */
const HOOK_MAILTOOLS_INC_SWITCH = 100;

/**
 * after creating the navigation object, before assigning to smarty
 *
 * @param array $navigation
 */
const HOOK_TOOLSGLOBAL_INC_SWITCH_CREATENAVIGATION = 101;

/**
 * @removed
 */
const HOOK_TOOLSGLOBAL_INC_PREISSTRINGLOCALIZED = 102;

/**
 * at the end of gibMwStVersandString() before returning the string
 *
 * @file Artikel.php
 * @param string                      $cVersandhinweis
 * @param JTL\Catalog\Product\Artikel $oArtikel
 * @since 4.0
 */
const HOOK_TOOLSGLOBAL_INC_MWSTVERSANDSTRING = 103;

/**
 * at the beginning of baueURL()
 *
 * @param mixed $obj
 * @param int   $art
 */
const HOOK_TOOLSGLOBAL_INC_SWITCH_BAUEURL = 104;

/**
 * @deprecated since 5.0.0
 */
const HOOK_TOOLSGLOBAL_INC_SETZELINKS = 105;

/**
 * after calculating shiping costs
 *
 * @param float|int                                 $fPreis
 * @param JTL\Checkout\Versandart|stdClass          $versandart
 * @param string                                    $cISO
 * @param null|stdClass|JTL\Catalog\Product\Artikel $oZusatzArtikel
 * @param null|JTL\Catalog\Product\Artikel          $Artikel
 * @deprecated since 5.5.0. Use HOOK_CALCULATE_SHIPPING_COSTS_END instead.
 */
const HOOK_TOOLSGLOBAL_INC_BERECHNEVERSANDPREIS = 106;

/**
 * @removed in 5.0.0
 */
const HOOK_TOOLSGLOBAL_INC_SWITCH_PARSENEWSTEXT = 107;

/**
 *
 */
const HOOK_TOOLSGLOBAL_INC_SWITCH_SETZESPRACHEUNDWAEHRUNG_SPRACHE = 108;

/**
 * at the end of setzeSpracheUndWaehrungLink()
 *
 * @param JTL\Filter\ProductFilter         $oNaviFilter
 * @param null|JTL\Filter\FilterInterface  $oZusatzFilter
 * @param array                            $cSprachURL
 * @param null|JTL\Catalog\Product\Artikel $oAktuellerArtikel
 * @param int                              $kSeite
 * @param int                              $kLink
 * @param int                              $AktuelleSeite
 */
const HOOK_TOOLSGLOBAL_INC_SETZESPRACHEUNDWAEHRUNG_WAEHRUNG = 109;

/**
 * after loading an article
 *
 * @file Artikel.php
 * @param JTL\Catalog\Product\Artikel $oArtikel
 * @param array                       $cacheTags - list of associated cache tags (since 4.0)
 * @param bool                        $cached - true when fetched from object cache (since 4.0)
 */
const HOOK_ARTIKEL_CLASS_FUELLEARTIKEL = 110;

/**
 * at the end of Attribut::loadFromDB()
 */
const HOOK_ATTRIBUT_CLASS_LOADFROMDB = 111;

/**
 * at the end of Bestellung::fuelleBestellung()
 *
 * @file Bestellung.php
 * @param JTL\Checkout\Bestellung $oBestellung (@since 4.05)
 */
const HOOK_BESTELLUNG_CLASS_FUELLEBESTELLUNG = 112;

/**
 * at the end of holeHilfreichsteBewertung()
 *
 * @file Bewertung.php
 */
const HOOK_BEWERTUNG_CLASS_HILFREICHSTEBEWERTUNG = 113;

/**
 * in holeProduktBewertungen()
 *
 * @file Bewertung.php
 */
const HOOK_BEWERTUNG_CLASS_SWITCH_SORTIERUNG = 114;

/**
 * after loading a rating
 *
 * @file Bewertung.php
 * @param JTL\Catalog\Product\Bewertung $oBewertung
 */
const HOOK_BEWERTUNG_CLASS_BEWERTUNG = 115;

/**
 * @file Eigenschaft.php
 */
const HOOK_EIGENSCHAFT_CLASS_LOADFROMDB = 116;

/**
 * @file EigenschaftsWert.php
 */
const HOOK_EIGENSCHAFTWERT_CLASS_LOADFROMDB = 117;

/**
 * after loading a company from the database
 *
 * @file Firma.php
 * @param JTL\Firma $instance - since 5.0.0
 * @param bool      $cached - since 5.2.0
 */
const HOOK_FIRMA_CLASS_LOADFROMDB = 118;

/**
 * after loading a manufacturer from the database
 *
 * @file Hersteller.php
 * @param JTL\Catalog\Hersteller $oHersteller
 * @param array                  $cacheTags - list of associated cache tags (since 4.0)
 * @param bool                   $cached - true if fetched from object cache (since 4.0)
 */
const HOOK_HERSTELLER_CLASS_LOADFROMDB = 119;

/**
 * @file Kategorie.php
 * @param Kategorie $oKategorie
 * @param array     $cacheTags - list of associated cache tags  (since 4.0)
 * @param bool      $cached - true if fetched from object cache  (since 4.0)
 */
const HOOK_KATEGORIE_CLASS_LOADFROMDB = 120;

/**
 * @file Kunde.php
 */
const HOOK_KUNDE_CLASS_LOADFROMDB = 121;

/**
 * @file Lieferadresse.php
 */
const HOOK_LIEFERADRESSE_CLASS_LOADFROMDB = 122;

/**
 * @file Merkmal.php
 * @param JTL\Catalog\Product\Merkmal $instance - since 5.0.0
 */
const HOOK_MERKMAL_CLASS_LOADFROMDB = 123;

/**
 * @file MerkmalWert.php
 * @param JTL\Catalog\Product\MerkmalWert $oMerkmalWert
 */
const HOOK_MERKMALWERT_CLASS_LOADFROMDB = 124;

/**
 * after loading a delivery address from the database
 * @file Rechnungsadresse.php
 */
const HOOK_RECHNUNGSADRESSE_CLASS_LOADFROMDB = 125;

/**
 * after adding an article to the cart
 *
 * @file Warenkorb.php
 * @param int   $kArtikel
 * @param array $oPosition_arr
 * @param float $nAnzahl
 * @param bool  $exists
 */
const HOOK_WARENKORB_CLASS_FUEGEEIN = 126;

/**
 * after adding an article to the wishlist
 *
 * @file Wunschliste.php
 */
const HOOK_WUNSCHLISTE_CLASS_FUEGEEIN = 127;

/**
 * after adding an article to the compare list
 *
 * @file Vergleichsliste.php
 */
const HOOK_VERGLEICHSLISTE_CLASS_EINFUEGEN = 128;

/**
 * after checking current link type
 *
 * @file seite.php
 */
const HOOK_SEITE_PAGE_IF_LINKART = 129;

/**
 * at the end of setzeSmartyWeiterleitung()
 *
 * @file bestellabschluss_inc.php
 */
const HOOK_BESTELLABSCHLUSS_INC_SMARTYWEITERLEITUNG = 130;

/**
 * after global includes (when not using JTL_INCLUDE_ONLY_DB)
 *
 * @file globalinclude.php
 */
const HOOK_GLOBALINCLUDE_INC = 131;

/**
 * at the very beginning to catch POST/GET params
 *
 * @file index.php|navi.php
 */
const HOOK_INDEX_NAVI_HEAD_POSTGET = 132;

/**
 * after instanciating JTLSmarty
 *
 * @file smartyInclude.php
 * @param \JTL\Smarty\JTLSmarty $smarty
 */
const HOOK_SMARTY_INC = 133;

/**
 * at the beginning of holeJobs()
 *
 * @file lastjobs.php
 * @param array $jobs - since 5.0.0
 */
const HOOK_LASTJOBS_HOLEJOBS = 134;

/**
 * @deprecated since 5.0.0
 */
const HOOK_NICEDB_CLASS_EXECUTEQUERY = 135;

/**
 * after sending an email
 */
const HOOK_MAILTOOLS_VERSCHICKEMAIL_GESENDET = 136;

/**
 * before writing the fetched output
 */
const HOOK_DO_EXPORT_OUTPUT_FETCHED = 137;

/**
 * at the beginning of bearbeite() in dbeS
 *
 * @param string $Pfad
 * @param array  $Artikel
 * @param array  $Kategorie
 * @param array  $Eigenschaftswert
 * @param array  $Hersteller
 * @param array  $Merkmalwert
 * @param array  $Merkmal
 * @param array  $Konfiggruppe
 */
const HOOK_BILDER_XML_BEARBEITE = 138;

/**
 * before writing fetched output
 */
const HOOK_CRON_EXPORTFORMATE_OUTPUT_FETCHED = 139;

/**
 * at the end of smarty outputfilter
 *
 * @file JTLSmarty.php
 * @param \JTL\Smarty\JTLSmarty        $smarty
 * @param \JTL\phpQuery\phpQueryObject $document
 */
const HOOK_SMARTY_OUTPUTFILTER = 140;

/**
 * after deleting of all special positions in the cart
 *
 * @file warenkorb_inc.php
 */
const HOOK_WARENKORB_LOESCHE_ALLE_SPEZIAL_POS = 141;

/**
 * at the beginning of Shop::seoCheck()
 *
 * @file class.core.Shop.php
 */
const HOOK_SEOCHECK_ANFANG = 142;

/**
 * at the end of Shop::seoCheck()
 *
 * @file class.core.Shop.php
 */
const HOOK_SEOCHECK_ENDE = 143;

/**
 * after defining $cSh and $cPh
 *
 * @file notify.php
 */
const HOOK_NOTIFY_HASHPARAMETER_DEFINITION = 144;

/**
 * in holLoginKunde() after loading a customer, before decryption
 *
 * @file Kunde.php
 * @param JTL\Customer\Customer $oKunde
 * @param stdClass              $oUser
 * @param string                $cBenutzername
 * @param string                $cPasswort
 */
const HOOK_KUNDE_CLASS_HOLLOGINKUNDE = 145;

/**
 * when no link if found for seo string
 *
 * @file index.php|navi.php
 * @param string $seo
 */
const HOOK_INDEX_SEO_404 = 146;

/**
 * triggered when checkbox has plugin special functions and is checked by a customer
 *
 * @file CheckBox.php
 * @param JTL\CheckBox $oCheckBox
 */
const HOOK_CHECKBOX_CLASS_TRIGGERSPECIALFUNCTION = 147;

/**
 * at the beginning of gibNaviMetaDescription()
 *
 * @file filter_inc.php
 */
const HOOK_FILTER_INC_GIBNAVIMETADESCRIPTION = 148;

/**
 * at the beginning of gibNaviMetaKeywords()
 *
 * @file filter_inc.php
 */
const HOOK_FILTER_INC_GIBNAVIMETAKEYWORDS = 149;

/**
 * at the beginning of gibNaviMetaTitle()
 *
 * @file filter_inc.php
 */
const HOOK_FILTER_INC_GIBNAVIMETATITLE = 150;

/**
 * in bearbeiteInsert() after inserting an article into the database
 *
 * @file Artikel_xml.php
 * @param stdClass $oArtikel
 */
const HOOK_ARTIKEL_XML_BEARBEITEINSERT = 151;

/**
 * in bearbeiteDeletes() after deleting an article from the database
 *
 * @file Artikel_xml.php
 * @param int $kArtikel - product ID
 */
const HOOK_ARTIKEL_XML_BEARBEITEDELETES = 152;

/**
 * in sendeMail() before actually sending an email
 *
 * @param \JTL\Smarty\JTLSmarty                $mailsmarty
 * @param \JTL\Mail\Mail\MailInterface         $mail - MailInterface since 5.0.0
 * @param int                                  $kEmailvorlage - 0 since 5.0.0
 * @param int                                  $kSprache
 * @param string                               $cPluginBody - empty string since 5.0.0
 * @param object                               $Emailvorlage - null since 5.0.0
 * @param \JTL\Mail\Template\TemplateInterface $template - since 5.0.0
 */
const HOOK_MAILTOOLS_SENDEMAIL_ENDE = 153;

/**
 * at the end of mappeBestellvorgangZahlungshinweis() when creating payment method notice
 *
 * @file bestellvorgang_inc.php
 * @param string $cHinweis
 * @param int    $nHinweisCode
 */
const HOOK_BESTELLVORGANG_INC_MAPPEBESTELLVORGANGZAHLUNGSHINWEIS = 154;

/**
 * @deprecated since 5.0.0
 */
const HOOK_TOOLSAJAX_SERVER_ADMIN = 155;

/**
 * after the creating of a new session - $_SESSION is available
 */
const HOOK_CORE_SESSION_CONSTRUCTOR = 156;

/**
 * at the end of gibBelieferbareLaender()
 *
 * @param array $oLaender_arr - array of countries
 */
const HOOK_TOOLSGLOBAL_INC_GIBBELIEFERBARELAENDER = 157;

/**
 * after executing job
 *
 * @file jobqueue_inc.php
 * @param JTL\Cron\QueueEntry     $oJobQueue
 * @param JTL\Cron\Job            $job
 * @param Psr\Log\LoggerInterface $logger
 */
const HOOK_JOBQUEUE_INC_BEHIND_SWITCH = 158;

/**
 * after filling an order
 *
 * @file Bestellungen_xml.php
 * @param stdClass              $oBestellung - order object
 * @param JTL\Customer\Customer $oKunde - customer object
 * @param stdClass              $oBestellungWawi
 */
const HOOK_BESTELLUNGEN_XML_BEARBEITESET = 159;

/**
 * before intercepting search
 *
 * @param array|string $cValue - search string
 * @param bool         $bExtendedJTLSearch
 */
const HOOK_NAVI_PRESUCHE = 160;

/**
 * before building article count
 *
 * @param bool          $bExtendedJTLSearch
 * @param null|stdClass $oExtendedJTLSearchResponse
 * @param string        $cValue
 * @param int           $nArtikelProSeite
 * @param int           $nSeite
 * @param int           $nSortierung
 * @param bool          $bLagerbeachten
 */
const HOOK_NAVI_SUCHE = 161;

/**
 * at the beginning of gibArtikelabhaengigeVersandkosten()
 *
 * @param JTL\Catalog\Product\Artikel $oArtikel
 * @param string                      $cLand
 * @param int|float                   $nAnzahl
 * @param bool                        $bHookReturn
 */
const HOOK_TOOLS_GLOBAL_GIBARTIKELABHAENGIGEVERSANDKOSTEN = 162;

/**
 * at the beginning of pruefeArtikelabhaengigeVersandkosten()
 *
 * @param JTL\Catalog\Product\Artikel $oArtikel
 * @param bool                        $bHookReturn
 */
const HOOK_TOOLS_GLOBAL_PRUEFEARTIKELABHAENGIGEVERSANDKOSTEN = 163;

/**
 * after urlNotFoundRedirect() and setting 404 header
 *
 * @param bool $isFileNotFound
 */
const HOOK_PAGE_NOT_FOUND_PRE_INCLUDE = 164;

/**
 * after the creating of the sitemap
 *
 * @file sitemapexport.php
 * @param JTL\Sitemap\Export $instance
 * @param array              $nAnzahlURL_arr
 * @param float              $fTotalZeit
 */
const HOOK_SITEMAP_EXPORT_GENERATED = 165;

/**
 * before putting an article into the cart at the beginning of checkeWarenkorbEingang()
 *
 * @param int   $kArtikel
 * @param float $fAnzahl
 */
const HOOK_TOOLS_GLOBAL_CHECKEWARENKORBEINGANG_ANFANG = 166;

/**
 * after putting an article onto the wishlist in checkeWarenkorbEingang()
 *
 * @param int                         $kArtikel - the article ID
 * @param float                       $fAnzahl - the amount of this article
 * @param JTL\Catalog\Product\Artikel $AktuellerArtikel - the current article
 */
const HOOK_TOOLS_GLOBAL_CHECKEWARENKORBEINGANG_WUNSCHLISTE = 167;

/**
 * @deprecated since 5.0.0
 */
const HOOK_NICEDB_CLASS_INSERTROW = 168;

/**
 * at the end of filter.php before displaying article list
 *
 * @file filter.php
 */
const HOOK_FILTER_ENDE = 169;

/**
 * at the end of navi.php before displaying article list
 *
 * @file navi.php
 */
const HOOK_NAVI_ENDE = 170;

/**
 * in bearbeiteHerstellerDeletes() after deleting manufacturers from the database
 *
 * @param int $kHersteller - manufacturer ID
 */
const HOOK_HERSTELLER_XML_BEARBEITEDELETES = 171;

/**
 * in bearbeiteDeletes() after deleting categories from the database
 *
 * @param int $kKategorie - category ID
 */
const HOOK_KATEGORIE_XML_BEARBEITEDELETES = 172;

/**
 * in bearbeiteInsert() when inserting manufacturers into the database
 *
 * @param stdClass $oHersteller - manufacturer object
 */
const HOOK_HERSTELLER_XML_BEARBEITEINSERT = 173;

/**
 * in bearbeiteInsert() when inserting categories into the database
 *
 * @param stdClass $oKategorie - category object
 */
const HOOK_KATEGORIE_XML_BEARBEITEINSERT = 174;

/**
 * before assigning css/js resources to smarty
 *
 * @file letzterInclude.php
 * @param array $cCSS_arr - template css
 * @param array $cJS_arr - template js
 * @param array $cPluginCss_arr - plugin css
 * @param array $cPluginCssConditional_arr - plugin css with condition
 * @param array $cPluginJsHead_arr - plugin js for head
 * @param array $cPluginJsBody_arr - plugin js for body
 */
const HOOK_LETZTERINCLUDE_CSS_JS = 175;

/**
 * before inserting newsletter recipients into the database
 *
 * @param stdClass $oNewsletterEmpfaengerHistory
 */
const HOOK_NEWSLETTER_PAGE_HISTORYEMPFAENGEREINTRAGEN = 176;

/**
 * at the end of baueArtikelAnzahl()
 *
 * @deprecated since 5.0.0
 */
const HOOK_FILTER_INC_BAUEARTIKELANZAHL = 177;

/**
 * @param array                    $oArtikelKey_arr
 * @param stdClass                 $FilterSQL
 * @param JTL\Filter\ProductFilter $NaviFilter
 * @param stdClass                 $orderData
 * @param bool                     $bExtendedJTLSearch
 * @param null|stdClass            $oExtendedJTLSearchResponse
 */
const HOOK_FILTER_INC_GIBARTIKELKEYS = 178;

/**
 * after getting images array in dbeS
 * @param array $Kategorie
 * @param array $Eigenschaftswert
 * @param array $Hersteller
 * @param array $Merkmalwert
 * @param array $Merkmal
 * @param array $Konfiggruppe
 */
const HOOK_BILDER_XML_BEARBEITE_ENDE = 179;

/**
 * after getting all check boxes
 *
 * @file CheckBox.php
 * @param array $oCheckBox_arr
 * @param int   $nAnzeigeOrt
 * @param int   $kKundengruppe
 * @param bool  $bAktiv
 * @param bool  $bSprache
 * @param bool  $bSpecial
 * @param bool  $bLogging
 */
const HOOK_CHECKBOX_CLASS_GETCHECKBOXFRONTEND = 180;

/**
 * before updating the order status
 *
 * @file Bestellungen_xml.php
 * @param int      $status
 * @param stdClass $oBestellung
 */
const HOOK_BESTELLUNGEN_XML_BESTELLSTATUS = 181;

/**
 * @deprecated since 5.0.0
 */
const HOOK_SMARTY_OUTPUTFILTER_MOBILE = 182;

/**
 * @deprecated since 5.0.0
 */
const HOOK_FILTER_INC_GIBARTIKELKEYS_SQL = 183;

/**
 * @deprecated since 5.0.0
 */
const HOOK_FILTER_INC_BAUFILTERSQL = 184;

/**
 * after flushing cache ID/tag
 *
 * @since 4.0
 * @file JTLCache.php
 */
const HOOK_CACHE_FLUSH_AFTER = 200;

/**
 * @deprecated since 5.0.0
 */
const HOOK_SMARTY_OUTPUTFILTER_CACHE = 202;

/**
 * @deprecated since 5.0.0
 */
const HOOK_SMARTY_GENERATE_CACHE_ID = 203;

/**
 * List of all js/css groups to minify after generation via Template::getMinifyArray()
 *
 * @param array $groups - list of tpl groups
 * @param array $cache_tags - list of associated cache tags
 * @since 4.0
 */
const HOOK_CSS_JS_LIST = 204;

/**
 * before deleting a position from the cart
 *
 * @param int               $nPos - the position's index
 * @param JTL\Cart\CartItem $position - the position itself
 * @since 4.0
 * @file warenkob_inc.php
 */
const HOOK_WARENKORB_LOESCHE_POSITION = 205;

/**
 * before deleting images in dbeS
 *
 * @param array $Artikel - kArtikel
 * @param array $Kategorie - kKategorie
 * @param array $Eigenschaftswert - kEigeschaftswert
 * @param array $Hersteller - kHersteller
 * @param array $Merkmalwert - kMerkmal
 * @param array $Merkmal - kMerkmalwert
 * @since 4.0
 * @file Bilder_xml.php
 */
const HOOK_BILDER_XML_BEARBEITEDELETES = 206;

/**
 * after inserting order into the database
 *
 * @param stdClass $oBestellung - order object
 * @param stdClass $bestellID - bestellid object
 * @param stdClass $bestellstatus - order status
 * @since 4.0
 * @file bestellabschluss_inc.php
 */
const HOOK_BESTELLABSCHLUSS_INC_BESTELLUNGINDB_ENDE = 207;

/**
 * @param string $original
 * @param string $custom
 * @param string $fallback
 * @param string $out
 * @param bool   $transform
 * @since 4.0
 * @file JTLSmarty.php
 */
const HOOK_SMARTY_FETCH_TEMPLATE = 208;

/**
 * @param stdClass              $oBestellung
 * @param stdClass              $oBestellungAlt
 * @param JTL\Customer\Customer $oKunde
 * @since 4.0
 * @file Bestellungen_xml.php
 */
const HOOK_BESTELLUNGEN_XML_BEARBEITEUPDATE = 209;

/**
 * after canceling an order
 *
 * @param JTL\Checkout\Bestellung $oBestellung
 * @param JTL\Customer\Customer   $oKunde
 * @param bool|PaymentMethod      $oModule
 * @since 4.0
 * @file Bestellungen_xml.php
 */
const HOOK_BESTELLUNGEN_XML_BEARBEITESTORNO = 210;

/**
 * @deprecated since 5.0.0
 */
const HOOK_BUILD_LINK_GROUPS = 211;

/**
 * @deprecated since 5.0.0
 */
const HOOK_GET_PAGE_LINK_LANGUAGE = 212;

/**
 * before handling the request
 *
 * @param JTL\IO\IO $io
 * @param string    $request
 * @since 4.0
 * @file io.php
 */
const HOOK_IO_HANDLE_REQUEST = 213;

/**
 * after setting the current page type
 *
 * @param int    $pageType
 * @param string $pageName
 * @since 4.0
 * @file class.core.Shop.php
 */
const HOOK_SHOP_SET_PAGE_TYPE = 214;

/**
 * immediately before storing kunde in DB
 *
 * @param JTL\Customer\Customer $oKunde
 * @since 4.03
 */
const HOOK_KUNDE_DB_INSERT = 215;

/**
 * @param \Intervention\Image\Image $image
 * @param array                     $settings
 * @param string                    $thumbnail
 * @since 4.03
 * @file Image.php
 */
const HOOK_IMAGE_RENDER = 216;

/**
 * @deprecated since 5.0.0
 */
const HOOK_NAVI_CREATE = 217;

/**
 * @param int    $min
 * @param int    $max
 * @param string $text
 * @since 4.03
 */
const HOOK_GET_DELIVERY_TIME_ESTIMATION_TEXT = 218;

/**
 * @param array $categories
 * @since 4.03
 */
const HOOK_GET_ALL_CATEGORIES = 219;

/**
 * @param Illuminate\Support\Collection $oNews_arr
 * @param array                         $cacheTags
 * @param bool                          $cached
 * @since 4.04
 */
const HOOK_GET_NEWS = 220;

/**
 * @param int    $filterType
 * @param string $filterSQL
 * @since 4.04
 */
const HOOK_STOCK_FILTER = 221;

/**
 * @param stdClass   $oAccount
 * @param string     $type - VALIDATE|SAVE|LOCK|UNLOCK|DELETE
 * @param array|null $attribs - extended attributes (only used if type == VALIDATE or SAVE)
 * @param array      $messages
 * @param bool|array $result - true if success otherwise errormap
 * @since 4.05
 * @file admin/includes/benutzerverwaltung_inc.php
 */
const HOOK_BACKEND_ACCOUNT_EDIT = 222;

/**
 * @param stdClass              $oAccount
 * @param \JTL\Smarty\JTLSmarty $smarty
 * @param array                 $attribs - extended attributes
 * @param string                $content
 * @since 4.05
 * @file admin/includes/benutzerverwaltung_inc.php
 */
const HOOK_BACKEND_ACCOUNT_PREPARE_EDIT = 223;

/**
 * @parem array boxes
 */
const HOOK_BOXEN_HOME = 224;

/**
 * in bearbeiteInsert() after inserting an article into the database
 *
 * @file QuickSync_xml.php
 * @param stdClass $oArtikel
 */
const HOOK_QUICKSYNC_XML_BEARBEITEINSERT = 225;

/**
 * after getting list of all manufacturers
 *
 * @param bool  $cached
 * @param array $cacheTags
 * @param array $manufacturers
 * @since 4.05
 * @file class.helper.Hersteller.php
 */
const HOOK_GET_MANUFACTURERS = 226;

/**
 * @param \JTL\Backend\AdminAccount $oAdminAccount
 * @param string                    $url
 * @since 4.06
 */
const HOOK_BACKEND_FUNCTIONS_GRAVATAR = 227;

/**
 * @param JTL\Cart\Cart           $oWarenkorb
 * @param JTL\Checkout\Bestellung $oBestellung
 * @since 4.06
 * @file includes/bestellabschluss_inc.php
 */
const HOOK_BESTELLABSCHLUSS_INC_WARENKORBINDB = 228;

/**
 * after truncating tables in database
 *
 * @since 4.06
 * @file admin/shopzuruecksetzen.php
 */
const HOOK_BACKEND_SHOP_RESET_AFTER = 229;

/**
 * on removing a cart position that has been deactivated / deleted in the meantime
 *
 * @param JTL\Cart\CartItem $oPosition
 * @param bool              $delete
 * @since 5.0.0
 */
const HOOK_WARENKORB_CLASS_LOESCHEDEAKTIVIERTEPOS = 230;

/**
 * before the ordernumber is returned from baueBestellnummer().
 *
 * @param int    $orderNo
 * @param string $prefix
 * @param string $suffix
 * @since 4.06.14
 * @file includes/bestellabschluss_inc.php
 */
const HOOK_BESTELLABSCHLUSS_INC_BAUEBESTELLNUMMER = 231;

/**
 * @param stdClass              $oBestellung
 * @param JTL\Customer\Customer $oKunde
 * @since 5.3.0
 * @file Bestellungen_xml.php
 */
const HOOK_BESTELLUNGEN_XML_BEARBEITEINSERT = 232;

/**
 * in ProductFilter::initBaseStates() after initializing the base filters
 *
 * @param \JTL\Filter\ProductFilter $productFilter
 * @since 5.0.0
 * @file includes/src/Filter/ProductFilter.php
 */
const HOOK_PRODUCTFILTER_INIT = 250;

/**
 * in ProductFilter::initStates() after initializing the active filters
 *
 * @param \JTL\Filter\ProductFilter $productFilter
 * @param array                     $params
 * @since 5.0.0
 * @file includes/src/Filter/ProductFilter.php
 */
const HOOK_PRODUCTFILTER_INIT_STATES = 251;

/**
 * in ProductFilter::construct() when creating the instance
 *
 * @param \JTL\Filter\ProductFilter $productFilter
 * @since 5.0.0
 * @file includes/src/Filter/ProductFilter.php
 */
const HOOK_PRODUCTFILTER_CREATE = 252;

/**
 * in ProductFilter::construct() when creating the instance
 *
 * @param array                     $select
 * @param array                     $joins
 * @param array                     $conditions
 * @param array                     $groupBy
 * @param array                     $having
 * @param array                     $order
 * @param array                     $limit
 * @param \JTL\Filter\ProductFilter $productFilter
 * @since 5.0.0
 * @file Filter/ProductFilter.php
 */
const HOOK_PRODUCTFILTER_GET_BASE_QUERY = 253;

/**
 * @param JTL\Filter\SortingOptions\Factory $factory
 * @param \JTL\Filter\ProductFilter         $productFilter
 * @since 5.0.0
 */
const HOOK_PRODUCTFILTER_REGISTER_SEARCH_OPTION = 254;

/**
 * in Preise::__construct()
 *
 * @param int    $customerGroupID
 * @param int    $customerID
 * @param int    $productID
 * @param int    $taxClassID
 * @param Preise $prices
 * @since 5.0.0
 * @file Preise.php
 */
const HOOK_PRICES_CONSTRUCT = 260;

/**
 * in WarenkorbHelper::addToCartCheck()
 *
 * @param Artikel $product
 * @param int     $quantity
 * @param array   $attributes
 * @param int     $accuracy
 * @param array   $redirectParam
 * @since 5.0.0
 * @file WarenkorbHelper.php
 */
const HOOK_ADD_TO_CART_CHECK = 261;

/**
 * in WarenkorbHelper::setzePositionsPreise()
 *
 * @param mixed $position
 * @param mixed $oldPosition
 * @since 5.0.0
 * @file Warenkorb.php
 */
const HOOK_SETZTE_POSITIONSPREISE = 262;

/**
 * in CaptchaService::isConfigured
 *
 * @param bool $isConfigured
 * @since 5.0.0
 * @file src/Services/CaptchaService.php
 */
const HOOK_CAPTCHA_CONFIGURED = 270;

/**
 * in CaptchaService::getHeadMarkup, CaptchaService::getBodyMarkup
 *
 * @param bool   $getBody
 * @param string $markup
 * @since 5.0.0
 * @file src/Services/CaptchaService.php
 */
const HOOK_CAPTCHA_MARKUP = 271;

/**
 * in CaptchaService::validate
 *
 * @param array $requestData
 * @param bool  $isValid
 * @since 5.0.0
 * @file src/Services/CaptchaService.php
 */
const HOOK_CAPTCHA_VALIDATE = 272;

/**
 * @param Plugin $plugin
 * @param bool   $hasError
 * @param string $msg
 * @param string $error
 * @param array  $options
 * @since 5.0.0
 * @file admin/plugin.php
 */
const HOOK_PLUGIN_SAVE_OPTIONS = 280;

/**
 * @param \JTL\Sitemap\Factories\FactoryInterface[] $factories
 * @param \JTL\Sitemap\Export                       $instance
 * @since 5.0.0
 * @file includes/src/Sitemap/Export.php
 */
const HOOK_SITEMAP_EXPORT_GENERATE = 285;

/**
 * @param \JTL\Sitemap\Export $instance
 * @since 5.0.0
 * @file includes/src/Sitemap/Export.php
 */
const HOOK_SITEMAP_EXPORT_INIT = 286;

/**
 * @param \JTL\Mail\Mailer             $mailer
 * @param \JTL\Mail\Mail\MailInterface $mail
 * @since 5.0.0
 * @file includes/src/Mail/Mailer.php
 */
const HOOK_MAIL_PRERENDER = 290;

/**
 * @param \JTL\Mail\Mailer               $mailer
 * @param \JTL\Mail\Mail\MailInterface   $mail
 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer
 * @since 5.1.2
 * @file includes/src/Mail/Mailer.php
 */
const HOOK_MAILER_PRE_SEND = 291;

/**
 * @param \JTL\Mail\Mailer               $mailer
 * @param \JTL\Mail\Mail\MailInterface   $mail
 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer
 * @param bool                           $status
 * @since 5.1.2
 * @file includes/src/Mail/Mailer.php
 */
const HOOK_MAILER_POST_SEND = 292;

/**
 * @param \JTL\Smarty\JTLSmarty                     $smarty
 * @param string                                    $templateID
 * @param \JTL\Mail\Template\TemplateInterface|null $template
 * @since 5.5.0
 * @file includes/src/Mail/Validator/SyntaxChecker.php
 */
const HOOK_MAIL_SYNTAX_CHECK = 293;


/**
 * @param array $data
 * @since 5.0.0
 * @file includes/src/Link/Link.php
 */
const HOOK_LINK_PRE_MAP = 300;

/**
 * @param \JTL\Link\Link $link
 * @since 5.0.0
 * @file includes/src/Link/Link.php
 */
const HOOK_LINK_MAPPED = 301;

/**
 * @param \JTL\Link\LinkGroup $group
 * @since 5.0.0
 * @file includes/src/Link/LinkGroup.php
 */
const HOOK_LINKGROUP_MAPPED = 302;

/**
 * @param \JTL\Link\LinkGroupList $list
 * @since 5.0.0
 * @file includes/src/Link/LinkGroupList.php
 */
const HOOK_LINKGROUPS_LOADED = 303;

/**
 * Kurz vor dem Einfügen einer neuen/bisher unbekannten Lieferadresse in die DB
 *
 * @param JTL\Checkout\Lieferadresse $deliveryAddress
 * @since 5.0.0
 * @file bestellabschluss_inc.php
 */
const HOOK_BESTELLABSCHLUSS_INC_BESTELLUNGINDB_LIEFERADRESSE_NEU = 304;

/**
 * Zuordnung einer bekannten Lieferadresse zu der Bestellung, beim Einfügen einer Bestellung in die DB.
 *
 * @param int $deliveryAddressID - Key der Lieferadresse als Integer
 * @since 5.0.0
 * @file bestellabschluss_inc.php
 */
const HOOK_BESTELLABSCHLUSS_INC_BESTELLUNGINDB_LIEFERADRESSE_ALT = 305;

/**
 * @param \JTL\Link\LinkGroupList $list
 * @since 5.0.0
 * @file includes/src/Link/LinkGroupList.php
 */
const HOOK_LINKGROUPS_LOADED_PRE_CACHE = 306;

/**
 * @param float             $price
 * @param Versandart|object $shippingMethod
 * @param string            $iso
 * @param Artikel|stdClass  $additionalProduct
 * @param Artikel|null      $product
 * @since 5.0.0
 * @file includes/src/Helpers/ShippingMethod.php
 * @deprecated since 5.5.0. Use HOOK_CALCULATE_SHIPPING_COSTS instead.
 */
const HOOK_CALCULATESHIPPINGFEES = 307;

/**
 * @param int                 $productID
 * @param JTL\Cart\CartItem[] $positionItems
 * @param float               $qty
 * @since 5.0.0
 * @file includes/src/Cart/Cart.php
 */
const HOOK_WARENKORB_ERSTELLE_SPEZIAL_POS = 310;

/**
 * @param \JTL\Backend\AdminIO $io
 * @param string               $request
 * @since 5.0.0
 * @file IOController.php
 */
const HOOK_IO_HANDLE_REQUEST_ADMIN = 311;

/**
 * @param Illuminate\Support\Collection $items - collection of JTL\Consent\ConsentModel\ConsentModel
 * @since 5.0.0
 * @file Manager.php
 */
const CONSENT_MANAGER_GET_ACTIVE_ITEMS = 320;

/**
 * @param float|string $netPrice
 * @param float|string $defaultTax
 * @param float|string $conversionTax
 * @param float|string $newNetPrice
 * @since 5.1.0
 * @file Preise.php
 */
const HOOK_RECALCULATED_NET_PRICE = 321;

/**
 * @param float|string $price
 * @param mixed        $currency
 * @param bool         $html
 * @param int          $decimals
 * @param string       $currencyName
 * @param string       $localized
 * @since 5.1.0
 * @file Preise.php
 */
const HOOK_LOCALIZED_PRICE_STRING = 330;

/**
 * @param array $sum
 * @since 5.1.0
 * @file Cart.php
 */
const HOOK_CART_GET_LOCALIZED_SUM = 331;

/**
 * @param float &$creditToUse
 * @param float  $cartTotal
 * @param float  $customerCredit
 * @since 5.1.0
 * @file includes/src/Helpers/Order.php
 */
const HOOK_BESTELLUNG_SETZEGUTHABEN = 335;

/**
 * @param bool   &$sendMails
 * @param object  $product
 * @param array   $subscriptions
 * @since 5.1.3
 * @file includes/src/dbeS/Sync/AbstractSync.php
 */
const HOOK_SYNC_SEND_AVAILABILITYMAILS = 336;

/**
 * @param CartItem  $positionItem
 * @param bool     &$delete
 * @since 5.1.3
 * @file includes/src/Cart/Cart.php
 */
const HOOK_CART_DELETE_PARENT_CART_ITEM = 337;

/**
 * @file includes/src/dbeS/Sync/DeliveryNotes.php
 * @param object $deliveryNote
 */
const HOOK_DELIVERYNOTES_XML_INSERT = 340;

/**
 * @param object $shipping
 * @since 5.1.3
 * @file includes/src/dbeS/Sync/DeliveryNotes.php
 */
const HOOK_DELIVERYNOTES_XML_SHIPPING = 341;

/**
 * @param int $deliveryNoteID
 * @since 5.1.3
 * @file includes/src/dbeS/Sync/DeliveryNotes.php
 */
const HOOK_DELIVERYNOTES_XML_DELETE = 342;

/**
 * @param int $kArtikel
 * @param int $kBild
 * @since 5.3.0
 */
const HOOK_ARTIKELBILD_XML_BEARBEITET = 343;

/**
 * @param int $orderId
 * @since 5.3.0
 */
const HOOK_BESTELLUNGEN_XML_DELETEORDER = 344;

/**
 * @param int                                  $orderId
 * @param bool|JTL\Plugin\Payment\LegacyMethod $module
 * @since 5.3.0
 */
const HOOK_BESTELLUNGEN_XML_HANDLEREACTIVATION = 345;

/**
 * @param int      $productID
 * @param stdClass $product
 * @param array    $xml
 * @since 5.3.0
 */
const HOOK_SYNC_ADDED_PRODUCT = 348;

/**
 * @param JTL\Customer\Customer $customer
 * @param array                 $attributes
 * @since 5.4.0
 */
const HOOK_SYNC_HANDLE_CUSTOMER_INSERTS = 349;

/**
 * @param JTL\Export\Product        $product
 * @param JTL\Export\FormatExporter $exporter
 * @param int                       $exportID
 * @since 5.2.0
 */
const HOOK_EXPORT_PRE_RENDER = 350;

/**
 * @param JTL\Export\FormatExporter $exporter
 * @param int                       $exportID
 * @param int                       $max
 * @param bool                      $isAsync
 * @param bool                      $isCron
 * @since 5.2.0
 */
const HOOK_EXPORT_START = 351;

/**
 * @param JTL\Export\FormatExporter $exporter
 * @param int                       $exportID
 * @param JTL\Export\Model          $model
 * @since 5.2.0
 */
const HOOK_EXPORT_FACTORY_GET_EXPORTER = 352;

/**
 * @param JTL\Catalog\Product\Artikel  $product
 * @param int|string|float            &$qty
 * @since 5.2.0
 */
const HOOK_CARTHELPER_ADD_PRODUCT_ID_TO_CART = 355;

/**
 * @param JTL\News\Item $item
 * @since 5.2.0
 */
const HOOK_NEWS_ITEM_MAPPED = 360;

/**
 * @param JTL\Template\TemplateServiceInterface $service
 * @param array                                 $arguments
 */
const HOOK_TPL_LOAD_PRE = 361;

/**
 * @param int $customerID
 * @since 5.2.0
 */
const HOOK_REGISTRATION_CUSTOMER_CREATED = 362;

/**
 * @param JTL\Router\Router $router
 * @since 5.2.0
 */
const HOOK_ROUTER_PRE_DISPATCH = 400;

/**
 * @param stdClass $oBestellung - order object
 * @param stdClass $oZahlungseingang
 * @since 5.2.0
 */
const HOOK_PAYMENT_METHOD_ADDINCOMINGPAYMENT = 401;

/**
 * @param JTL\Extensions\Download\Download $download
 * @param int                              $customerID
 * @param int                              $orderID
 * @since 5.2.3
 */
const HOOK_ORDER_DOWNLOAD_FILE = 402;

/**
 * @param JTL\Extensions\Config\Item $configItem
 * @param float                      $fVKPreis
 * @since 5.3.0
 */
const HOOK_CONFIG_ITEM_GETPREIS = 403;

/**
 * @param JTL\IO\IOResponse           $response
 * @param JTL\Catalog\Product\Artikel $product
 * @since 5.3.0
 */
const HOOK_IO_CHECK_DEPENDENCIES = 404;

/**
 * @param JTL\Customer\Registration\Validator\ValidatorInterface $validator
 * @since 5.3.0
 */
const HOOK_VALIDATE_REGISTRATION_FORM = 405;

/**
 * @param JTL\Customer\Registration\Validator\ValidatorInterface $validator
 * @since 5.3.0
 */
const HOOK_VALIDATE_SHIPPING_ADDRESS_FORM = 406;

/**
 * @param array $classes - list of class names to be registered
 * @since 5.3.0
 */
const HOOK_RESTAPI_REGISTER_CONTROLLER = 410;

/**
 * @param array    $oArtikelKey_arr
 * @param stdClass $xSelling
 * @since 5.3.2
 */
const HOOK_CARTHELPER_GET_XSELLING = 411;

/**
 * after processing the request to ensure that the response can be modified
 *
 * @param JTL\IO\IO                               $io
 * @param Laminas\Diactoros\Response\JsonResponse $response
 * @since 5.3.2
 */
const HOOK_IO_HANDLE_RESPONSE = 412;

/**
 * in Cart::setzePositionsPreise()
 *
 * @param JTL\Cart\CartItem $position
 * @param JTL\Cart\CartItem $oldPosition
 * @since 5.4.1
 * @file Cart.php
 */
const HOOK_SET_POSITION_PRICES_END = 413;

/**
 * @param JTL\Review\ReviewModel $review
 * @param float                  $reward
 * @since 5.4.0
 */
const HOOK_REVIEWMANAGER_ADDREWARD_START = 415;

/**
 * @param JTL\Review\ReviewModel      $review
 * @param float                       $reward
 * @param JTL\Review\ReviewBonusModel $reviewBonus
 * @since 5.4.0
 */
const HOOK_REVIEWMANAGER_ADDREWARD_END = 416;

/**
 * @param JTL\Review\ReviewModel      $review
 * @param JTL\Review\ReviewBonusModel $reviewBonus
 * @param int                         $customerID
 * @since 5.4.0
 */
const HOOK_BACKEND_REVIEWCONTROLLER_DELETEREWARD = 417;

/**
 * in Cart::loescheSpezialPos()
 *
 * @param JTL\Cart\CartItem $positionItem
 * @param bool              $delete
 * @since 5.5.0
 * @file Cart.php
 */
const HOOK_CART_DELETE_SPECIAL_CART_ITEM = 418;

/**
 * in Cart::loescheSpezialPos()
 *
 * @since 5.5.0
 * @file Cart.php
 */
const HOOK_CART_DELETE_SPECIAL_CART_ITEM_END = 419;

/**
 * @since 5.5.0
 * @file includes/src/Shipping/Services/ShippingService.php
 * @param float                                  $price
 * @param JTL\Shipping\DomainObjects\ShippingDTO $shippingMethod
 * @param string                                 $deliveryCountryISO
 * @param JTL\Catalog\Product\Artikel|null       $product
 * @param array<int, JTL\Cart\CartItem>          $cartItems
 */
const HOOK_CALCULATE_SHIPPING_COSTS = 420;

/**
 * @since 5.5.0
 * @file includes/src/Shipping/Services/ShippingService.php
 * @param float                                  $price
 * @param JTL\Shipping\DomainObjects\ShippingDTO $shippingMethod
 * @param string                                 $deliveryCountryISO
 * @param JTL\Catalog\Product\Artikel|null       $product
 * @param array<int, JTL\Cart\CartItem>          $cartItems
 */
const HOOK_CALCULATE_SHIPPING_COSTS_END = 421;

/**
 * @since 5.5.3
 * @file includes/src/dbeS/Sync/Orders.php
 * @param stdClass      $newItem
 * @param stdClass|null $oldItem
 */
const HOOK_ORDER_XML_UPDATE_CARTITEMS = 422;

/**
 * @since 5.5.3
 * @file includes/src/Mail/Template/AbstractTemplate.php
 * @param JTL\Mail\Template\AbstractTemplate $abstractClass
 * @param JTL\Smarty\JTLSmarty               $smarty
 * @param mixed                              &$data
 */
const HOOK_MAIL_ABSTRACTTEMPLATE_PRERENDER = 423;

/**
 * @since 5.5.3
 * @file includes/src/Mail/Validator/SyntaxChecker.php
 * @param JTL\Mail\Hydrator\HydratorInterface $hydrator
 * @param JTL\Mail\Renderer\RendererInterface $renderer
 * @param JTL\Mail\Template\Model             $model
 */
const HOOK_MAIL_SYNTAXCHECKER_AFTER_HYDRATE = 424;

/**
 * @since 5.5.3
 * @file includes/src/Router/Controller/Backend/PluginController.php
 * @param JTL\Plugin\PluginInterface $plugin
 */
const HOOK_PLUGIN_CONTROLLER_END = 425;

/**
 * @since 5.6.0
 * @file includes/src/Review/ReviewModel.php
 * @param JTL\Review\ReviewModel $review
 */
const HOOK_REVIEWMODEL_DELETE = 426;
