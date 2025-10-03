<?php

declare(strict_types=1);

namespace JTL;

use Exception;
use JTL\Helpers\Text;
use JTL\Settings\Option\Blocklist;
use JTL\Settings\Settings;
use PHPMailer\PHPMailer\PHPMailer;
use stdClass;

/**
 * Class SimpleMail
 * @package JTL
 */
class SimpleMail
{
    private string $cVerfasserMail = '';

    private string $cVerfasserName = '';

    private string $cBetreff = '';

    private string $cBodyHTML = '';

    private string $cBodyText = '';

    /**
     * @var array<array{cPath: string, cName: string, cEncoding: string, cType: string}>
     */
    private array $attachments = [];

    private string $cMethod;

    private string $cSMTPUser;

    private string $cSMTPPass;

    private int $cSMTPPort = 25;

    private string $cSMTPHost;

    private string $cSMTPAuth;

    private string $cSendMailPfad;

    /**
     * @var array<string, mixed>
     */
    private array $cErrorLog = [];

    private bool $valid = true;

    /**
     * @param bool                  $shopMail
     * @param array<string, string> $mailConfig
     */
    public function __construct(bool $shopMail = true, array $mailConfig = [])
    {
        if ($shopMail === true) {
            $config = Shop::getSettingSection(\CONF_EMAILS);

            $this->cMethod        = $config['email_methode'];
            $this->cSendMailPfad  = $config['email_sendmail_pfad'];
            $this->cSMTPHost      = $config['email_smtp_hostname'];
            $this->cSMTPPort      = $config['email_smtp_port'];
            $this->cSMTPAuth      = $config['email_smtp_auth'];
            $this->cSMTPUser      = $config['email_smtp_user'];
            $this->cSMTPPass      = $config['email_smtp_pass'];
            $this->cVerfasserName = $config['email_master_absender_name'];
            $this->cVerfasserMail = $config['email_master_absender'];
        } elseif (!empty($mailConfig)) {
            if (!empty($mailConfig['cMethod'])) {
                $this->valid = $this->setMethod($mailConfig['cMethod']);
            }
            $this->cSendMailPfad = $mailConfig['cSendMailPfad'];
            $this->cSMTPHost     = $mailConfig['cSMTPHost'];
            $this->cSMTPPort     = (int)$mailConfig['cSMTPPort'];
            $this->cSMTPAuth     = $mailConfig['cSMTPAuth'];
            $this->cSMTPUser     = $mailConfig['cSMTPUser'];
            $this->cSMTPPass     = $mailConfig['cSMTPPass'];
            if (!empty($mailConfig['cVerfasserName'])) {
                $this->setVerfasserName($mailConfig['cVerfasserName']);
            }
            if (!empty($mailConfig['cVerfasserMail'])) {
                $this->valid = $this->setVerfasserMail($mailConfig['cVerfasserMail']);
            }
        } else {
            $this->valid = false;
        }
    }

    /**
     * Anhang hinzufügen
     * array('cName' => 'Mein Anhang', 'cPath' => '/pfad/zu/meiner/datei.txt');
     */
    public function addAttachment(
        string $name,
        string $path,
        string $encoding = 'base64',
        string $type = 'application/octet-stream'
    ): bool {
        if (!empty($name) && \file_exists($path)) {
            $attachments              = [];
            $attachments['cName']     = $name;
            $attachments['cPath']     = $path;
            $attachments['cEncoding'] = $encoding;
            $attachments['cType']     = $type;
            $this->attachments[]      = $attachments;

            return true;
        }

        return false;
    }

    /**
     * @throws Exception
     */
    public function validate(): bool
    {
        if (!$this->valid) {
            $this->setErrorLog('cConfig', 'Konfiguration fehlerhaft');
        }
        if (empty($this->cVerfasserMail) || empty($this->cVerfasserName)) {
            $this->setErrorLog('cVerfasserMail', 'Verfasser nicht gesetzt!');
        }
        if (empty($this->cBodyHTML) && empty($this->cBodyText)) {
            $this->setErrorLog('cBody', 'Inhalt der E-Mail nicht gesetzt!');
        }
        if (empty($this->cBetreff)) {
            $this->setErrorLog('cBetreff', 'Betreff nicht gesetzt!');
        }
        if (empty($this->cMethod)) {
            $this->setErrorLog('cMethod', 'Versandmethode nicht gesetzt!');
        } else {
            switch ($this->cMethod) {
                case 'PHP Mail()':
                case 'sendmail':
                    if (empty($this->cSendMailPfad)) {
                        $this->setErrorLog('cSendMailPfad', 'SendMailPfad nicht gesetzt!!');
                    }
                    break;
                case 'QMail':
                    break;
                case 'smtp':
                    if (
                        empty($this->cSMTPAuth)
                        || empty($this->cSMTPHost)
                        || empty($this->cSMTPPass)
                        || empty($this->cSMTPUser)
                    ) {
                        $this->setErrorLog('SMTP', 'SMTP Daten nicht gesetzt!');
                    }
                    break;
            }
        }

        $cErrorLog = $this->getErrorLog();

        return empty($cErrorLog);
    }

    /**
     * E-Mail verschicken
     *
     * @param array<array{cMail: string, cName: string}> $recipients
     * @param array<array{cMail: string, cName: string}> $cc
     * @param array<array{cMail: string, cName: string}> $bcc
     * @param array<array{cMail: string, cName: string}> $replyTo
     * @return bool
     * @throws Exception
     * @throws \PHPMailer\PHPMailer\Exception
     */
    public function send(array $recipients, array $cc = [], array $bcc = [], array $replyTo = []): bool
    {
        if ($this->validate() !== true) {
            return false;
        }
        $mailer           = new PHPMailer();
        $mailer->CharSet  = \JTL_CHARSET;
        $mailer->Timeout  = \SOCKET_TIMEOUT;
        $mailer->From     = $this->cVerfasserMail;
        $mailer->Sender   = $this->cVerfasserMail;
        $mailer->FromName = $this->cVerfasserName;
        $mailer->Encoding = PHPMailer::ENCODING_QUOTED_PRINTABLE;
        if (!empty($recipients)) {
            foreach ($recipients as $recipient) {
                $mailer->addAddress($recipient['cMail'], $recipient['cName']);
            }
        }
        if (!empty($cc)) {
            foreach ($cc as $recipient) {
                $mailer->addCC($recipient['cMail'], $recipient['cName']);
            }
        }
        if (!empty($bcc)) {
            foreach ($bcc as $recipient) {
                $mailer->addBCC($recipient['cMail'], $recipient['cName']);
            }
        }
        if (!empty($replyTo)) {
            foreach ($replyTo as $item) {
                $mailer->addReplyTo($item['cMail'], $item['cName']);
            }
        }

        $mailer->Subject = $this->cBetreff;

        switch ($this->cMethod) {
            case 'mail':
                $mailer->isMail();
                break;
            case 'sendmail':
                $mailer->isSendmail();
                $mailer->Sendmail = $this->cSendMailPfad;
                break;
            case 'qmail':
                $mailer->isQmail();
                break;
            case 'smtp':
                $mailer->isSMTP();
                $mailer->Host          = $this->cSMTPHost;
                $mailer->Port          = $this->cSMTPPort;
                $mailer->SMTPKeepAlive = true;
                $mailer->SMTPAuth      = (int)$this->cSMTPAuth === 1;
                $mailer->Username      = $this->cSMTPUser;
                $mailer->Password      = $this->cSMTPPass;
                break;
        }

        if (!empty($this->cBodyHTML)) {
            $mailer->isHTML();
            $mailer->Body    = $this->cBodyHTML;
            $mailer->AltBody = $this->cBodyText;
        } else {
            $mailer->isHTML(false);
            $mailer->Body = $this->cBodyText;
        }
        foreach ($this->attachments as $attachment) {
            $mailer->addAttachment(
                $attachment['cPath'],
                $attachment['cName'],
                $attachment['cEncoding'],
                $attachment['cType']
            );
        }
        $sent = $mailer->send();
        $mailer->clearAddresses();

        return $sent;
    }

    public function getVerfasserMail(): ?string
    {
        return $this->cVerfasserMail;
    }

    public function getVerfasserName(): string
    {
        return $this->cVerfasserName;
    }

    public function getBetreff(): string
    {
        return $this->cBetreff;
    }

    public function getBodyHTML(): string
    {
        return $this->cBodyHTML;
    }

    public function getBodyText(): string
    {
        return $this->cBodyText;
    }

    public function setVerfasserMail(string $cVerfasserMail): bool
    {
        if (\filter_var($cVerfasserMail, \FILTER_VALIDATE_EMAIL)) {
            $this->cVerfasserMail = $cVerfasserMail;

            return true;
        }

        return false;
    }

    public function setVerfasserName(string $cVerfasserName): self
    {
        $this->cVerfasserName = $cVerfasserName;

        return $this;
    }

    public function setBetreff(string $cBetreff): self
    {
        $this->cBetreff = $cBetreff;

        return $this;
    }

    public function setBodyHTML(string $cBodyHTML): self
    {
        $this->cBodyHTML = $cBodyHTML;

        return $this;
    }

    public function setBodyText(string $cBodyText): self
    {
        $this->cBodyText = $cBodyText;

        return $this;
    }

    public function getErrorInfo(): null
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getErrorLog(): array
    {
        return $this->cErrorLog;
    }

    public function setErrorLog(string $cKey, mixed $cValue): void
    {
        $this->cErrorLog[$cKey] = $cValue;
    }

    public function getMethod(): string
    {
        return $this->cMethod;
    }

    public function setMethod(string $cMethod): bool
    {
        if ($cMethod === 'QMail' || $cMethod === 'smtp' || $cMethod === 'PHP Mail()' || $cMethod === 'sendmail') {
            $this->cMethod = $cMethod;

            return true;
        }

        return false;
    }

    public static function checkBlacklist(?string $mail): bool
    {
        if ($mail === null) {
            return true;
        }
        $mail = \mb_convert_case(Text::filterXSS($mail), \MB_CASE_LOWER);
        if (Text::filterEmailAddress($mail) === false) {
            return true;
        }
        if (Settings::boolValue(Blocklist::DO_USE)) {
            return false;
        }
        $db = Shop::Container()->getDB();
        foreach ($db->getObjects('SELECT cEmail FROM temailblacklist') as $item) {
            if (\str_contains($item->cEmail, '*')) {
                \preg_match('/' . \str_replace('*', '[a-z0-9\-\_\.\@\+]*', $item->cEmail) . '/', $mail, $hits);
                // Blocked
                if (isset($hits[0]) && \mb_strlen($mail) === \mb_strlen($hits[0])) {
                    $block = $db->select('temailblacklistblock', 'cEmail', $mail);
                    if ($block !== null && !empty($block->cEmail)) {
                        $_upd                = new stdClass();
                        $_upd->dLetzterBlock = 'NOW()';
                        $db->update('temailblacklistblock', 'cEmail', $mail, $_upd);
                    } else {
                        $block                = new stdClass();
                        $block->cEmail        = $mail;
                        $block->dLetzterBlock = 'NOW()';
                        $db->insert('temailblacklistblock', $block);
                    }

                    return true;
                }
            } elseif (\mb_convert_case($item->cEmail, \MB_CASE_LOWER) === \mb_convert_case($mail, \MB_CASE_LOWER)) {
                $block = $db->select('temailblacklistblock', 'cEmail', $mail);
                if (!empty($block->cEmail)) {
                    $_upd                = new stdClass();
                    $_upd->dLetzterBlock = 'NOW()';
                    $db->update('temailblacklistblock', 'cEmail', $mail, $_upd);
                } else {
                    $block                = new stdClass();
                    $block->cEmail        = $mail;
                    $block->dLetzterBlock = 'NOW()';
                    $db->insert('temailblacklistblock', $block);
                }

                return true;
            }
        }

        return false;
    }
}
