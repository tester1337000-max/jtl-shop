<?php

declare(strict_types=1);

namespace JTL\Redirect\Helpers;

use JTL\Helpers\URL;

class Normalizer
{
    public function normalize(string $path, bool $keepTrailingSlash = true): string
    {
        $url = new URL();
        $url->setUrl($path);
        $newURL = $url->normalize();
        if (\str_starts_with($newURL, 'http://') || \str_starts_with($newURL, 'https://')) {
            return $keepTrailingSlash ? \trim($newURL, '\\/') : $newURL;
        }

        return '/' . ($keepTrailingSlash
                ? \trim($newURL, '\\/')
                : \ltrim($newURL, '\\/')
            );
    }
}
