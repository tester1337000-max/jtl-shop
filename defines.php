<?php

declare(strict_types=1);

use JTL\Plugin\HookManager;

/*
 * Global options
 */

ifndef('JTL_CHARSET', 'utf-8');
ifndef('DB_CHARSET', 'utf8mb4');
ifndef('DB_COLLATE', 'utf8mb4_unicode_ci');
ifndef('DB_DEFAULT_SQL_MODE', false);
ifndef('DB_STARTUP_SQL', '');
ifndef('SHOP_TIMEZONE', 'Europe/Berlin');
ifndef('DEFAULT_CURL_OPT_VERIFYPEER', true);
ifndef('DEFAULT_CURL_OPT_VERIFYHOST', 2);
ifndef('SAVE_BOT_SESSION', 0);
ifndef('ES_SESSIONS', 0);
ifndef('SMARTY_USE_SUB_DIRS', false);
ifndef('SMARTY_LEGACY_MODE', true);
ifndef('JTL_INCLUDE_ONLY_DB', 0);

/*
 * Paths
 */

ifndef('PFAD_CONFIG', 'config/');
ifndef('PFAD_INCLUDES', 'includes/');
ifndef('PFAD_TEMPLATES', 'templates/');
ifndef('PFAD_COMPILEDIR', 'templates_c/');
ifndef('PATH_STATIC_MINIFY', PFAD_COMPILEDIR . 'min/');
ifndef('PFAD_EMAILPDFS', 'emailpdfs/');
ifndef('PFAD_NEWSLETTERBILDER', 'newsletter/');
ifndef('PFAD_LINKBILDER', 'links/');
ifndef('PFAD_INCLUDES_LIBS', PFAD_INCLUDES . 'libs/');
ifndef('PFAD_MINIFY', PFAD_INCLUDES . 'vendor/mrclay/minify');
ifndef('PFAD_CODEMIRROR', PFAD_INCLUDES_LIBS . 'codemirror/');
ifndef('PFAD_INCLUDES_TOOLS', PFAD_INCLUDES . 'tools/');
ifndef('PFAD_INCLUDES_EXT', PFAD_INCLUDES . 'ext/');
ifndef('PFAD_INCLUDES_MODULES', PFAD_INCLUDES . 'modules/');
ifndef('PFAD_SMARTY', PFAD_INCLUDES . 'vendor/smarty/smarty/libs/');
ifndef('PFAD_GFX', 'gfx/');
ifndef('PFAD_DBES', 'dbeS/');
ifndef('PFAD_DBES_TMP', PFAD_DBES . 'tmp/');
ifndef('PFAD_BILDER', 'bilder/');
ifndef('PFAD_BILDER_SLIDER', PFAD_BILDER . 'slider/');
ifndef('PFAD_CRON', 'cron/');
ifndef('PFAD_FONTS', PFAD_INCLUDES . 'fonts/');
ifndef('PFAD_BILDER_INTERN', PFAD_BILDER . 'intern/');
ifndef('PFAD_BILDER_BANNER', PFAD_BILDER . 'banner/');
ifndef('PFAD_NEWSBILDER', PFAD_BILDER . 'news/');
ifndef('PFAD_NEWSKATEGORIEBILDER', PFAD_BILDER . 'newskategorie/');
ifndef('PFAD_SHOPLOGO', PFAD_BILDER_INTERN . 'shoplogo/');
ifndef('PFAD_ADMIN', 'admin/');
ifndef('PFAD_EMAILVORLAGEN', PFAD_ADMIN . 'mailtemplates/');
ifndef('PFAD_MEDIAFILES', 'mediafiles/');
ifndef('PFAD_PRODUKTBILDER', PFAD_BILDER . 'produkte/');
ifndef('PFAD_PRODUKTBILDER_MINI', PFAD_PRODUKTBILDER . 'mini/');
ifndef('PFAD_PRODUKTBILDER_KLEIN', PFAD_PRODUKTBILDER . 'klein/');
ifndef('PFAD_PRODUKTBILDER_NORMAL', PFAD_PRODUKTBILDER . 'normal/');
ifndef('PFAD_PRODUKTBILDER_GROSS', PFAD_PRODUKTBILDER . 'gross/');
ifndef('PFAD_KATEGORIEBILDER', PFAD_BILDER . 'kategorien/');
ifndef('PFAD_VARIATIONSBILDER', PFAD_BILDER . 'variationen/');
ifndef('PFAD_VARIATIONSBILDER_MINI', PFAD_VARIATIONSBILDER . 'mini/');
ifndef('PFAD_VARIATIONSBILDER_NORMAL', PFAD_VARIATIONSBILDER . 'normal/');
ifndef('PFAD_VARIATIONSBILDER_GROSS', PFAD_VARIATIONSBILDER . 'gross/');
ifndef('PFAD_HERSTELLERBILDER', PFAD_BILDER . 'hersteller/');
ifndef('PFAD_HERSTELLERBILDER_NORMAL', PFAD_HERSTELLERBILDER . 'normal/');
ifndef('PFAD_HERSTELLERBILDER_KLEIN', PFAD_HERSTELLERBILDER . 'klein/');
ifndef('PFAD_MERKMALBILDER', PFAD_BILDER . 'merkmale/');
ifndef('PFAD_MERKMALBILDER_NORMAL', PFAD_MERKMALBILDER . 'normal/');
ifndef('PFAD_MERKMALBILDER_KLEIN', PFAD_MERKMALBILDER . 'klein/');
ifndef('PFAD_MERKMALWERTBILDER', PFAD_BILDER . 'merkmalwerte/');
ifndef('PFAD_MERKMALWERTBILDER_NORMAL', PFAD_MERKMALWERTBILDER . 'normal/');
ifndef('PFAD_MERKMALWERTBILDER_KLEIN', PFAD_MERKMALWERTBILDER . 'klein/');
ifndef('PFAD_BRANDINGBILDER', PFAD_BILDER . 'brandingbilder/');
ifndef('PFAD_SUCHSPECIALOVERLAY', PFAD_BILDER . 'suchspecialoverlay/');
ifndef('PFAD_SUCHSPECIALOVERLAY_KLEIN', PFAD_SUCHSPECIALOVERLAY . 'klein/');
ifndef('PFAD_SUCHSPECIALOVERLAY_NORMAL', PFAD_SUCHSPECIALOVERLAY . 'normal/');
ifndef('PFAD_SUCHSPECIALOVERLAY_GROSS', PFAD_SUCHSPECIALOVERLAY . 'gross/');
ifndef('PFAD_SUCHSPECIALOVERLAY_RETINA', PFAD_SUCHSPECIALOVERLAY . 'retina/');
ifndef('PFAD_OVERLAY_TEMPLATE', '/images/overlay/');
ifndef('PFAD_KONFIGURATOR_KLEIN', PFAD_BILDER . 'konfigurator/klein/');
ifndef('PFAD_LOGFILES', PFAD_ROOT . 'jtllogs/');
ifndef('PFAD_EXPORT', 'export/');
ifndef('PFAD_EXPORT_BACKUP', PFAD_EXPORT . 'backup/');
ifndef('PFAD_UPDATE', 'update/');
ifndef('PFAD_WIDGETS', 'widgets/');
ifndef('PFAD_PORTLETS', 'portlets/');
ifndef('PFAD_INSTALL', 'install/');
ifndef('PFAD_SHOPMD5', 'shopmd5files/');
ifndef('PFAD_UPLOADS', PFAD_ROOT . 'uploads/');
ifndef('PFAD_DOWNLOADS_REL', 'downloads/');
ifndef('PFAD_DOWNLOADS_PREVIEW_REL', PFAD_DOWNLOADS_REL . 'vorschau/');
ifndef('PFAD_DOWNLOADS', PFAD_ROOT . PFAD_DOWNLOADS_REL);
ifndef('PFAD_DOWNLOADS_PREVIEW', PFAD_ROOT . PFAD_DOWNLOADS_PREVIEW_REL);
ifndef('PFAD_UPLOAD_CALLBACK', PFAD_INCLUDES_EXT . 'uploads_cb.php');
ifndef('PFAD_IMAGEMAP', PFAD_BILDER . 'banner/');
ifndef('PFAD_EMAILTEMPLATES', 'templates_mail/');
ifndef('PFAD_MEDIA_VIDEO', 'media/video/');
ifndef('PFAD_MEDIA_IMAGE', 'media/image/');
ifndef('PFAD_MEDIA_DESCRIPTIVE', 'media/descriptive/');
ifndef('PFAD_MEDIA_IMAGE_STORAGE', PFAD_MEDIA_IMAGE . 'storage/');
ifndef('STORAGE_VARIATIONS', PFAD_MEDIA_IMAGE_STORAGE . 'variations/');
ifndef('STORAGE_CONFIGGROUPS', PFAD_MEDIA_IMAGE_STORAGE . 'configgroups/');
ifndef('STORAGE_MANUFACTURERS', PFAD_MEDIA_IMAGE_STORAGE . 'manufacturers/');
ifndef('STORAGE_CATEGORIES', PFAD_MEDIA_IMAGE_STORAGE . 'categories/');
ifndef('STORAGE_CHARACTERISTICS', PFAD_MEDIA_IMAGE_STORAGE . 'characteristics/');
ifndef('STORAGE_CHARACTERISTIC_VALUES', PFAD_MEDIA_IMAGE_STORAGE . 'characteristicvalues/');
ifndef('STORAGE_OPC', PFAD_MEDIA_IMAGE_STORAGE . 'opc/');
ifndef('STORAGE_VIDEO_THUMBS', PFAD_MEDIA_IMAGE_STORAGE . 'videothumbs/');
ifndef('PATH_MAILATTACHMENTS', PFAD_ROOT . PFAD_COMPILEDIR . 'mailattachments/');
ifndef('PFAD_PLUGIN', PFAD_INCLUDES . 'plugins/');
ifndef('PFAD_SYNC_TMP', 'tmp/'); //rel zu dbeS
ifndef('PFAD_SYNC_LOGS', PFAD_ROOT . PFAD_DBES . 'logs/');
ifndef('FILE_RSS_FEED', 'rss.xml');
ifndef('FILE_SHOP_FEED', 'shopinfo.xml');
ifndef('FILE_PHPFEHLER', PFAD_LOGFILES . 'phperror.log');
ifndef('BILD_KEIN_KATEGORIEBILD_VORHANDEN', PFAD_GFX . 'keinBild.gif');
ifndef('BILD_KEIN_ARTIKELBILD_VORHANDEN', PFAD_GFX . 'keinBild.gif');
ifndef('BILD_KEIN_HERSTELLERBILD_VORHANDEN', PFAD_GFX . 'keinBild.gif');
ifndef('BILD_KEIN_MERKMALBILD_VORHANDEN', PFAD_GFX . 'keinBild.gif');
ifndef('BILD_KEIN_MERKMALWERTBILD_VORHANDEN', PFAD_GFX . 'keinBild_kl.gif');
ifndef('BILD_UPLOAD_ZUGRIFF_VERWEIGERT', PFAD_GFX . 'keinBild.gif');
ifndef('OBJECT_CACHE_DIR', PFAD_ROOT . PFAD_COMPILEDIR . 'filecache/');
ifndef('PATH_LOCALE_CACHE', PFAD_COMPILEDIR . 'locale/');
ifndef('DIR_LOCALE_CACHE', PFAD_ROOT . PATH_LOCALE_CACHE);

/*
 * Debugging & Trouble Shooting
 */

ifndef('SYNC_LOG_LEVEL', E_ERROR | E_PARSE);
ifndef('ADMIN_LOG_LEVEL', E_ERROR | E_PARSE);
ifndef('SHOP_LOG_LEVEL', E_ERROR | E_PARSE);
ifndef('SMARTY_LOG_LEVEL', E_ERROR | E_PARSE);
ifndef('COMPATIBILITY_TRACE_DEPTH', 0);
ifndef('KEEP_SYNC_FILES', false);
ifndef('PROFILE_SHOP', false);
ifndef('PLUGIN_DEV_MODE', false);
ifndef('ADMIN_MIGRATION', false);
ifndef('IO_LOG_CONSOLE', false);
ifndef('ES_DB_LOGGING', true);
ifndef('DEBUG_LEVEL', 0);
ifndef('PROFILE_QUERIES', false);
ifndef('PROFILE_QUERIES_ECHO', false);
ifndef('NICEDB_DEBUG_STMT_LEN', 500);
ifndef('NICEDB_EXCEPTION_ECHO', false);
ifndef('NICEDB_EXCEPTION_BACKTRACE', false);
ifndef('SMARTY_DEBUG_CONSOLE', false);
ifndef('SMARTY_SHOW_LANGKEY', false);
ifndef('SMARTY_FORCE_COMPILE', false);
ifndef('SHOW_DEBUG_BAR', false);
ifndef('SHOW_TEMPLATE_HINTS', 0);
const SAFE_MODE_LOCK = PFAD_ROOT . PFAD_ADMIN . PFAD_COMPILEDIR . 'safemode.lck';
ifndef('SAFE_MODE', $GLOBALS['plgSafeMode'] ?? file_exists(SAFE_MODE_LOCK));
ifndef('TRACK_VISITORS', true);
ifndef('COMPRESS_DESCRIPTIONS', false);
ifndef('COMPRESSION_LEVEL', -1);
ifndef('EXS_LIVE', true);

/*
 * Limits & Performance
 */

ifndef('SOCKET_TIMEOUT', 30);
ifndef('ARTICLES_PER_PAGE_HARD_LIMIT', 100);
ifndef('JTLLOG_MAX_LOGSIZE', 200000);
ifndef('SITEMAP_ITEMS_LIMIT', 25000);
ifndef('ART_MATRIX_MAX', 250);
ifndef('REDIS_CONNECT_TIMEOUT', 3);
ifndef('CURL_TIMEOUT_IN_SECONDS', 10);
ifndef('MAX_IMAGES_PER_STEP', 25000);
ifndef('MAX_CORRUPTED_IMAGES', 50);
ifndef('IMAGE_CLEANUP_LIMIT', 50);
ifndef('IMAGE_PRELOAD_LIMIT', 10);
ifndef('IMAGE_PRELOAD_TIMEOUT', 30);
ifndef('SUCHCACHE_LEBENSDAUER', 60);
ifndef('CATEGORY_FULL_LOAD_LIMIT', 10000);
ifndef('CATEGORY_FULL_LOAD_MAX_LEVEL', 3);
ifndef('PRODUCT_LIST_CATEGORY_LIMIT', 500);
ifndef('QUEUE_MAX_STUCK_HOURS', 1);
ifndef('LICENSE_CHECK_MAX_TRY_COUNT', 1);
ifndef('MAX_REVISIONS', 5);
ifndef('EMAIL_CHUNK_SIZE', 1);

/*
 * Features
 */

ifndef('DELIVERY_TIME_DAYS_TO_WEEKS_LIMIT', 15);
ifndef('DELIVERY_TIME_DAYS_TO_MONTHS_LIMIT', 61);
ifndef('DELIVERY_TIME_DAYS_PER_WEEK', 7);
ifndef('DELIVERY_TIME_DAYS_PER_MONTH', 30);
ifndef('PRODUCT_LIST_SHOW_RATINGS', true);
ifndef('SHOW_CHILD_PRODUCTS', 0);
ifndef('ENABLE_EXPERIMENTAL_ROUTING_SCHEMES', false);
ifndef('EXPERIMENTAL_MULTILANG_SHOP', false);
ifndef('ENABLE_RETURNS_MANAGEMENT', false);
ifndef('ENABLE_PROPOTIONAL_TAXES', false);
ifndef('SHOW_REST_API', false);
ifndef('FORCE_IMAGEDRIVER_GD', false);
ifndef('IMAGE_SIZE_XS', 'xs');
ifndef('IMAGE_SIZE_SM', 'sm');
ifndef('IMAGE_SIZE_MD', 'md');
ifndef('IMAGE_SIZE_LG', 'lg');

/*
 * Routing
 */

ifndef('SEO_SLUG_LOWERCASE', false);
ifndef('REDIR_OLD_ROUTES', true);
ifndef('SLUG_ALLOW_SLASHES', true);
ifndef('SLUG_ALLOW_SPECIAL_CHARS', false);
ifndef('ROUTE_PREFIX_PRODUCTS', 'products');
ifndef('ROUTE_PREFIX_CHARACTERISTICS', 'characteristics');
ifndef('ROUTE_PREFIX_CATEGORIES', 'categories');
ifndef('ROUTE_PREFIX_SEARCHSPECIALS', 'searchspecials');
ifndef('ROUTE_PREFIX_SEARCHQUERIES', 'searchqueries');
ifndef('ROUTE_PREFIX_MANUFACTURERS', 'manufacturers');
ifndef('ROUTE_PREFIX_NEWS', 'news');
ifndef('ROUTE_PREFIX_SEARCH', 'search');
ifndef('ROUTE_PREFIX_PAGES', 'pages');
ifndef('CATEGORIES_SLUG_HIERARCHICALLY', false);

/*
 * Security
 */

ifndef('EXPORTFORMAT_ALLOW_PHP', false);
ifndef('NEWSLETTER_USE_SECURITY', true);
ifndef('MAILTEMPLATE_USE_SECURITY', true);
ifndef('EXPORTFORMAT_USE_SECURITY', true);
ifndef('EXPORTFORMAT_ALLOWED_FORMATS', 'txt,csv,xml,html,htm,json,yaml,yml,zip,gz');
ifndef('PASSWORD_DEFAULT_LENGTH', 12);
ifndef('SECURE_PHP_FUNCTIONS', '
    addcslashes, addslashes, bin2hex, chop, chr, chunk_split, count_chars, crypt, explode, html_entity_decode,
    htmlentities, htmlspecialchars_decode, htmlspecialchars, implode, join, lcfirst, levenshtein, ltrim, md5, metaphone,
    money_format, nl2br, number_format, ord, rtrim, sha1, similar_text, soundex, sprintf, str_ireplace, str_pad,
    str_repeat, str_replace, str_rot13, str_shuffle, str_split, str_word_count, strcasecmp, strchr, strcmp, strcoll,
    strcspn, strip_tags, stripslashes, stristr, strlen, strnatcasecmp, strnatcmp, strncasecmp, strncmp, strpbrk, strpos,
    strrchr, strrev, strripos, strrpos, strspn, strstr, strtok, strtolower, strtoupper, strtr, substr_compare,
    substr_count, substr_replace, substr, trim, ucfirst, ucwords, vsprintf, var_dump, print_r, printf, wordwrap,
    intval, floatval, strval, doubleval, 
    is_a, is_array, is_numeric, is_bool, is_float, is_null, is_int, is_string, is_object, uniqid, count,
    array_reverse, array_slice, array_merge, array_unique, array_map, array_keys, array_push, array_pop, array_find, 
    array_values, array_key_last, array_key_first, array_key_exists,
    get_class, urlencode, base64_encode, base64_decode,
    
    str_ends_with, str_starts_with, str_contains, preg_match, is_double, is_resource,
    
    mb_substr, mb_strpos, mb_strrpos, mb_strlen, mb_strtolower, mb_strtoupper, mb_strwidth, mb_strimwidth, mb_stristr,
    mb_convert_case, mb_chr, mb_ucfirst,
    
    checkdate, date_add, date_create_from_format, date_create_immutable_from_format, date_create_immutable, date_create,
    date_date_set, date_diff, date_format, date_get_last_errors, date_interval_create_from_date_string,
    date_interval_format, date_isodate_set, date_modify, date_offset_get, date_parse_from_format, date_parse, date_sub,
    date_sun_info, date_sunrise, date_sunset, date_time_set, date_timestamp_get, date_timespamp_set, date_timezone_get,
    date_timezone_set, date, getdate, gettimeofday, gmdate, gmmktime, gmstrftime, idate, localtime, microtime, mktime,
    strftime, strptime, strtotime, time, timezone_abbreviations_list, timezone_identifiers_list, timezone_location_get,
    timezone_name_from_abbr, timezone_name_get, timezone_offset_get, timzone_open, timezone_transitions_get,
    timezone_version_get, basename,
    
    preg_filter, preg_quote, preg_replace, preg_split,
    
    bcadd, bccomp, bcdiv, bcmod, bcmul, bcpow, bcpowmod, bcsqrt, bcsub,
    
    abs, acos, acosh, asin, asinh, atan2, atan, atanh, base_convert, bindex, ceil, cos, cosh, decbin, dexhex, decoct,
    deg2rad, exp, expm1, floor, fmod, getrandmax, hexdec, hypot, intdiv, is_finite, is_infinite, is_nan, lcg_value,
    log10, log1p, log, max, min, mt_getrandmax, mt_rand, mt_srand, octdec, pi, pow, rad2deg, rand, round, sin, sinh,
    sqrt, srand, tan, tanh,
    
    json_decode, json_encode, json_last_error_msg, json_last_error,
    
    yaml_emit, yaml_parse,
');

/*
 * Deprecated
 */

ifndef('DS', DIRECTORY_SEPARATOR);
ifndef('TEMPLATE_COMPATIBILITY', false);
ifndef('IMAGE_COMPATIBILITY_LEVEL', 1);
ifndef('PROFILE_PLUGINS', false);
ifndef('ES_LOGGING', 1);
ifndef('PHP_ERROR_HANDLER', false);
ifndef('DEBUG_FRAME', false);
ifndef('MEDIAIMAGE_REGEX', '/^media\/image\/(?P<type>product)' .
    '\/(?P<id>\d+)\/(?P<size>xs|sm|md|lg|xl|os)\/(?P<name>[a-zA-Z0-9\-_\.]+)' .
    '(?:(?:~(?P<number>\d+))?)\.(?P<ext>jpg|jpeg|png|gif|webp)$/');
ifndef('PCLZIP_TEMPORARY_DIR', PFAD_ROOT . PFAD_COMPILEDIR);
ifndef('CATEGORY_FILTER_ITEM_LIMIT', -1);
ifndef('MULTILANG_URL_FALLBACK', false);

// Applying some settings
ini_set('default_charset', JTL_CHARSET);
mb_internal_encoding(strtoupper(JTL_CHARSET));
date_default_timezone_set(SHOP_TIMEZONE);
error_reporting(SHOP_LOG_LEVEL);
ini_set('session.use_trans_sid', '0');

/**
 * upgrader
 */
ifndef('SHOW_UPGRADER', false);
ifndef('SHOW_UPGRADE_CHANNEL_ALPHA', false);
ifndef('SHOW_UPGRADE_CHANNEL_BETA', false);
ifndef('SHOW_UPGRADE_CHANNEL_BLEEDING_EDGE', false);

/**
 * @param string $constant
 * @param mixed  $value
 */
function ifndef(string $constant, mixed $value): void
{
    defined($constant) || define($constant, $value);
}

/**
 * @param int   $hookID
 * @param array $args_arr
 */
function executeHook(int $hookID, array $args_arr = []): void
{
    HookManager::getInstance()->executeHook($hookID, $args_arr);
}

// Static defines (do not edit)
require_once __DIR__ . '/defines_inc.php';
require_once __DIR__ . '/hooks_inc.php';
