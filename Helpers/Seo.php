<?php

declare(strict_types=1);

namespace JTL\Helpers;

use JTL\DB\DbInterface;
use JTL\Shop;

/**
 * Class Seo
 * @package JTL\Helpers
 */
class Seo
{
    public static function getSeo(mixed $url, bool $keepUnderscore = false): string
    {
        return \is_string($url) ? self::sanitizeSeoSlug($url, $keepUnderscore) : '';
    }

    public static function checkSeo(mixed $url, ?DbInterface $db = null): string
    {
        if ($url === '' || !\is_string($url)) {
            return '';
        }
        $db = $db ?? Shop::Container()->getDB();
        if ($db->select('tseo', 'cSeo', $url) === null) {
            return $url;
        }
        $db->query('SET @IKEY := 0');

        return $db->getSingleObject(
            "SELECT oseo.newSeo
                FROM (
                    SELECT CONCAT('" . $url . "', '_', (CONVERT(@IKEY:=@IKEY+1 USING 'utf8mb4')
                        COLLATE utf8mb4_unicode_ci)) newSeo,
                        @IKEY nOrder
                    FROM tseo AS iseo
                    WHERE iseo.cSeo LIKE '" . $url . "%'
                        AND iseo.cSeo RLIKE '^" . $url . "(_[0-9]+)?$'
                ) AS oseo
                WHERE oseo.newSeo NOT IN (
                    SELECT iseo.cSeo
                    FROM tseo AS iseo
                    WHERE iseo.cSeo LIKE '" . $url . "_%'
                        AND iseo.cSeo RLIKE '^" . $url . "_[0-9]+$'
                )
                ORDER BY oseo.nOrder
                LIMIT 1"
        )->newSeo ?? $url;
    }

    public static function sanitizeSeoSlug(string $str, bool $keepUnderscore = false): string
    {
        /** @var string $str */
        $str = \preg_replace(
            \SLUG_ALLOW_SPECIAL_CHARS ? '/[^\pL\d\-\/_\s$.+(),]+/u' : '/[^\pL\d\-\/_\s]+/u',
            '',
            Text::replaceUmlauts($str)
        );
        /** @var string $str */
        $str = \preg_replace('/[\/]+/u', '/', $str);
        /** @var string $str */
        $str          = \transliterator_transliterate(
            'Any-Latin; Latin-ASCII;' . (\SEO_SLUG_LOWERCASE ? ' Lower();' : ''),
            \trim($str, ' -_')
        );
        $convertedStr = @\iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
        /** @var string $str */
        $str   = $convertedStr === false
            ? \preg_replace('/[^a-zA-Z\d\s]/', '', $str)
            : \str_replace("'", '', $convertedStr);
        $regex = $keepUnderscore === false
            ? '/[\-_\s]+/u'
            : '/[\-\s]+/u';

        return \preg_replace($regex, '-', \trim($str)) ?? $str;
    }

    /**
     * Get flat SEO-URL path (removes all slashes from seo-url-path, including leading and trailing slashes)
     *
     * @param string $path - the seo path e.g. "My/Product/Name"
     * @return string - flat SEO-URL Path e.g. "My-Product-Name"
     */
    public static function getFlatSeoPath(string $path): string
    {
        return \trim(\str_replace('/', '-', $path), ' -_');
    }
}
