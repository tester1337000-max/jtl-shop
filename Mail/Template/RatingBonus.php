<?php

declare(strict_types=1);

namespace JTL\Mail\Template;

use JTL\Catalog\Product\Preise;
use JTL\Smarty\JTLSmarty;

/**
 * Class RatingBonus
 * @package JTL\Mail\Template
 */
class RatingBonus extends AbstractTemplate
{
    protected ?string $id = \MAILTEMPLATE_BEWERTUNG_GUTHABEN;

    /**
     * @inheritdoc
     */
    public function preRender(JTLSmarty $smarty, mixed $data): void
    {
        parent::preRender($smarty, $data);
        if ($data === null) {
            return;
        }
        $waehrung = $this->db->select('twaehrung', 'cStandard', 'Y');

        $data->oBewertungGuthabenBonus->fGuthabenBonusLocalized = Preise::getLocalizedPriceString(
            $data->oBewertungGuthabenBonus->fGuthabenBonus,
            $waehrung,
            false
        );
        $smarty->assign('oKunde', $data->tkunde)
            ->assign('oBewertungGuthabenBonus', $data->oBewertungGuthabenBonus);
    }
}
