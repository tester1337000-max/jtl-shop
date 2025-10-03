<?php

declare(strict_types=1);

namespace JTL\Widgets;

use JTL\DB\DbInterface;
use JTL\Plugin\PluginInterface;
use JTL\Shop;
use JTL\Smarty\ContextType;
use JTL\Smarty\JTLSmarty;

/**
 * Class AbstractWidget
 * @package JTL\Widgets
 */
abstract class AbstractWidget implements WidgetInterface
{
    public JTLSmarty $oSmarty;

    public DbInterface $oDB;

    public ?PluginInterface $oPlugin = null;

    public bool $hasBody = true;

    public string $permission = '';

    /**
     * @inheritdoc
     */
    public function __construct(?JTLSmarty $smarty = null, ?DbInterface $db = null, $plugin = null)
    {
        $this->oSmarty = $smarty ?? Shop::Smarty(false, ContextType::BACKEND);
        $this->oDB     = $db ?? Shop::Container()->getDB();
        $this->oPlugin = $plugin;
        $this->init();
    }

    /**
     * @inheritdoc
     */
    public function getSmarty(): JTLSmarty
    {
        return $this->oSmarty;
    }

    /**
     * @inheritdoc
     */
    public function setSmarty(JTLSmarty $oSmarty): void
    {
        $this->oSmarty = $oSmarty;
    }

    /**
     * @inheritdoc
     */
    public function getDB(): DbInterface
    {
        return $this->oDB;
    }

    /**
     * @inheritdoc
     */
    public function setDB(DbInterface $oDB): void
    {
        $this->oDB = $oDB;
    }

    /**
     * @inheritdoc
     */
    public function getPlugin(): PluginInterface
    {
        return $this->oPlugin ?? throw new \Exception('Plugin not set');
    }

    /**
     * @inheritdoc
     */
    public function setPlugin(PluginInterface $plugin): void
    {
        $this->oPlugin = $plugin;
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
    }

    /**
     * @inheritdoc
     */
    public function getContent()
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getPermission(): string
    {
        return $this->permission;
    }

    /**
     * @inheritdoc
     */
    public function setPermission(string $permission): void
    {
        $this->permission = $permission;
    }

    /**
     * @inheritdoc
     */
    public function hasBody(): bool
    {
        return $this->hasBody;
    }

    /**
     * @inheritdoc
     */
    public function setHasBody(bool $hasBody): void
    {
        $this->hasBody = $hasBody;
    }
}
