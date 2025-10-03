<?php

declare(strict_types=1);

namespace JTL\OPC;

use JTL\OPC\Portlets\MissingPortlet\MissingPortlet;

/**
 * Class MissingPortletInstance
 * @package JTL\OPC
 */
class MissingPortletInstance extends PortletInstance
{
    protected string $missingClass = '';

    /**
     * @param MissingPortlet    $portlet
     * @param string            $missingClass
     * @param array<mixed>|null $data
     * @throws \Exception
     */
    public function __construct(MissingPortlet $portlet, string $missingClass, ?array $data = null)
    {
        parent::__construct($portlet, $data);
        $this->setMissingClass($missingClass);
    }

    public function getMissingClass(): string
    {
        return $this->missingClass;
    }

    public function setMissingClass(string $missingClass): self
    {
        $this->missingClass = $missingClass;

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function jsonSerializeShort(): array
    {
        $result                 = parent::jsonSerializeShort();
        $result['missingClass'] = $this->getMissingClass();

        return $result;
    }
}
