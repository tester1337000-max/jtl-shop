<?php

declare(strict_types=1);

namespace JTL\OPC;

use JTL\Helpers\GeneralObject;

/**
 * Class Page
 * @package JTL\OPC
 */
class Page implements \JsonSerializable
{
    protected int $key = 0;

    protected string $id = '';

    protected bool $isModifiable = true;

    protected ?string $publishFrom = null;

    protected ?string $publishTo = null;

    protected string $name = '';

    protected int $revId = 0;

    protected string $url = '';

    protected ?string $lastModified = null;

    protected string $lockedBy = '';

    protected ?string $lockedAt = null;

    /**
     * @var array<mixed>|null
     */
    protected ?array $customerGroups = null;

    protected AreaList $areaList;

    public function __construct()
    {
        $this->areaList = new AreaList();
    }

    public function getKey(): int
    {
        return $this->key;
    }

    public function setKey(int $key): self
    {
        $this->key = $key;

        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function isModifiable(): bool
    {
        return $this->isModifiable;
    }

    public function setIsModifiable(bool $isModifiable): Page
    {
        $this->isModifiable = $isModifiable;

        return $this;
    }

    public function getPublishFrom(): ?string
    {
        return $this->publishFrom;
    }

    public function setPublishFrom(?string $publishFrom): self
    {
        $this->publishFrom = $publishFrom;

        return $this;
    }

    public function getPublishTo(): ?string
    {
        return $this->publishTo;
    }

    public function setPublishTo(?string $publishTo): self
    {
        $this->publishTo = $publishTo;

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

    public function getRevId(): int
    {
        return $this->revId;
    }

    public function setRevId(int $revId): self
    {
        $this->revId = $revId;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getLastModified(): ?string
    {
        return $this->lastModified;
    }

    public function setLastModified(?string $lastModified): self
    {
        $this->lastModified = $lastModified;

        return $this;
    }

    public function getLockedBy(): string
    {
        return $this->lockedBy;
    }

    public function setLockedBy(string $lockedBy): self
    {
        $this->lockedBy = $lockedBy;

        return $this;
    }

    public function getLockedAt(): ?string
    {
        return $this->lockedAt;
    }

    public function setLockedAt(?string $lockedAt): self
    {
        $this->lockedAt = $lockedAt;

        return $this;
    }

    /**
     * @return array<mixed>|null
     */
    public function getCustomerGroups(): ?array
    {
        return $this->customerGroups;
    }

    /**
     * @param array<mixed>|null $customerGroups
     * @return $this
     */
    public function setCustomerGroups(?array $customerGroups): self
    {
        $this->customerGroups = $customerGroups;

        return $this;
    }

    public function getAreaList(): AreaList
    {
        return $this->areaList;
    }

    public function setAreaList(AreaList $newList): self
    {
        $this->areaList = $newList;

        return $this;
    }

    /**
     * @param int[]|null $publicDraftKeys
     * @return int
     */
    public function getStatus(?array $publicDraftKeys = []): int
    {
        $now  = \date('Y-m-d H:i:s');
        $from = $this->getPublishFrom();
        $to   = $this->getPublishTo();

        if (!empty($from) && $now >= $from && (empty($to) || $now < $to)) {
            if (empty($publicDraftKeys) || \in_array($this->getKey(), $publicDraftKeys)) {
                return 0; // public
            }
            return 1; // planned
        }
        if (!empty($from) && $now < $from) {
            return 1; // planned
        }
        if (empty($from)) {
            return 2; // draft
        }
        if (!empty($to) && $now > $to) {
            return 3; // backdate
        }

        return -1;
    }

    /**
     * @param bool $preview
     * @return array<string, bool>
     */
    public function getCssList(bool $preview = false): array
    {
        $list = [];
        foreach ($this->areaList->getAreas() as $area) {
            /** @noinspection AdditionOperationOnArraysInspection */
            $list += $area->getCssList($preview);
        }

        return $list;
    }

    /**
     * @return array<string, bool>
     */
    public function getJsList(): array
    {
        $list = [];
        foreach ($this->areaList->getAreas() as $area) {
            /** @noinspection AdditionOperationOnArraysInspection */
            $list += $area->getJsList();
        }

        return $list;
    }

    /**
     * @throws \Exception
     */
    public function fromJson(string $json): self
    {
        $this->deserialize(\json_decode($json, true, 512, \JSON_THROW_ON_ERROR));

        return $this;
    }

    /**
     * @param array<mixed> $data
     * @return Page
     * @throws \Exception
     */
    public function deserialize(array $data): self
    {
        $this->setKey($data['key'] ?? $this->getKey());
        $this->setId($data['id'] ?? $this->getId());
        $this->setPublishFrom($data['publishFrom'] ?? $this->getPublishFrom());
        $this->setPublishTo($data['publishTo'] ?? $this->getPublishTo());
        $this->setName($data['name'] ?? $this->getName());
        $this->setUrl($data['url'] ?? $this->getUrl());
        $this->setRevId($data['revId'] ?? $this->getRevId());
        $this->setCustomerGroups($data['customerGroups'] ?? $this->getCustomerGroups());

        if (GeneralObject::isCountable('areas', $data)) {
            $this->getAreaList()->deserialize($data['areas']);
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'key'            => $this->getKey(),
            'id'             => $this->getId(),
            'publishFrom'    => $this->getPublishFrom(),
            'publishTo'      => $this->getPublishTo(),
            'name'           => $this->getName(),
            'revId'          => $this->getRevId(),
            'url'            => $this->getUrl(),
            'lastModified'   => $this->getLastModified(),
            'lockedBy'       => $this->getLockedBy(),
            'lockedAt'       => $this->getLockedAt(),
            'areaList'       => $this->getAreaList()->jsonSerialize(),
            'customerGroups' => $this->getCustomerGroups(),
        ];
    }
}
