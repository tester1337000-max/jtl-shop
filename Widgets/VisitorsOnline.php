<?php

declare(strict_types=1);

namespace JTL\Widgets;

use JTL\Backend\Permissions;
use JTL\Catalog\Product\Preise;
use JTL\Customer\Visitor;
use JTL\Helpers\Text;
use JTL\Shop;
use stdClass;

/**
 * Class VisitorsOnline
 * @package JTL\Widgets
 */
class VisitorsOnline extends AbstractWidget
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        (new Visitor($this->getDB(), Shop::Container()->getCache()))->archive();
        $this->setPermission(Permissions::STATS_VISITOR_VIEW);
    }

    /**
     * @return stdClass[]
     */
    public function getVisitors(): array
    {
        // clause 'ANY_VALUE' is needed by servers, who has the 'sql_mode'-setting 'only_full_group_by' enabled.
        // this is the default since mysql version >= 5.7.x
        $visitors      = $this->oDB->getObjects(
            'SELECT `otab`.*,
                `tbestellung`.`fGesamtsumme` AS fGesamtsumme, `tbestellung`.`dErstellt` as dErstellt,
                `tkunde`.`cVorname` as cVorname, `tkunde`.`cNachname` AS cNachname,
                `tkunde`.`cNewsletter` AS cNewsletter
            FROM `tbesucher` AS `otab`
                INNER JOIN `tkunde` ON `otab`.`kKunde` = `tkunde`.`kKunde`
                LEFT JOIN `tbestellung` ON `otab`.`kBestellung` = `tbestellung`.`kBestellung`
            WHERE `otab`.`kKunde` != 0
                AND `otab`.`kBesucherBot` = 0
                AND `otab`.`dLetzteAktivitaet` = (
                    SELECT MAX(`tbesucher`.`dLetzteAktivitaet`)
                    FROM `tbesucher`
                    WHERE `tbesucher`.`kKunde` = `otab`.`kKunde`
                )
            UNION
            SELECT
                `tbesucher`.*,
                `tbestellung`.`fGesamtsumme` AS fGesamtsumme, `tbestellung`.`dErstellt` as dErstellt,
                `tkunde`.`cVorname` AS cVorname, `tkunde`.`cNachname` AS cNachname,
                `tkunde`.`cNewsletter` AS cNewsletter
            FROM `tbesucher`
                LEFT JOIN `tbestellung` 
                    ON `tbesucher`.`kBestellung` = `tbestellung`.`kBestellung`
                LEFT JOIN `tkunde` 
                    ON `tbesucher`.`kKunde` = `tkunde`.`kKunde`
            WHERE `tbesucher`.`kBesucherBot` = 0
                AND `tbesucher`.`kKunde` = 0'
        );
        $cryptoService = Shop::Container()->getCryptoService();
        foreach ($visitors as $visitor) {
            $visitor->cNachname       = \trim($cryptoService->decryptXTEA($visitor->cNachname ?? ''));
            $visitor->cEinstiegsseite = Text::filterXSS($visitor->cEinstiegsseite);
            $visitor->cAusstiegsseite = Text::filterXSS($visitor->cAusstiegsseite);
            if ($visitor->kBestellung > 0) {
                $visitor->fGesamtsumme = Preise::getLocalizedPriceString($visitor->fGesamtsumme);
            }
        }

        return $visitors;
    }

    /**
     * @param stdClass[] $visitors
     * @return stdClass
     */
    public function getVisitorsInfo(array $visitors): stdClass
    {
        $info            = new stdClass();
        $info->nCustomer = 0;
        $info->nAll      = \count($visitors);
        foreach ($visitors as $visitor) {
            if ($visitor->kKunde > 0) {
                $info->nCustomer++;
            }
        }
        $info->nUnknown = $info->nAll - $info->nCustomer;

        return $info;
    }

    /**
     * @inheritdoc
     */
    public function getContent(): string
    {
        $visitors = $this->getVisitors();

        return $this->oSmarty->assign('oVisitors_arr', $visitors)
            ->assign('oVisitorsInfo', $this->getVisitorsInfo($visitors))
            ->fetch('tpl_inc/widgets/visitors_online.tpl');
    }
}
