<?php

declare(strict_types=1);

namespace JTL\Mail;

use Greew\OAuth2\Client\Provider\Azure;
use Illuminate\Support\Collection;
use JTL\Abstracts\AbstractService;
use JTL\Mail\Attachments\AttachmentsService;
use JTL\Mail\Mail\MailInterface;
use JTL\Mail\SendMailObjects\MailDataTableObject;
use JTL\Shop;
use League\OAuth2\Client\Provider\Google;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\OAuth;
use PHPMailer\PHPMailer\PHPMailer;
use stdClass;

/**
 * Class MailService
 * @package JTL\Mail
 */
class MailService extends AbstractService
{
    /**
     * @var array<mixed>
     */
    protected array $emailConfig = [];

    public function __construct(
        protected MailRepository $repository = new MailRepository(),
        protected AttachmentsService $attachmentsService = new AttachmentsService()
    ) {
    }

    /**
     * Could be injected as dependency as well
     *
     * @return array<mixed>
     */
    public function getEmailConfig(): array
    {
        if ($this->emailConfig === []) {
            $this->emailConfig = Shop::getSettingSection(\CONF_EMAILS);
            // @ToDo: Remove when setting is created
            $this->emailConfig['chunkSize'] = \EMAIL_CHUNK_SIZE;
        }

        return $this->emailConfig;
    }

    /**
     * @return MailRepository
     */
    public function getRepository(): MailRepository
    {
        return $this->repository;
    }

    public function getAttachmentsService(): AttachmentsService
    {
        return $this->attachmentsService;
    }

    /**
     * @return array{bool, int}
     */
    public function queueMail(MailInterface $mailObject): array
    {
        $result = true;
        $item   = $this->prepareQueueInsert($mailObject);
        $mailID = $this->getRepository()->queueMailDataTableObject($item);
        $this->cacheAttachments($item);
        foreach ($item->getAttachments() as $attachment) {
            $result = $result && ($this->getAttachmentsService()->insertAttachment($attachment, $mailID) > 0);
        }

        return [$result, $mailID];
    }

    private function cacheAttachments(MailDataTableObject $item): void
    {
        if (
            !\is_dir(\PATH_MAILATTACHMENTS)
            && !\mkdir($concurrentDirectory = \PATH_MAILATTACHMENTS, 0775)
            && !\is_dir($concurrentDirectory)
        ) {
            Shop::Container()->getLogService()->error('Error sending mail: Attachment directory could not be created');

            return;
        }
        foreach ($item->getAttachments() as $attachment) {
            $fileName = \preg_replace('/[^öäüÖÄÜßa-zA-Z\d.\-_]/u', '', $attachment->getName());
            if ($attachment->getMime() === 'application/pdf' && !\str_ends_with($attachment->getName(), '.pdf')) {
                $attachment->setName($fileName . '.pdf');
            }
            $uniqueFilename = \uniqid(\str_replace(['.', ':', ' '], '', $item->getDateQueued()), true);
            if (!\copy($attachment->getDir() . $attachment->getFileName(), \PATH_MAILATTACHMENTS . $uniqueFilename)) {
                Shop::Container()->getLogService()->error('Error sending mail: Attachment could not be cached');

                return;
            }
            if ($attachment->getDir() !== \PFAD_ROOT . \PFAD_ADMIN . \PFAD_INCLUDES . \PFAD_EMAILPDFS) {
                \unlink($attachment->getDir() . $attachment->getFileName());
            }
            $attachment->setDir(\PATH_MAILATTACHMENTS);
            $attachment->setFileName($uniqueFilename);
        }
    }

    private function prepareQueueInsert(MailInterface $mailObject): MailDataTableObject
    {
        $insertObj = new MailDataTableObject();
        $insertObj->hydrateWithObject($mailObject->toObject());
        $insertObj->setLanguageId($mailObject->getLanguage()->getId());
        $insertObj->setTemplateId($mailObject->getTemplate()?->getID() ?? '');

        return $insertObj;
    }

    /**
     * @param int $mailId
     * @return MailDataTableObject[]
     */
    public function getAndMarkMailById(int $mailId = 0): array
    {
        $mailsToSend = $this->getRepository()->getMailByQueueId($mailId);

        return $this->getReturnMailObjects($mailsToSend);
    }

    /**
     * @return MailDataTableObject[]
     */
    public function getNextQueuedMailsAndMarkThemToSend(): array
    {
        $mailsToSend = $this->getRepository()->getNextMailsFromQueue($this->getEmailConfig()['chunkSize']);

        return $this->getReturnMailObjects($mailsToSend);
    }

    /**
     * @param array<mixed> $mailIds
     */
    public function setMailStatus(array $mailIds, int $isSendingNow): bool
    {
        return $this->getRepository()->setMailStatus($mailIds, $isSendingNow);
    }

    /**
     * @throws Exception
     */
    public function sendViaPHPMailer(MailInterface $mail): bool
    {
        $phpmailer             = new PHPMailer();
        $phpmailer->AllowEmpty = true;
        $phpmailer->CharSet    = \JTL_CHARSET;
        $phpmailer->Timeout    = \SOCKET_TIMEOUT;
        $phpmailer->Encoding   = PHPMailer::ENCODING_QUOTED_PRINTABLE;
        $phpmailer->setLanguage($mail->getLanguage()->getIso639());
        $phpmailer->setFrom($mail->getFromMail(), $mail->getFromName());
        foreach ($mail->getRecipients() as $recipient) {
            $phpmailer->addAddress($recipient['mail'], $recipient['name']);
        }
        $phpmailer->addReplyTo($mail->getReplyToMail(), $mail->getReplyToName());
        $phpmailer->Subject = $mail->getSubject();
        if (!empty($mail->getCopyRecipients()[0])) {
            foreach ($mail->getCopyRecipients() as $recipient) {
                $phpmailer->addBCC($recipient);
            }
        }
        $this->initMethod($phpmailer);
        if ($mail->getBodyHTML()) {
            $phpmailer->isHTML();
            $phpmailer->Body    = $mail->getBodyHTML();
            $phpmailer->AltBody = $mail->getBodyText();
        } else {
            $phpmailer->isHTML(false);
            $phpmailer->Body = $mail->getBodyText();
        }
        $this->addAttachments($phpmailer, $mail);
        \executeHook(\HOOK_MAILER_PRE_SEND, [
            'mailer'    => $this,
            'mail'      => $mail,
            'phpmailer' => $phpmailer
        ]);
        if ($phpmailer->Body === '') {
            Shop::Container()->getLogService()->warning('Empty body for mail ' . $phpmailer->Subject);
        }
        $sent = $phpmailer->send();
        $mail->setError($phpmailer->ErrorInfo);
        \executeHook(\HOOK_MAILER_POST_SEND, [
            'mailer'    => $this,
            'mail'      => $mail,
            'phpmailer' => $phpmailer,
            'status'    => $sent
        ]);

        return $sent;
    }

    private function getMethod(): \stdClass
    {
        $config = $this->getEmailConfig();

        return (object)[
            'methode'             => $config['email_methode'],
            'sendmail_pfad'       => $config['email_sendmail_pfad'],
            'smtp_hostname'       => $config['email_smtp_hostname'],
            'smtp_port'           => $config['email_smtp_port'],
            'smtp_auth'           => (int)$config['email_smtp_auth'] === 1,
            'smtp_user'           => $config['email_smtp_user'],
            'smtp_pass'           => $config['email_smtp_pass'],
            'SMTPSecure'          => $config['email_smtp_verschluesselung'],
            'SMTPAutoTLS'         => !empty($config['email_smtp_verschluesselung']),
            'oauth_client_id'     => $config['oauth_client_id'],
            'oauth_client_secret' => $config['oauth_client_secret'],
            'oauth_tenant_id'     => $config['oauth_tenant_id'],
            'oauth_refresh_token' => $config['oauth_refresh_token'],
        ];
    }

    private function createAzureOAuthFromMethod(\stdClass $method): OAuth
    {
        return new OAuth([
            'provider'     => new Azure([
                'clientId'     => $method->oauth_client_id,
                'clientSecret' => $method->oauth_client_secret,
                'tenantId'     => $method->oauth_tenant_id,
            ]),
            'clientId'     => $method->oauth_client_id,
            'clientSecret' => $method->oauth_client_secret,
            'refreshToken' => $method->oauth_refresh_token,
            'userName'     => $method->smtp_user,
        ]);
    }

    private function createGoogleOAuthFromMethod(\stdClass $method): OAuth
    {
        return new OAuth([
            'provider'     => new Google([
                'clientId'     => $method->oauth_client_id,
                'clientSecret' => $method->oauth_client_secret,
            ]),
            'clientId'     => $method->oauth_client_id,
            'clientSecret' => $method->oauth_client_secret,
            'refreshToken' => $method->oauth_refresh_token,
            'userName'     => $method->smtp_user,
        ]);
    }

    private function initMethod(PHPMailer $phpmailer): void
    {
        $method = $this->getMethod();
        switch ($method->methode) {
            case 'mail':
                $phpmailer->isMail();
                break;
            case 'sendmail':
                $phpmailer->isSendmail();
                $phpmailer->Sendmail = $method->sendmail_pfad;
                break;
            case 'qmail':
                $phpmailer->isQmail();
                break;
            case 'smtp':
                $phpmailer->isSMTP();
                $phpmailer->Host          = $method->smtp_hostname;
                $phpmailer->Port          = $method->smtp_port;
                $phpmailer->SMTPKeepAlive = true;
                $phpmailer->SMTPAuth      = $method->smtp_auth;
                $phpmailer->Username      = $method->smtp_user;
                $phpmailer->Password      = $method->smtp_pass;
                $phpmailer->SMTPSecure    = $method->SMTPSecure;
                $phpmailer->SMTPAutoTLS   = $method->SMTPAutoTLS;
                break;
            case 'outlook':
                $phpmailer->isSMTP();
                $phpmailer->Host          = 'smtp.office365.com';
                $phpmailer->Port          = 587;
                $phpmailer->SMTPKeepAlive = true;
                $phpmailer->SMTPAuth      = true;
                $phpmailer->SMTPSecure    = PHPMailer::ENCRYPTION_STARTTLS;
                $phpmailer->AuthType      = 'XOAUTH2';
                $phpmailer->setOAuth($this->createAzureOAuthFromMethod($method));
                break;
            case 'gmail':
                $phpmailer->isSMTP();
                $phpmailer->Host          = 'smtp.gmail.com';
                $phpmailer->Port          = 587;
                $phpmailer->SMTPKeepAlive = true;
                $phpmailer->SMTPAuth      = true;
                $phpmailer->SMTPSecure    = PHPMailer::ENCRYPTION_STARTTLS;
                $phpmailer->AuthType      = 'XOAUTH2';
                $phpmailer->setOAuth($this->createGoogleOAuthFromMethod($method));
                break;
        }
    }

    /**
     * @throws Exception
     */
    private function addAttachments(PHPMailer $phpmailer, MailInterface $mail): void
    {
        foreach ($mail->getPdfAttachments() as $pdf) {
            $phpmailer->addAttachment(
                $pdf->getFullPath(),
                $pdf->getName() . '.pdf',
                $pdf->getEncoding(),
                $pdf->getMime()
            );
        }
        foreach ($mail->getAttachments() as $attachment) {
            $phpmailer->addAttachment(
                $attachment->getFullPath(),
                $attachment->getName(),
                $attachment->getEncoding(),
                $attachment->getMime()
            );
        }
    }

    public function setError(int $mailID, string $errorMsg): void
    {
        $this->getRepository()->setError($mailID, $errorMsg);

        Shop::Container()->getLogService()->error(
            "Error sending mail: \nMailId: " . $mailID . "\n" . $errorMsg
        );
    }

    public function deleteQueuedMail(int $mailID): void
    {
        $this->getRepository()->deleteQueuedMail($mailID);
    }

    /**
     * @param string[] $mailIDs
     */
    public function deleteQueuedMails(array $mailIDs): void
    {
        $this->getRepository()->deleteQueuedMails($mailIDs);
    }

    /**
     * @param array<mixed> $mailsToSend
     * @return MailDataTableObject[]
     */
    private function getReturnMailObjects(array $mailsToSend): array
    {
        // do not send mails multiple times
        $this->setMailStatus(\array_column($mailsToSend, 'id'), 1);
        $attachments       = $this->getAttachmentsService()->getListByMailIDs(\array_column($mailsToSend, 'id'));
        $returnMailObjects = [];
        foreach ($mailsToSend as $mail) {
            if (!\is_array($mail['copyRecipients'])) {
                $mail['copyRecipients'] = \explode(';', $mail['copyRecipients']);
            }
            $attachmentsToAdd = $mail['hasAttachments'] > 0 ? $attachments[$mail['id']] : [];
            $mdto             = new MailDataTableObject();
            $mdto->hydrate($mail);
            $returnMailObjects[] = $mdto->setAttachments($attachmentsToAdd ?? []);
        }

        return $returnMailObjects;
    }

    /**
     * @return Collection<int, MailDataTableObject>
     */
    public function getErroneousMails(string $limit): Collection
    {
        return $this->buildMailCollection($this->getRepository()->getErroneousMails($limit));
    }

    /**
     * @return Collection<int, MailDataTableObject>
     */
    public function getQueuedMails(string $limit): Collection
    {
        return $this->buildMailCollection($this->getRepository()->getQueuedMails($limit));
    }

    /**
     * @param stdClass[] $mailData
     * @return Collection<int, MailDataTableObject>
     */
    private function buildMailCollection(array $mailData): Collection
    {
        $mails = new Collection();
        foreach ($mailData as $mail) {
            $mail->copyRecipients = \explode(';', $mail->copyRecipients);
            $mails->push((new MailDataTableObject())->hydrateWithObject($mail));
        }

        return $mails;
    }

    public function getErroneousMailsCount(): int
    {
        return $this->getRepository()->getErroneousMailsCount();
    }

    public function getQueuedMailsCount(): int
    {
        return $this->getRepository()->getQueuedMailsCount();
    }
}
