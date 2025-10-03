<?php

/**
 * Groups configuration for default Minify implementation
 *
 * @package Minify
 */

declare(strict_types=1);

use JTL\Backend\AdminTemplate;
use JTL\Shop;

if (isset($_GET['g']) && ($_GET['g'] === 'admin_js' || $_GET['g'] === 'admin_css')) {
    return AdminTemplate::getInstance()->getMinifyArray(true);
}
$resources = Shop::Container()->getTemplateService()->getActiveTemplate()->getResources();
$resources->init();

return $resources->getMinifyArray(true);
