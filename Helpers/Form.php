<?php

declare(strict_types=1);

namespace JTL\Helpers;

use Exception;
use JTL\Mail\Mail\Mail;
use JTL\RateLimit\Upload;
use JTL\Session\Frontend;
use JTL\Shop;
use JTL\SimpleMail;
use stdClass;

use function Functional\none;

/**
 * Class Form
 * @package JTL\Helpers
 * @since 5.0.0
 */
class Form
{
    /**
     * @param array<string, mixed> $requestData
     * @return bool
     * @since 5.0.0
     */
    public static function validateCaptcha(array $requestData): bool
    {
        $valid = Shop::Container()->getCaptchaService()->validate($requestData);

        if ($valid) {
            Frontend::set('bAnti_spam_already_checked', true);
        } else {
            Shop::Smarty()->assign('bAnti_spam_failed', true);
        }

        return $valid;
    }

    /**
     * create a hidden input field for xsrf validation
     * @throws Exception
     * @since 5.0.0
     */
    public static function getTokenInput(): string
    {
        if (!isset($_SESSION['jtl_token'])) {
            $_SESSION['jtl_token'] = Shop::Container()->getCryptoService()->randomString(32);
        }

        return '<input type="hidden" class="jtl_token" name="jtl_token" value="' . $_SESSION['jtl_token'] . '" />';
    }

    /**
     * validate token from POST/GET
     *
     * @param null|string $token
     * @return bool
     * @since 5.0.0
     */
    public static function validateToken(?string $token = null): bool
    {
        if (!isset($_SESSION['jtl_token'])) {
            return false;
        }

        $tokenTMP = $token ?? $_POST['jtl_token'] ?? $_GET['token'] ?? null;

        if ($tokenTMP === null) {
            return false;
        }

        return Shop::Container()->getCryptoService()->stableStringEquals($_SESSION['jtl_token'], $tokenTMP);
    }

    /**
     * @param array<string, int> $fehlendeAngaben
     * @return int
     * @since 5.0.0
     */
    public static function eingabenKorrekt(array $fehlendeAngaben): int
    {
        return (int)none($fehlendeAngaben, static fn($e): bool => $e > 0);
    }

    /**
     * check if form data contains honeypot data - index must be 'jtl_hp_input' (see scc)
     * @param array<string, mixed> $data
     * @return bool - true if honeypot is filled
     */
    public static function honeypotWasFilledOut(array $data): bool
    {
        return !empty($data['jtl_hp_input']);
    }

    /**
     * @return array<string, int>
     * @former gibFehlendeEingabenKontaktformular()
     * @since 5.0.0
     */
    public static function getMissingContactFormData(): array
    {
        $ret  = [];
        $conf = Shop::getSettingSection(\CONF_KONTAKTFORMULAR);
        if (empty($_POST['nachricht'])) {
            $ret['nachricht'] = 1;
        }
        if (empty($_POST['subject'])) {
            $ret['subject'] = 1;
        }
        if (empty($_POST['email'])) {
            $ret['email'] = 1;
        } elseif (Text::filterEmailAddress($_POST['email']) === false) {
            $ret['email'] = 2;
        } elseif (SimpleMail::checkBlacklist($_POST['email'])) {
            $ret['email'] = 3;
        }
        foreach (['vorname', 'nachname', 'firma'] as $key) {
            if (empty($_POST[$key]) && $conf['kontakt_abfragen_' . $key] === 'Y') {
                $ret[$key] = 1;
            }
        }
        foreach (['fax', 'tel', 'mobil'] as $idx) {
            if ($conf['kontakt_abfragen_' . $idx] === 'Y' && ($ok = Text::checkPhoneNumber($_POST[$idx] ?? '')) > 0) {
                $ret[$idx] = $ok;
            }
        }
        if ($conf['kontakt_abfragen_captcha'] !== 'N' && !self::validateCaptcha($_POST)) {
            $ret['captcha'] = 2;
        }

        return $ret;
    }

    /**
     * @since 5.0.0
     */
    public static function baueKontaktFormularVorgaben(bool $clear = false): stdClass
    {
        $msg                  = self::getDefaultCustomerFormInputs();
        $msg->kKontaktBetreff = null;
        $msg->cNachricht      = null;

        if ($clear) {
            return $msg;
        }

        $msg->kKontaktBetreff = Request::postInt('subject', null);
        $msg->cNachricht      = isset($_POST['nachricht'])
            ? Text::filterXSS($_POST['nachricht'])
            : null;

        if (isset($_POST['subject']) && $_POST['subject']) {
            $msg->kKontaktBetreff = Text::filterXSS($_POST['subject']);
        }
        if (isset($_POST['nachricht']) && $_POST['nachricht']) {
            $msg->cNachricht = Text::filterXSS($_POST['nachricht']);
        }

        return $msg;
    }

    /**
     * @former pruefeBetreffVorhanden()
     * @since 5.0.0
     */
    public static function checkSubject(): bool
    {
        $customerGroupID = Frontend::getCustomerGroup()->getID();
        if (!$customerGroupID) {
            $customerGroupID = Frontend::getCustomer()->getGroupID();
        }

        $subjects = Shop::Container()->getDB()->getObjects(
            "SELECT kKontaktBetreff
                FROM tkontaktbetreff
                WHERE FIND_IN_SET('" . $customerGroupID . "', REPLACE(cKundengruppen, ';', ',')) > 0
                    OR cKundengruppen = '0'"
        );

        return \count($subjects) > 0;
    }

    /**
     * @return int|false
     * @former bearbeiteNachricht()
     * @since 5.0.0
     */
    public static function editMessage(): bool|int
    {
        $betreff = isset($_POST['subject'])
            ? Shop::Container()->getDB()->select('tkontaktbetreff', 'kKontaktBetreff', Request::pInt('subject'))
            : null;
        if ($betreff === null || empty($betreff->kKontaktBetreff)) {
            return false;
        }
        $betreffSprache             = Shop::Container()->getDB()->select(
            'tkontaktbetreffsprache',
            'kKontaktBetreff',
            (int)$betreff->kKontaktBetreff,
            'cISOSprache',
            Shop::getLanguageCode()
        );
        $data                       = new stdClass();
        $data->tnachricht           = self::baueKontaktFormularVorgaben();
        $data->tnachricht->cBetreff = $betreffSprache->cName ?? '';

        $conf     = Shop::getSettings([\CONF_KONTAKTFORMULAR, \CONF_GLOBAL]);
        $from     = new stdClass();
        $senders  = Shop::Container()->getDB()->selectAll('temailvorlageeinstellungen', 'kEmailvorlage', 11);
        $mailData = new stdClass();
        if (\count($senders)) {
            foreach ($senders as $f) {
                $from->{$f->cKey} = $f->cValue;
            }
            $mailData->fromEmail = $from->cEmailOut;
            $mailData->fromName  = $from->cEmailSenderName;
        }
        $mailData->toEmail      = $betreff->cMail;
        $mailData->toName       = $conf['global']['global_shopname'];
        $mailData->replyToEmail = $data->tnachricht->cMail;
        $mailData->replyToName  = '';
        if (!empty($data->tnachricht->cVorname)) {
            $mailData->replyToName .= $data->tnachricht->cVorname . ' ';
        }
        if (!empty($data->tnachricht->cNachname)) {
            $mailData->replyToName .= $data->tnachricht->cNachname;
        }
        if (!empty($data->tnachricht->cFirma)) {
            $mailData->replyToName .= ' - ' . $data->tnachricht->cFirma;
        }
        $data->mail = $mailData;
        if (isset($_SESSION['kSprache']) && !isset($data->tkunde)) {
            $data->tkunde           = new stdClass();
            $data->tkunde->kSprache = $_SESSION['kSprache'];
        }
        $mailer = Shop::Container()->getMailer();
        $mail   = new Mail();
        $mail   = $mail->createFromTemplateID(\MAILTEMPLATE_KONTAKTFORMULAR, $data);
        if ($conf['kontakt']['kontakt_kopiekunde'] === 'Y') {
            $mail->addCopyRecipient($data->tnachricht->cMail);
        }
        $mailer->send($mail);

        $KontaktHistory                  = new stdClass();
        $KontaktHistory->kKontaktBetreff = $betreff->kKontaktBetreff;
        $KontaktHistory->kSprache        = $_SESSION['kSprache'];
        $KontaktHistory->cAnrede         = $data->tnachricht->cAnrede ?? null;
        $KontaktHistory->cVorname        = $data->tnachricht->cVorname ?? null;
        $KontaktHistory->cNachname       = $data->tnachricht->cNachname ?? null;
        $KontaktHistory->cFirma          = $data->tnachricht->cFirma ?? null;
        $KontaktHistory->cTel            = $data->tnachricht->cTel ?? null;
        $KontaktHistory->cMobil          = $data->tnachricht->cMobil ?? null;
        $KontaktHistory->cFax            = $data->tnachricht->cFax ?? null;
        $KontaktHistory->cMail           = $data->tnachricht->cMail ?? null;
        $KontaktHistory->cNachricht      = $data->tnachricht->cNachricht ?? null;
        $KontaktHistory->cIP             = Request::getRealIP();
        $KontaktHistory->dErstellt       = 'NOW()';

        return Shop::Container()->getDB()->insert('tkontakthistory', $KontaktHistory);
    }

    /**
     * @since 5.0.0
     */
    public static function checkFloodProtection(int $min): bool
    {
        if (!$min) {
            return false;
        }
        $history = Shop::Container()->getDB()->getSingleObject(
            'SELECT kKontaktHistory
                FROM tkontakthistory
                WHERE cIP = :ip
                    AND DATE_SUB(NOW(), INTERVAL :min MINUTE) < dErstellt',
            ['ip' => Request::getRealIP(), 'min' => $min]
        );

        return $history !== null && $history->kKontaktHistory > 0;
    }

    /**
     * @since 5.0.0
     */
    public static function reachedUploadLimitPerHour(int $max = 0): bool
    {
        if ($max <= 0) {
            return false;
        }
        $limiter = new Upload(Shop::Container()->getDB());
        $limiter->setLimit($max);
        $limiter->init(Request::getRealIP());
        $ok = $limiter->check();
        $limiter->cleanup();
        if ($ok === true) {
            $limiter->persist();
        }

        return !$ok;
    }

    /**
     * @since 5.0.0
     */
    public static function baueFormularVorgaben(): stdClass
    {
        return self::baueKontaktFormularVorgaben();
    }

    public static function getDefaultCustomerFormInputs(): stdClass
    {
        $msg    = new stdClass();
        $inputs = [
            'cAnrede'   => 'anrede',
            'cVorname'  => 'vorname',
            'cNachname' => 'nachname',
            'cFirma'    => 'firma',
            'cMail'     => 'email',
            'cFax'      => 'fax',
            'cTel'      => 'tel',
            'cMobil'    => 'mobil'
        ];
        foreach ($inputs as $key => $input) {
            $msg->$key = isset($_POST[$input]) ? Text::filterXSS($_POST[$input]) : ($_SESSION['Kunde']->$key ?? null);
        }
        if ($msg->cAnrede === 'm') {
            $msg->cAnredeLocalized = Shop::Lang()->get('salutationM');
        } elseif ($msg->cAnrede === 'w') {
            $msg->cAnredeLocalized = Shop::Lang()->get('salutationW');
        }

        return $msg;
    }

    /**
     * @param array<string, int> $missingData
     * @return int
     * @former angabenKorrekt()
     * @since 5.2.0
     */
    public static function hasNoMissingData(array $missingData): int
    {
        return (int)none($missingData, fn($e): bool => $e > 0);
    }
}
