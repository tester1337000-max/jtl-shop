<?php

declare(strict_types=1);

namespace JTL\Backend;

/**
 * Class NotificationEntry
 * @package JTL\Backend
 */
class NotificationEntry
{
    public const TYPE_NONE    = -1;
    public const TYPE_INFO    = 0;
    public const TYPE_WARNING = 1;
    public const TYPE_DANGER  = 2;

    protected ?string $pluginId = null;

    protected int $type;

    protected string $title;

    protected ?string $description = null;

    protected ?string $url = null;

    protected ?string $hash = null;

    protected bool $ignored = false;

    public function __construct(
        int $type,
        string $title,
        ?string $description = null,
        ?string $url = null,
        ?string $hash = null
    ) {
        $this->setType($type)
            ->setTitle($title)
            ->setDescription($description)
            ->setUrl($url)
            ->setHash($hash);
    }

    public function getPluginId(): ?string
    {
        return $this->pluginId;
    }

    public function setPluginId(string $pluginId): self
    {
        $this->pluginId = $pluginId;

        return $this;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(int $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function hasDescription(): bool
    {
        return $this->description !== null && \mb_strlen($this->description) > 0;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function hasUrl(): bool
    {
        return $this->url !== null && \mb_strlen($this->url) > 0;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(?string $hash): self
    {
        $this->hash = $hash;

        return $this;
    }

    public function isIgnored(): bool
    {
        return $this->ignored;
    }

    public function setIgnored(bool $ignored): self
    {
        $this->ignored = $ignored;

        return $this;
    }
}
