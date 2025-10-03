<?php

declare(strict_types=1);

use JTL\Campaign;
use JTL\Helpers\Request;
use JTL\Session\Frontend;
use JTL\Shop;

require_once __DIR__ . '/globalinclude.php';

$session = Frontend::getInstance();

// kK   = kKampagne
// kN   = kNewsletter
// kNE  = kNewsletterEmpfaenger
if (
    Request::verifyGPCDataInt('kK') > 0
    && Request::verifyGPCDataInt('kN') > 0
    && Request::verifyGPCDataInt('kNE') > 0
) {
    $campaignID   = Request::verifyGPCDataInt('kK');
    $newsletterID = Request::verifyGPCDataInt('kN');
    $recipientID  = Request::verifyGPCDataInt('kNE');
    // Prüfe ob der Newsletter vom Newsletterempfänger bereits geöffnet wurde.
    $tracking = Shop::Container()->getDB()->select(
        'tnewslettertrack',
        'kKampagne',
        $campaignID,
        'kNewsletter',
        $newsletterID,
        'kNewsletterEmpfaenger',
        $recipientID,
        false,
        'kNewsletterTrack'
    );
    if (!isset($tracking->kNewsletterTrack)) {
        $newTracking                        = new stdClass();
        $newTracking->kKampagne             = $campaignID;
        $newTracking->kNewsletter           = $newsletterID;
        $newTracking->kNewsletterEmpfaenger = $recipientID;
        $newTracking->dErstellt             = 'NOW()';

        $db = Shop::Container()->getDB();
        $id = $db->insert('tnewslettertrack', $newTracking);
        if ($id > 0) {
            $campaign = new Campaign($campaignID, $db);
            // Kampagnenbesucher in die Session
            $_SESSION['Kampagnenbesucher'][$campaign->kKampagne] = $campaign;

            Campaign::setCampaignAction(KAMPAGNE_DEF_NEWSLETTER, $id, 1);
        }
    }
}

echo base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');
