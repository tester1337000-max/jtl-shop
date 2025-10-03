<?php

declare(strict_types=1);

namespace JTL\Mail\Template;

use JTL\Smarty\JTLSmarty;

/**
 * Class OrderConfirmation
 * @package JTL\Mail\Template
 */
class OrderConfirmation extends OrderShipped
{
    protected ?string $id = \MAILTEMPLATE_BESTELLBESTAETIGUNG;

    /**
     * @inheritdoc
     */
    public function preRender(JTLSmarty $smarty, mixed $data): void
    {
        parent::preRender($smarty, $data);
        if ($data === null) {
            return;
        }
        $smarty->assign('Verfuegbarkeit_arr', $data->cVerfuegbarkeit_arr ?? null);
        $smarty->assign('totalLocalized', $data->totalLocalized ?? null);
        $smarty->assign('payments', $data->payments ?? null);
        $moduleID = $data->tbestellung->Zahlungsart->cModulId ?? null;
        if (!empty($moduleID)) {
            $paymentConf = $this->db->getSingleObject(
                'SELECT tzahlungsartsprache.*
                    FROM tzahlungsartsprache
                    JOIN tzahlungsart
                        ON tzahlungsart.kZahlungsart = tzahlungsartsprache.kZahlungsart
                        AND tzahlungsart.cModulId = :module
                    JOIN tsprache
                        ON tsprache.cISO = tzahlungsartsprache.cISOSprache
                    WHERE tsprache.kSprache = :lid',
                ['module' => $moduleID, 'lid' => $this->languageID]
            );
            if ($paymentConf !== null && $paymentConf->kZahlungsart > 0) {
                $smarty->assign('Zahlungsart', $paymentConf);
            }
        }
    }
}
