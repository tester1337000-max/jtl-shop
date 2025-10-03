<?php

declare(strict_types=1);

namespace JTL\Mail\SendMailObjects;

use JTL\DataObjects\AbstractDataObject;
use JTL\DataObjects\DataTableObjectInterface;

/**
 * Class MailDataAttachmentObject
 * @package JTL\Mail\SendMailObjects
 */
class MailDataAttachmentObject extends AbstractDataObject implements DataTableObjectInterface
{
    private string $primaryKey = 'id';

    protected int $id = 0;

    protected int $mailID = 0;

    protected string $mime = '';

    protected string $dir = '';

    protected string $fileName = '';

    protected string $name = '';

    protected string $encoding = 'base64';

    /**
     * @var array<string, string>
     */
    private array $mapping = [];

    /**
     * @var array<string, string>
     */
    private array $columnMapping = [
        'primaryKey' => 'primaryKey',
        'id'         => 'id',
        'mailID'     => 'mailID',
        'mime'       => 'mime',
        'dir'        => 'dir',
        'fileName'   => 'fileName',
        'name'       => 'name',
        'encoding'   => 'encoding',
    ];

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getMailID(): int
    {
        return $this->mailID;
    }

    public function setMailID(int $mailID): self
    {
        $this->mailID = $mailID;

        return $this;
    }

    public function getMime(): string
    {
        return $this->mime;
    }

    public function setMime(string $mime): self
    {
        $this->mime = $mime;

        return $this;
    }

    public function getDir(): string
    {
        return $this->dir;
    }

    public function setDir(string $dir): self
    {
        $this->dir = $dir;

        return $this;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): self
    {
        $this->fileName = $fileName;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getEncoding(): string
    {
        return $this->encoding;
    }

    public function setEncoding(string $encoding): self
    {
        $this->encoding = $encoding;

        return $this;
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
