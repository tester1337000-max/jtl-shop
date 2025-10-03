<?php

declare(strict_types=1);

namespace JTL\Reset;

use JTL\DB\DbInterface;
use JTL\Router\Controller\Backend\NewsController;

/**
 * Class Reset
 * @package JTL\Reset
 */
readonly class Reset
{
    public function __construct(private DbInterface $db)
    {
    }

    public function doReset(ResetContentType $contentType): self
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0;');
        match ($contentType) {
            ResetContentType::PRODUCTS              => $this->resetProducts(),
            ResetContentType::TAXES                 => $this->resetTaxes(),
            ResetContentType::REVISIONS             => $this->db->query('TRUNCATE trevisions'),
            ResetContentType::NEWS                  => $this->resetNews(),
            ResetContentType::BESTSELLER            => $this->db->query('TRUNCATE tbestseller'),
            ResetContentType::STATS_VISITOR         => $this->resetVisitorStats(),
            ResetContentType::STATS_PRICES          => $this->db->query('TRUNCATE tpreisverlauf'),
            ResetContentType::MESSAGES_AVAILABILITY => $this->db->query('TRUNCATE tverfuegbarkeitsbenachrichtigung'),
            ResetContentType::SEARCH_REQUESTS       => $this->resetSearchRequests(),
            ResetContentType::RATINGS               => $this->resetRatings(),
            ResetContentType::WISHLIST              => $this->resetWishList(),
            ResetContentType::COMPARELIST           => $this->resetCompareList(),
            ResetContentType::CUSTOMERS             => $this->resetCustomers(),
            ResetContentType::ORDERS                => $this->resetOrders(),
            ResetContentType::COUPONS               => $this->resetCoupons(),
            ResetContentType::SETTINGS              => $this->resetSettings()
        };

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1;');

        return $this;
    }

    private function resetCompareList(): void
    {
        $this->db->query('TRUNCATE tvergleichsliste');
        $this->db->query('TRUNCATE tvergleichslistepos');
    }

    private function resetProducts(): void
    {
        $this->db->query('TRUNCATE tartikel');
        $this->db->query('TRUNCATE tartikelabnahme');
        $this->db->query('TRUNCATE tartikelattribut');
        $this->db->query('TRUNCATE tartikelkategorierabatt');
        $this->db->query('TRUNCATE tartikelkonfiggruppe');
        $this->db->query('TRUNCATE tartikelmerkmal');
        $this->db->query('TRUNCATE tartikelpict');
        $this->db->query('TRUNCATE tartikelsichtbarkeit');
        $this->db->query('TRUNCATE tartikelsonderpreis');
        $this->db->query('TRUNCATE tartikelsprache');
        $this->db->query('TRUNCATE tartikelwarenlager');
        $this->db->query('TRUNCATE tattribut');
        $this->db->query('TRUNCATE tattributsprache');
        $this->db->query('TRUNCATE tbild');
        $this->db->query('TRUNCATE teigenschaft');
        $this->db->query('TRUNCATE teigenschaftkombiwert');
        $this->db->query('TRUNCATE teigenschaftsichtbarkeit');
        $this->db->query('TRUNCATE teigenschaftsprache');
        $this->db->query('TRUNCATE teigenschaftwert');
        $this->db->query('TRUNCATE teigenschaftwertabhaengigkeit');
        $this->db->query('TRUNCATE teigenschaftwertaufpreis');
        $this->db->query('TRUNCATE teigenschaftwertpict');
        $this->db->query('TRUNCATE teigenschaftwertsichtbarkeit');
        $this->db->query('TRUNCATE teigenschaftwertsprache');
        $this->db->query('TRUNCATE teinheit');
        $this->db->query('TRUNCATE tkategorie');
        $this->db->query('TRUNCATE tkategorieartikel');
        $this->db->query('TRUNCATE tkategorieattribut');
        $this->db->query('TRUNCATE tkategorieattributsprache');
        $this->db->query('TRUNCATE tkategoriekundengruppe');
        $this->db->query('TRUNCATE tkategoriepict');
        $this->db->query('TRUNCATE tkategoriesichtbarkeit');
        $this->db->query('TRUNCATE tkategoriesprache');
        $this->db->query('TRUNCATE tmediendatei');
        $this->db->query('TRUNCATE tmediendateiattribut');
        $this->db->query('TRUNCATE tmediendateisprache');
        $this->db->query('TRUNCATE tmerkmal');
        $this->db->query('TRUNCATE tmerkmalsprache');
        $this->db->query('TRUNCATE tmerkmalwert');
        $this->db->query('TRUNCATE tmerkmalwertbild');
        $this->db->query('TRUNCATE tmerkmalwertsprache');
        $this->db->query('TRUNCATE tpreis');
        $this->db->query('TRUNCATE tpreisdetail');
        $this->db->query('TRUNCATE tsonderpreise');
        $this->db->query('TRUNCATE txsell');
        $this->db->query('TRUNCATE txsellgruppe');
        $this->db->query('TRUNCATE thersteller');
        $this->db->query('TRUNCATE therstellersprache');
        $this->db->query('TRUNCATE tlieferstatus');
        $this->db->query('TRUNCATE tkonfiggruppe');
        $this->db->query('TRUNCATE tkonfigitem');
        $this->db->query('TRUNCATE tkonfiggruppesprache');
        $this->db->query('TRUNCATE tkonfigitempreis');
        $this->db->query('TRUNCATE tkonfigitemsprache');
        $this->db->query('TRUNCATE twarenlager');
        $this->db->query('TRUNCATE twarenlagersprache');
        $this->db->query('TRUNCATE tuploadschema');
        $this->db->query('TRUNCATE tuploadschemasprache');
        $this->db->query('TRUNCATE tmasseinheit');
        $this->db->query('TRUNCATE tmasseinheitsprache');
        $this->db->query(
            "DELETE FROM tseo
                WHERE cKey = 'kArtikel'
                OR cKey = 'kKategorie'
                OR cKey = 'kMerkmalWert'
                OR cKey = 'kHersteller'"
        );
    }

    private function resetTaxes(): void
    {
        $this->db->query('TRUNCATE tsteuerklasse');
        $this->db->query('TRUNCATE tsteuersatz');
        $this->db->query('TRUNCATE tsteuerzone');
        $this->db->query('TRUNCATE tsteuerzoneland');
    }

    private function resetNews(): void
    {
        foreach ($this->db->getInts('SELECT kNews FROM tnews', 'kNews') as $id) {
            NewsController::deleteImageDir($id);
        }
        $this->db->query('TRUNCATE tnews');
        $this->db->delete('trevisions', 'type', 'news');
        $this->db->query('TRUNCATE tnewskategorie');
        $this->db->query('TRUNCATE tnewskategorienews');
        $this->db->query('TRUNCATE tnewskommentar');
        $this->db->query('TRUNCATE tnewsmonatsuebersicht');
        $this->db->query(
            "DELETE FROM tseo
                WHERE cKey = 'kNews'
                  OR cKey = 'kNewsKategorie'
                  OR cKey = 'kNewsMonatsUebersicht'"
        );
    }

    private function resetVisitorStats(): void
    {
        $this->db->query('TRUNCATE tbesucher');
        $this->db->query('TRUNCATE tbesucherarchiv');
        $this->db->query('TRUNCATE tbesuchteseiten');
    }

    private function resetSearchRequests(): void
    {
        $this->db->query('TRUNCATE tsuchanfrage');
        $this->db->query('TRUNCATE tsuchanfrageerfolglos');
        $this->db->query('TRUNCATE tsuchanfragemapping');
        $this->db->query('TRUNCATE tsuchanfragencache');
        $this->db->query('TRUNCATE tsuchcache');
        $this->db->query('TRUNCATE tsuchcachetreffer');
        $this->db->delete('tseo', 'cKey', 'kSuchanfrage');
    }

    private function resetRatings(): void
    {
        $this->db->query('TRUNCATE tartikelext');
        $this->db->query('TRUNCATE tbewertung');
        $this->db->query('TRUNCATE tbewertungguthabenbonus');
        $this->db->query('TRUNCATE tbewertunghilfreich');
    }

    private function resetWishList(): void
    {
        $this->db->query('TRUNCATE twunschliste');
        $this->db->query('TRUNCATE twunschlistepos');
        $this->db->query('TRUNCATE twunschlisteposeigenschaft');
        $this->db->query('TRUNCATE twunschlisteversand');
    }

    private function resetCustomers(): void
    {
        $this->db->query('TRUNCATE tkunde');
        $this->db->query('TRUNCATE tkundenattribut');
        $this->db->query('TRUNCATE tkundendatenhistory');
        $this->db->query('TRUNCATE tkundenfeld');
        $this->db->query('TRUNCATE tkundenfeldwert');
        $this->db->query('TRUNCATE tkundenherkunft');
        $this->db->query('TRUNCATE tkundenkontodaten');
        $this->db->query('TRUNCATE tlieferadresse');
        $this->db->query('TRUNCATE trechnungsadresse');
        $this->db->query('TRUNCATE twarenkorbpers');
        $this->db->query('TRUNCATE twarenkorbperspos');
        $this->db->query('TRUNCATE twarenkorbpersposeigenschaft');
        $this->db->query('TRUNCATE tpasswordreset');
        $this->db->query('DELETE FROM tbesucher WHERE kKunde > 0');
        $this->db->query('DELETE FROM tbesucherarchiv WHERE kKunde > 0');
        $this->db->query(
            'DELETE tbewertung, tbewertunghilfreich, tbewertungguthabenbonus
                FROM tbewertung
                LEFT JOIN tbewertunghilfreich
                    ON tbewertunghilfreich.kBewertung = tbewertung.kBewertung
                LEFT JOIN tbewertungguthabenbonus
                    ON tbewertungguthabenbonus.kBewertung = tbewertung.kBewertung
                WHERE tbewertung.kKunde > 0'
        );
        $this->db->query('DELETE FROM tgutschein WHERE kKunde > 0');
        $this->db->query('DELETE FROM tnewskommentar WHERE kKunde > 0');
        $this->db->query('DELETE FROM tnewsletterempfaenger WHERE kKunde > 0');
        $this->db->query('DELETE FROM tnewsletterempfaengerhistory WHERE kKunde > 0');
        $this->db->query(
            'DELETE tpreis, tpreisdetail
                FROM tpreis
                LEFT JOIN tpreisdetail ON tpreisdetail.kPreis = tpreis.kPreis
                WHERE kKunde > 0'
        );

        $this->resetWishList();
        $this->resetOrders();
    }

    private function resetOrders(): void
    {
        $this->db->query('TRUNCATE tbestellid');
        $this->db->query('TRUNCATE tbestellstatus');
        $this->db->query('TRUNCATE tbestellung');
        $this->db->query('TRUNCATE tlieferschein');
        $this->db->query('TRUNCATE tlieferscheinpos');
        $this->db->query('TRUNCATE tlieferscheinposinfo');
        $this->db->query('TRUNCATE twarenkorb');
        $this->db->query('TRUNCATE twarenkorbpers');
        $this->db->query('TRUNCATE twarenkorbperspos');
        $this->db->query('TRUNCATE twarenkorbpersposeigenschaft');
        $this->db->query('TRUNCATE twarenkorbpos');
        $this->db->query('TRUNCATE twarenkorbposeigenschaft');
        $this->db->query('TRUNCATE tuploaddatei');
        $this->db->query('TRUNCATE tuploadqueue');
        $this->db->query('TRUNCATE tzahlungsinfo');
        $this->db->query('TRUNCATE tkuponbestellung');
        $this->db->query('TRUNCATE tdownloadhistory');
        $this->db->query('TRUNCATE tbestellattribut');
        $this->db->query('TRUNCATE tzahlungseingang');
        $this->db->query('TRUNCATE tzahlungsession');
        $this->db->query('TRUNCATE tzahlungsid');
        $this->db->query('TRUNCATE tzahlungslog');
        $this->db->query('TRUNCATE tgratisgeschenk');
        $this->db->query('DELETE FROM tbesucher WHERE kBestellung > 0');
        $this->db->query('DELETE FROM tbesucherarchiv WHERE kBestellung > 0');
        $this->db->query('DELETE FROM tcheckboxlogging WHERE kBestellung > 0');

        $this->resetUploadFiles();
    }

    private function resetUploadFiles(): void
    {
        foreach (\glob(\PFAD_UPLOADS . '*') ?: [] as $file) {
            if (\is_file($file) && !\str_starts_with($file, '.')) {
                \unlink($file);
            }
        }
    }

    private function resetCoupons(): void
    {
        $this->db->query('TRUNCATE tkupon');
        $this->db->query('TRUNCATE tkuponbestellung');
        $this->db->query('TRUNCATE tkuponkunde');
        $this->db->query('TRUNCATE tkuponsprache');
    }

    private function resetSettings(): void
    {
        $this->db->query('TRUNCATE teinstellungenlog');
        $this->db->query(
            'UPDATE teinstellungen
                  INNER JOIN teinstellungen_default USING(cName)
                  SET teinstellungen.cWert = teinstellungen_default.cWert'
        );
    }
}
