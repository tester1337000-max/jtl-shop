<?php

declare(strict_types=1);

namespace JTL\Mail\Template;

use JTL\Smarty\JTLSmarty;

/**
 * Class OrderUpdated
 * @package JTL\Mail\Template
 */
class OrderUpdated extends OrderShipped
{
    protected ?string $id = \MAILTEMPLATE_BESTELLUNG_AKTUALISIERT;

    /**
     * @inheritdoc
     */
    public function preRender(JTLSmarty $smarty, mixed $data): void
    {
        parent::preRender($smarty, $data);
        if ($data === null) {
            return;
        }
        $moduleID = $data->tbestellung->Zahlungsart->cModulId ?? null;
        if (!empty($moduleID)) {
            $conf = $this->db->getSingleObject(
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

            if ($conf !== null && $conf->kZahlungsart > 0) {
                $smarty->assign('Zahlungsart', $conf);
            }
        }
    }
}
