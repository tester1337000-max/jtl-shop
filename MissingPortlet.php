<?php

declare(strict_types=1);

namespace JTL\OPC\Portlets\MissingPortlet;

use JTL\OPC\Portlet;
use JTL\OPC\PortletInstance;
use JTL\Plugin\PluginInterface;

/**
 * Class MissingPortlet
 * @package JTL\OPC\Portlets
 */
class MissingPortlet extends Portlet
{
    protected string $missingClass = '';

    protected ?PluginInterface $inactivePlugin = null;

    /**
     * @throws \Exception
     */
    public function getPreviewHtml(PortletInstance $instance): string
    {
        return $this->getPreviewHtmlFromTpl($instance);
    }

    public function getFinalHtml(PortletInstance $instance, bool $inContainer = true): string
    {
        return '';
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

    public function getInactivePlugin(): ?PluginInterface
    {
        return $this->inactivePlugin;
    }

    public function setInactivePlugin(?PluginInterface $inactivePlugin): MissingPortlet
    {
        $this->inactivePlugin = $inactivePlugin;

        return $this;
    }
}
