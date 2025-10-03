<?php

declare(strict_types=1);

namespace JTL\Mail\SendMailObjects;

use JTL\DataObjects\AbstractDataObject;
use JTL\DataObjects\DataTableObjectInterface;
use JTL\Language\LanguageModel;
use JTL\Mail\Mail\Attachment;

/**
 * Class MailDataTableObject
 * @package JTL\Mail\SendMailObjects
 */
class MailDataTableObject extends AbstractDataObject implements DataTableObjectInterface
{
    private string $primaryKey = 'id';

    protected int $id = 0;

    protected int $reSend = 0;

    protected int $isSendingNow = 0;

    protected int $sendCount = 0;

    protected int $errorCount = 0;

    protected string $lastError = '';

    protected string $dateQueued = 'now()';

    protected string $dateSent = '';

    protected string $fromMail;

    protected string $fromName = '';

    protected string $toMail;

    protected ?string $toName = null;

    protected ?string $replyToMail = null;

    protected ?string $replyToName = null;

    protected string $copyRecipients = '';

    protected string $subject;

    protected string $bodyHTML;

    protected string $bodyText;

    protected int $hasAttachments = 0;

    /**
     * @var Attachment[]
     */
    private array $attachments = [];

    protected int $languageId = 0;

    protected string $templateId = '';

    private ?LanguageModel $language = null;

    protected int $customerGroupID = 1;

    protected int $priority = 100;

    /**
     * @var array<string, string>
     */
    private array $mapping = [];

    /**
     * @var array<string, string>
     */
    private array $columnMapping = [
        'primarykey'      => 'primarykey',
        'id'              => 'id',
        'reSend'          => 'reSend',
        'isCancelled'     => 'isCancelled',
        'isBlocked'       => 'isBlocked',
        'isSendingNow'    => 'isSendingNow',
        'sendCount'       => 'sendCount',
        'errorCount'      => 'errorCount',
        'lastError'       => 'lastError',
        'dateQueued'      => 'dateQueued',
        'dateSent'        => 'dateSent',
        'isHtml'          => 'isHtml',
        'fromMail'        => 'fromMail',
        'fromName'        => 'fromName',
        'toMail'          => 'toMail',
        'toName'          => 'toName',
        'replyToMail'     => 'replyToMail',
        'replyToName'     => 'replyToName',
        'copyRecipients'  => 'copyRecipients',
        'subject'         => 'subject',
        'bodyHTML'        => 'bodyHTML',
        'bodyText'        => 'bodyText',
        'hasAttachments'  => 'hasAttachments',
        'attachments'     => 'attachments',
        'pdfAttachments'  => 'attachments',
        'isEmbedImages'   => 'isEmbedImages',
        'customHeaders'   => 'customHeaders',
        'typeReference'   => 'typeReference',
        'deliveryOngoing' => 'deliveryOngoing',
        'templateId'      => 'templateId',
        'languageId'      => 'languageId',
        'language'        => 'language',
        'customerGroupID' => 'customerGroupID',
        'priority'        => 'priority',
    ];

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(string|int $id): self
    {
        $this->id = (int)$id;

        return $this;
    }

    public function getReSend(): int
    {
        return $this->reSend;
    }

    public function setReSend(string|int $reSend): self
    {
        $this->reSend = (int)$reSend;

        return $this;
    }

    public function getIsSendingNow(): int
    {
        return $this->isSendingNow;
    }

    public function setIsSendingNow(string|int $isSendingNow): self
    {
        $this->isSendingNow = (int)$isSendingNow;

        return $this;
    }

    public function getSendCount(): int
    {
        return $this->sendCount;
    }

    public function setSendCount(string|int $sendCount): self
    {
        $this->sendCount = (int)$sendCount;

        return $this;
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    public function setErrorCount(string|int $errorCount): self
    {
        $this->errorCount = (int)$errorCount;

        return $this;
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function setLastError(string $lastError): self
    {
        $this->lastError = $lastError;

        return $this;
    }

    public function getDateQueued(): string
    {
        return $this->dateQueued;
    }

    public function setDateQueued(string $dateQueued): self
    {
        $this->dateQueued = $dateQueued;

        return $this;
    }

    public function getDateSent(): string
    {
        return $this->dateSent;
    }

    public function setDateSent(string $dateSent): self
    {
        $this->dateSent = $dateSent;

        return $this;
    }

    public function getFromMail(): string
    {
        return $this->fromMail;
    }

    public function setFromMail(string $fromMail): self
    {
        $this->fromMail = $fromMail;

        return $this;
    }

    public function getFromName(): string
    {
        return $this->fromName;
    }

    public function setFromName(string $fromName): self
    {
        $this->fromName = $fromName;

        return $this;
    }

    public function getToMail(): string
    {
        return $this->toMail;
    }

    public function setToMail(string $toEmail): self
    {
        $this->toMail = $toEmail;

        return $this;
    }

    public function getToName(): string
    {
        return $this->toName ?? '';
    }

    public function setToName(?string $toName): self
    {
        $this->toName = $toName;

        return $this;
    }

    public function getReplyToMail(): ?string
    {
        return $this->replyToMail ?? $this->fromMail;
    }

    public function setReplyToMail(?string $replyToMail): self
    {
        $this->replyToMail = $replyToMail;

        return $this;
    }

    public function getReplyToName(): string
    {
        return $this->replyToName ?? $this->fromName;
    }

    public function setReplyToName(?string $replyToName): self
    {
        $this->replyToName = $replyToName;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getCopyRecipients(): array
    {
        return \explode(';', $this->copyRecipients);
    }

    public function getCopyRecipientsPlain(): string
    {
        return $this->copyRecipients;
    }

    /**
     * @param array<mixed> $copyRecipients
     * @return self
     */
    public function setCopyRecipients(array $copyRecipients): self
    {
        $this->copyRecipients = \implode(';', $copyRecipients);

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function getBodyHTML(): string
    {
        return $this->bodyHTML;
    }

    public function setBodyHTML(string $bodyHTML): self
    {
        $this->bodyHTML = $bodyHTML;

        return $this;
    }

    public function getBodyText(): string
    {
        return $this->bodyText;
    }

    public function setBodyText(string $bodyText): self
    {
        $this->bodyText = $bodyText;

        return $this;
    }

    public function getHasAttachments(): int
    {
        return $this->hasAttachments;
    }

    public function setHasAttachments(string|int $hasAttachments): self
    {
        $this->hasAttachments = (int)$hasAttachments;

        return $this;
    }

    /**
     * @return Attachment[]
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    /**
     * @param Attachment[]|null $attachments
     */
    public function setAttachments(?array $attachments): self
    {
        if (\is_array($attachments)) {
            foreach ($attachments as $attachment) {
                $this->attachments[] = $attachment;
            }
        }
        if (!empty($attachments[0])) {
            $this->hasAttachments = 1;
        }

        return $this;
    }

    public function getTemplateId(): string
    {
        return $this->templateId;
    }

    public function setTemplateId(null|string|int $template): self
    {
        if ($template !== null) {
            $this->templateId = (string)$template;
        }

        return $this;
    }

    public function getLanguageId(): int
    {
        return $this->languageId;
    }

    public function setLanguageId(string|int $language): self
    {
        $this->languageId = (int)$language;

        return $this;
    }

    public function getLanguage(): ?LanguageModel
    {
        return $this->language;
    }

    public function setLanguage(?LanguageModel $languageModel): self
    {
        $this->language = $languageModel;

        return $this;
    }

    public function getCustomerGroupID(): int
    {
        return $this->customerGroupID;
    }

    public function setCustomerGroupID(string|int $customerGroupID): self
    {
        $this->customerGroupID = (int)$customerGroupID;

        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(string|int $priority): void
    {
        $this->priority = (int)$priority;
    }

    /**
     * @inheritdoc
     */
    public function getMapping(): array
    {
        return \array_merge($this->mapping, $this->columnMapping);
    }

    /**
     * @inheritdoc
     */
    public function getReverseMapping(): array
    {
        return \array_flip($this->mapping);
    }

    /**
     * @inheritdoc
     */
    public function getColumnMapping(): array
    {
        return \array_flip($this->columnMapping);
    }
}
