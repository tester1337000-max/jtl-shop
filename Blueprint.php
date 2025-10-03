<?php

declare(strict_types=1);

namespace JTL\OPC;

use JTL\Shop;

/**
 * Class Blueprint
 * @package JTL\OPC
 */
class Blueprint implements \JsonSerializable
{
    protected int $id = 0;

    protected string $name = '';

    protected ?PortletInstance $instance = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

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

    public function getInstance(): ?PortletInstance
    {
        return $this->instance;
    }

    public function setInstance(?PortletInstance $instance): self
    {
        $this->instance = $instance;

        return $this;
    }

    /**
     * @param array<mixed> $data
     * @return $this
     * @throws \Exception
     */
    public function deserialize(array $data): self
    {
        $this->setName($data['name']);
        $instance = Shop::Container()->getOPC()->getPortletInstance($data['content']);
        $this->setInstance($instance);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id'       => $this->getId(),
            'name'     => $this->getName(),
            'instance' => $this->instance?->jsonSerialize(),
        ];
    }
}
