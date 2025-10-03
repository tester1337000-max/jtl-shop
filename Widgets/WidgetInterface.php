<?php

declare(strict_types=1);

namespace JTL\Widgets;

use JTL\DB\DbInterface;
use JTL\Plugin\PluginInterface;
use JTL\Smarty\JTLSmarty;

/**
 * Interface WidgetInterface
 * @package JTL\Widgets
 */
interface WidgetInterface
{
    /**
     * @param JTLSmarty|null       $smarty
     * @param DbInterface|null     $db
     * @param PluginInterface|null $plugin
     */
    public function __construct(?JTLSmarty $smarty = null, ?DbInterface $db = null, $plugin = null);

    /**
     * @return JTLSmarty
     */
    public function getSmarty(): JTLSmarty;

    /**
     * @param JTLSmarty $oSmarty
     */
    public function setSmarty(JTLSmarty $oSmarty): void;

    /**
     * @return DbInterface
     */
    public function getDB(): DbInterface;

    /**
     * @param DbInterface $oDB
     */
    public function setDB(DbInterface $oDB): void;

    /**
     * @return PluginInterface
     */
    public function getPlugin(): PluginInterface;

    /**
     * @param PluginInterface $plugin
     */
    public function setPlugin(PluginInterface $plugin): void;

    /**
     * @return mixed
     */
    public function init();

    /**
     * @return string
     */
    public function getContent();

    /**
     * @return string
     */
    public function getPermission(): string;

    /**
     * @param string $permission
     */
    public function setPermission(string $permission): void;

    /**
     * @return bool
     */
    public function hasBody(): bool;

    /**
     * @param bool $hasBody
     */
    public function setHasBody(bool $hasBody): void;
}
