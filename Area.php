<?php

declare(strict_types=1);

namespace JTL\OPC;

use JTL\Events\Dispatcher;
use JTL\Events\Event;
use JTL\Helpers\GeneralObject;
use JTL\Shop;

/**
 * Class Area
 * @package JTL\OPC
 */
class Area implements \JsonSerializable
{
    protected string $id = '';

    /**
     * @var PortletInstance[]
     */
    protected array $content = [];

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Clear the contents
     */
    public function clear(): void
    {
        $this->content = [];
    }

    /**
     * @param PortletInstance $portlet
     */
    public function addPortlet(PortletInstance $portlet): void
    {
        $this->content[] = $portlet;
    }

    /**
     * @return PortletInstance[]
     */
    public function getContent(): array
    {
        return $this->content;
    }

    /**
     * @throws \Exception
     */
    public function getPreviewHtml(): string
    {
        $result = '';
        foreach ($this->content as $portletInstance) {
            $result .= $portletInstance->getPreviewHtml();
        }

        Dispatcher::getInstance()->fire(Event::OPC_AREA_GETPREVIEWHTML, [
            'area'   => $this,
            'result' => &$result
        ]);

        return $result;
    }

    /**
     * @throws \Exception
     */
    public function getFinalHtml(bool $inContainer = true): string
    {
        $result = '';
        foreach ($this->content as $portletInstance) {
            $result .= $portletInstance->getFinalHtml($inContainer);
        }

        Dispatcher::getInstance()->fire(Event::OPC_AREA_GETFINALHTML, [
            'area'   => $this,
            'result' => &$result
        ]);

        return $result;
    }

    /**
     * @param bool $preview
     * @return array<string, bool>
     */
    public function getCssList(bool $preview = false): array
    {
        $list = [];
        foreach ($this->content as $portletInstance) {
            $cssFiles = $portletInstance->getPortlet()->getCssFiles($preview);
            $list     += $cssFiles;
            foreach ($portletInstance->getSubareaList()->getAreas() as $area) {
                $list += $area->getCssList($preview);
            }
        }

        return $list;
    }

    /**
     * @return array<string, bool>
     */
    public function getJsList(): array
    {
        $list = [];
        foreach ($this->content as $portletInstance) {
            $jsFiles = $portletInstance->getPortlet()->getJsFiles();
            $list    += $jsFiles;
            foreach ($portletInstance->getSubareaList()->getAreas() as $area) {
                $list += $area->getJsList();
            }
        }

        return $list;
    }

    /**
     * @param array<mixed> $data
     * @return $this
     * @throws \Exception
     */
    public function deserialize(array $data): self
    {
        $this->id = $data['id'];
        if (GeneralObject::hasCount('content', $data)) {
            $this->clear();
            $opc = Shop::Container()->getOPC();
            foreach ($data['content'] as $portletData) {
                $instance = $opc->getPortletInstance($portletData);
                $this->addPortlet($instance);
            }
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [
            'id'      => $this->id,
            'content' => [],
        ];

        foreach ($this->content as $instance) {
            $result['content'][] = $instance->jsonSerialize();
        }

        return $result;
    }
}
