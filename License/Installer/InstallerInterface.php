<?php

declare(strict_types=1);

namespace JTL\License\Installer;

use JTL\License\AjaxResponse;

/**
 * Interface InstallerInterface
 * @package JTL\License\Installer
 */
interface InstallerInterface
{
    /**
     * @param string       $exsID
     * @param string       $zip
     * @param AjaxResponse $response
     * @return int
     */
    public function update(string $exsID, string $zip, AjaxResponse $response): int;

    /**
     * @param string       $itemID
     * @param string       $zip
     * @param AjaxResponse $response
     * @return int
     */
    public function install(string $itemID, string $zip, AjaxResponse $response): int;

    /**
     * only use this for upgrading old shop4 plugins without exsid to new ones
     *
     * @param string       $zip
     * @param AjaxResponse $response
     * @return int
     */
    public function forceUpdate(string $zip, AjaxResponse $response): int;
}
