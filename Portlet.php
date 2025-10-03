<?php

declare(strict_types=1);

namespace JTL\OPC;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\L10n\GetText;
use JTL\Plugin\PluginInterface;

/**
 * Class Portlet
 * @package JTL\OPC
 */
class Portlet implements \JsonSerializable
{
    use PortletHtml;
    use PortletStyles;
    use PortletAnimations;

    protected string $title = '';

    protected string $group = '';

    protected bool $active = false;

    final public function __construct(
        protected string $class,
        protected int $id,
        protected DbInterface $db,
        protected JTLCacheInterface $cache,
        protected GetText $getText,
        protected ?PluginInterface $plugin = null
    ) {
        if ($this->plugin === null) {
            $this->getText->loadAdminLocale('portlets/' . $this->class);
        } else {
            $this->getText->loadPluginLocale('portlets/' . $this->class, $this->plugin);
        }
    }

    public function initInstance(PortletInstance $instance): void
    {
    }

    /**
     * @return array<mixed>
     */
    final public function getDefaultProps(): array
    {
        $defProps = [];
        foreach ($this->getPropertyDesc() as $name => $propDesc) {
            $defProps[$name] = $propDesc['default'] ?? '';
            if (isset($propDesc['children'])) {
                foreach ($propDesc['children'] as $childName => $childPropDesc) {
                    $defProps[$childName] = $childPropDesc['default'] ?? '';
                }
            }
            if (isset($propDesc['childrenFor'])) {
                foreach ($propDesc['childrenFor'] as $optionalPropDescs) {
                    foreach ($optionalPropDescs as $childName => $childPropDesc) {
                        $defProps[$childName] = $childPropDesc['default'] ?? '';
                    }
                }
            }
        }

        return $defProps;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getPropertyDesc(): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    public function getExtraJsFiles(): array
    {
        return [];
    }

    /**
     * @return array<string, bool>
     */
    public function getJsFiles(): array
    {
        $list = [];
        foreach ($this->getExtraJsFiles() as $extra) {
            $list[$extra] = true;
        }
        return $list;
    }

    /**
     * @return array<mixed>
     */
    public function getDeepPropertyDesc(): array
    {
        $deepDesc = [];
        foreach ($this->getPropertyDesc() as $name => $propDesc) {
            $deepDesc[$name] = $propDesc;
            if (isset($propDesc['children'])) {
                foreach ($propDesc['children'] as $childName => $childPropDesc) {
                    $deepDesc[$childName] = $childPropDesc;
                }
            }
            if (isset($propDesc['childrenFor'])) {
                foreach ($propDesc['childrenFor'] as $optionalPropDescs) {
                    foreach ($optionalPropDescs as $childName => $childPropDesc) {
                        $deepDesc[$childName] = $childPropDesc;
                    }
                }
            }
        }

        return $deepDesc;
    }

    /**
     * @return array<string, string|string[]>
     */
    public function getPropertyTabs(): array
    {
        return [];
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPluginId(): int
    {
        return $this->plugin === null ? 0 : $this->plugin->getID();
    }

    public function getRawTitle(): string
    {
        return $this->title;
    }

    public function getTitle(): string
    {
        return \__($this->title);
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function setGroup(string $group): self
    {
        $this->group = $group;

        return $this;
    }

    public function getPlugin(): ?PluginInterface
    {
        return $this->plugin;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function rendersForms(PortletInstance $instance): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id'           => $this->getId(),
            'pluginId'     => $this->getPluginId(),
            'title'        => $this->getTitle(),
            'class'        => $this->getClass(),
            'group'        => $this->getGroup(),
            'active'       => $this->isActive(),
            'defaultProps' => $this->getDefaultProps(),
        ];
    }
}
