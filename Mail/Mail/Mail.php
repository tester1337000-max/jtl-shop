<?php

declare(strict_types=1);

namespace JTL\Mail\Mail;

use InvalidArgumentException;
use JTL\Customer\Customer;
use JTL\Language\LanguageHelper;
use JTL\Language\LanguageModel;
use JTL\Mail\SendMailObjects\MailDataTableObject;
use JTL\Mail\Template\TemplateFactory;
use JTL\Mail\Template\TemplateInterface;
use JTL\Session\Frontend;
use JTL\Shop;
use PHPMailer\PHPMailer\PHPMailer;
use ReflectionClass;
use stdClass;

/**
 * Class Mail
 * @package JTL\Mail\Mail
 */
class Mail implements MailInterface
{
    /**
     * @deprecated since 5.3.0 - this was a typo
     */
    public const LENTH_LIMIT = 987;

    public const LENGTH_LIMIT = 987;

    private int $customerGroupID = 0;

    private ?LanguageModel $language = null;

    private string $fromMail;

    private string $fromName;

    private ?string $toMail = null;

    private string $toName = '';

    private ?string $replyToMail = null;

    private ?string $replyToName = null;

    private ?string $subject = null;

    private string $bodyHTML = '';

    private string $bodyText = '';

    /**
     * @var Attachment[]
     */
    private array $attachments = [];

    /**
     * @var Attachment[]
     */
    private array $pdfAttachments = [];

    private string $error = '';

    /**
     * @var string[]
     */
    private array $copyRecipients = [];

    private ?TemplateInterface $template = null;

    /**
     * @var mixed|stdClass
     */
    private mixed $data = null;

    /**
     * @var array{}|array<array{mail: string, name: string}>
     */
    private array $recipients = [];

    private int $priority = 100;

    public function __construct()
    {
        $this->initDefaults();
    }

    /**
     * @inheritdoc
     */
    public function createFromTemplateID(
        string $id,
        mixed $data = null,
        ?TemplateFactory $factory = null
    ): MailInterface {
        $template = $this->getTemplateFromID($factory, $id);

        return $this->createFromTemplate($template, $data);
    }

    /**
     * @inheritdoc
     */
    public function createFromTemplate(
        TemplateInterface $template,
        mixed $data = null,
        ?LanguageModel $language = null
    ): MailInterface {
        $this->setData($data);
        $this->setTemplate($template);
        $this->language = $language ?? $this->detectLanguage();

        $languageID = $this->language->getId();
        if ($this->customerGroupID === 0) {
            $this->setCustomerGroupID(Frontend::getCustomer()->getGroupID());
        }
        $template->load($languageID, $this->customerGroupID);
        $model = $template->getModel();
        if ($model === null) {
            throw new InvalidArgumentException('Cannot parse model for ' . $template->getID());
        }
        $names       = $model->getAttachmentNames($languageID);
        $attachments = $model->getAttachments($languageID);
        if (\count($names) === \count($attachments)) {
            foreach (\array_combine($names, $attachments) as $name => $attachment) {
                $this->addPdfFile($name, $attachment);
            }
        } else {
            Shop::Container()->getLogService()->error(
                'Creating mail object: could not combine attachments with their corresponding names for template ID '
                . $template->getID()
            );
        }
        $this->setSubject($model->getSubject($languageID));
        $this->setFromName($template->getFromName() ?? $this->fromName ?? '');
        $this->setFromMail($template->getFromMail() ?? $this->fromMail ?? '');
        $this->setCopyRecipients($template->getCopyTo());
        $this->setSubject($template->getSubject() ?? $this->subject ?? '');
        $this->parseData();
        $this->setReplyToMail($this->replyToMail ?? $this->fromMail);
        $this->setReplyToName($this->replyToName ?? $this->replyToMail ?? '');

        return $this;
    }

    /**
     * some mail servers seem to have problems with very long lines - wordwrap() if necessary
     */
    private function wordwrap(string $text): string
    {
        $hasLongLines = false;
        foreach (\preg_split('/((\r?\n)|(\r\n?))/', $text) ?: [] as $line) {
            if (\mb_strlen($line) > self::LENGTH_LIMIT) {
                $hasLongLines = true;
                break;
            }
        }

        return $hasLongLines ? \wordwrap($text, 900) : $text;
    }

    private function parseData(): void
    {
        /** @var stdClass|null $nlRecipient */
        $nlRecipient = $this->data->NewsletterEmpfaenger ?? null;
        /** @var stdClass|null $mailRecipient */
        $mailRecipient = $this->data->mailReceiver ?? null;
        /** @var stdClass|Customer|null $customer */
        $customer = $this->data->tkunde ?? null;
        if (!empty($nlRecipient->cEmail)) {
            $this->toMail = $nlRecipient->cEmail;
            $this->toName = $nlRecipient->cVorname . ' ' . $nlRecipient->cNachname;
        } elseif (!empty($mailRecipient->cEmail)) {
            $this->toMail = $mailRecipient->cEmail;
            $this->toName = $mailRecipient->cVorname . ' ' . $mailRecipient->cNachname;
        } elseif (isset($this->data->mail)) {
            if (isset($this->data->mail->fromEmail)) {
                $this->fromMail = $this->data->mail->fromEmail;
            }
            if (isset($this->data->mail->fromName)) {
                $this->fromName = $this->data->mail->fromName;
            }
            if (isset($this->data->mail->toEmail)) {
                $this->toMail = $this->data->mail->toEmail;
            }
            if (isset($this->data->mail->toName)) {
                $this->toName = $this->data->mail->toName;
            }
            if (isset($this->data->mail->replyToEmail)) {
                $this->replyToMail = $this->data->mail->replyToEmail;
            }
            if (isset($this->data->mail->replyToName)) {
                $this->replyToName = $this->data->mail->replyToName;
            }
        } elseif (isset($customer->cMail)) {
            $this->toMail = $customer->cMail;
            $this->toName = $customer->cVorname . ' ' . $customer->cNachname;
        }
    }

    private function detectLanguage(): LanguageModel
    {
        if ($this->language !== null) {
            return $this->language;
        }
        $allLanguages = LanguageHelper::getAllLanguages(1);
        if (isset($this->data->tkunde->kSprache) && $this->data->tkunde->kSprache > 0) {
            return $allLanguages[(int)$this->data->tkunde->kSprache];
        }
        if (isset($this->data->NewsletterEmpfaenger->kSprache) && $this->data->NewsletterEmpfaenger->kSprache > 0) {
            return $allLanguages[(int)$this->data->NewsletterEmpfaenger->kSprache];
        }
        if (
            isset($_SESSION['currentLanguage']->kSprache)
            && \is_a($_SESSION['currentLanguage'], LanguageModel::class)
        ) {
            return $_SESSION['currentLanguage'];
        }

        return isset($_SESSION['kSprache'])
            ? $allLanguages[$_SESSION['kSprache']]
            : LanguageHelper::getDefaultLanguage();
    }

    /**
     * @inheritdoc
     */
    public function getLanguage(): LanguageModel
    {
        return $this->language ?? $this->detectLanguage();
    }

    /**
     * @inheritdoc
     */
    public function setLanguage(LanguageModel $language): MailInterface
    {
        $this->language = $language;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * @inheritdoc
     */
    public function setData(mixed $data): MailInterface
    {
        $this->data = $data;

        return $this;
    }

    public function initDefaults(): void
    {
        $config         = Shop::getSettingSection(\CONF_EMAILS);
        $this->fromName = $config['email_master_absender_name'] ?? '';
        $this->fromMail = $config['email_master_absender'] ?? '';
    }

    /**
     * @inheritdoc
     */
    public function getCustomerGroupID(): int
    {
        return $this->customerGroupID;
    }

    /**
     * @inheritdoc
     */
    public function setCustomerGroupID(int $customerGroupID): MailInterface
    {
        $this->customerGroupID = $customerGroupID;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getFromMail(): string
    {
        return $this->fromMail;
    }

    /**
     * @inheritdoc
     */
    public function setFromMail(string $fromMail): MailInterface
    {
        $this->fromMail = $fromMail;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getFromName(): string
    {
        return $this->fromName;
    }

    /**
     * @inheritdoc
     */
    public function setFromName(string $fromName): MailInterface
    {
        $this->fromName = $fromName;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getToMail(): string
    {
        return $this->toMail ?? throw new \Exception('No recipient mail address set');
    }

    /**
     * @inheritdoc
     */
    public function setToMail(string $toMail): MailInterface
    {
        $this->toMail = $toMail;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function addRecipient(string $mail, string $name = ''): void
    {
        $this->recipients[] = ['mail' => $mail, 'name' => $name];
    }

    /**
     * @inheritdoc
     */
    public function setRecipients(array $recipients): void
    {
        $this->recipients = $recipients;
    }

    /**
     * @inheritdoc
     */
    public function getRecipients(): array
    {
        return \array_merge([['mail' => $this->getToMail(), 'name' => $this->getToName()]], $this->recipients);
    }

    /**
     * @inheritdoc
     */
    public function getToName(): string
    {
        return $this->toName;
    }

    /**
     * @inheritdoc
     */
    public function setToName(string $toName): MailInterface
    {
        $this->toName = $toName;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getReplyToMail(): string
    {
        return $this->replyToMail ?? $this->fromMail;
    }

    /**
     * @inheritdoc
     */
    public function setReplyToMail(string $replyToMail): MailInterface
    {
        $this->replyToMail = $replyToMail;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getReplyToName(): string
    {
        return $this->replyToName ?? $this->getReplyToMail();
    }

    /**
     * @inheritdoc
     */
    public function setReplyToName(string $replyToName): MailInterface
    {
        $this->replyToName = $replyToName;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getSubject(): string
    {
        return $this->subject ?? throw new \Exception('No subject set');
    }

    /**
     * @inheritdoc
     */
    public function setSubject(string $subject): MailInterface
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getBodyHTML(): string
    {
        return $this->bodyHTML;
    }

    /**
     * @inheritdoc
     */
    public function setBodyHTML(string $bodyHTML): MailInterface
    {
        $this->bodyHTML = $this->wordwrap($bodyHTML);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getBodyText(): string
    {
        return $this->bodyText;
    }

    /**
     * @inheritdoc
     */
    public function setBodyText(string $bodyText): MailInterface
    {
        $this->bodyText = $this->wordwrap($bodyText);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    /**
     * @inheritdoc
     */
    public function setAttachments(array $attachments): MailInterface
    {
        $this->attachments = $attachments;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function addAttachment(Attachment $attachment): void
    {
        $this->attachments[] = $attachment;
    }

    /**
     * @inheritdoc
     */
    public function getPdfAttachments(): array
    {
        return $this->pdfAttachments;
    }

    /**
     * @inheritdoc
     */
    public function setPdfAttachments(array $pdfAttachments): MailInterface
    {
        $this->pdfAttachments = $pdfAttachments;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function addPdfAttachment(Attachment $pdf): void
    {
        $this->pdfAttachments[] = $pdf;
    }

    /**
     * @inheritdoc
     */
    public function addPdfFile(string $name, string $file): void
    {
        $attachment = new Attachment();
        $attachment->setName($name);
        $attachment->setFileName($file);
        $attachment->setMime('application/pdf');
        $attachment->setEncoding(PHPMailer::ENCODING_BASE64);
        $this->pdfAttachments[] = $attachment;
    }

    /**
     * @inheritdoc
     */
    public function getError(): string
    {
        return $this->error;
    }

    /**
     * @inheritdoc
     */
    public function setError(string $error): MailInterface
    {
        $this->error = $error;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getCopyRecipients(): array
    {
        return $this->copyRecipients;
    }

    /**
     * @inheritdoc
     */
    public function setCopyRecipients(array $copyRecipients): MailInterface
    {
        $this->copyRecipients = $copyRecipients;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function addCopyRecipient(string $copyRecipient): void
    {
        $this->copyRecipients[] = $copyRecipient;
    }

    /**
     * @inheritdoc
     */
    public function getTemplate(): ?TemplateInterface
    {
        return $this->template;
    }

    /**
     * @inheritdoc
     */
    public function setTemplate(?TemplateInterface $template): MailInterface
    {
        $this->template = $template;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * @inheritdoc
     */
    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function toObject(): stdClass
    {
        $reflect    = new ReflectionClass($this);
        $properties = $reflect->getProperties();
        $toArray    = [];
        foreach ($properties as $property) {
            $propertyName           = $property->getName();
            $toArray[$propertyName] = $property->getValue($this);
        }

        return (object)$toArray;
    }

    /**
     * @throws \Exception
     */
    public function hydrateWithObject(MailDataTableObject $object): self
    {
        $attributes = \get_object_vars($this);
        foreach ($attributes as $attribute => $value) {
            $setMethod = 'set' . \ucfirst($attribute);
            $getMethod = 'get' . \ucfirst($attribute);
            if (
                \method_exists($this, $setMethod)
                && \method_exists($object, $getMethod)
                && $object->{$getMethod}() !== null
            ) {
                $this->$setMethod($object->{$getMethod}());
            }
        }
        $this->setLanguage(Shop::Lang()->getLanguageByID($object->getLanguageId()));
        $this->template = $this->getTemplateFromID(null, $object->getTemplateId());

        return $this;
    }

    protected function getTemplateFromID(?TemplateFactory $factory, string $id): TemplateInterface
    {
        $factory  = $factory ?? new TemplateFactory(Shop::Container()->getDB());
        $template = $factory->getTemplate($id);
        if ($template === null) {
            throw new InvalidArgumentException('Cannot find template ' . $id);
        }

        return $template;
    }
}
