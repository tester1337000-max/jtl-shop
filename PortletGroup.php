<?php

declare(strict_types=1);

namespace JTL\OPC;

/**
 * Class PortletGroup
 * @package JTL\OPC
 */
class PortletGroup
{
    protected string $name = '';

    /**
     * @var Portlet[]
     */
    protected array $portlets = [];

    public function __construct(string $name)
    {
        $this->name = $name === '' ? 'No Group' : $name;
    }

    /**
     * @return Portlet[]
     */
    public function getPortlets(): array
    {
        return $this->portlets;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function addPortlet(Portlet $portlet): self
    {
        $this->portlets[] = $portlet;

        return $this;
    }
}
