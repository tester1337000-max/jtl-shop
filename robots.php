<?php

declare(strict_types=1);

use JTL\Shop;

ob_start();
require_once __DIR__ . '/includes/globalinclude.php';

$robotsContent = file_get_contents(PFAD_ROOT . 'robots.txt');

if (file_exists(PFAD_ROOT . PFAD_EXPORT . 'sitemap_index.xml') && !str_contains($robotsContent, 'Sitemap: ')) {
    $robotsContent .= PHP_EOL . 'Sitemap: ' . Shop::getURL() . '/sitemap_index.xml';
}

ob_end_clean();
header('Content-Type: text/plain', true, 200);

echo $robotsContent;
