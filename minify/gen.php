<?php

declare(strict_types=1);

$send_400 = static function ($content = 'Bad URL') {
    http_response_code(400);
    die($content);
};

$send_301 = static function ($url) {
    http_response_code(301);
    header('Cache-Control: max-age=31536000');
    header('Location: ' . $url);
    exit;
};


/**
 * Get the name of the current cache directory within static/. E.g. "1467089473"
 *
 * @param bool $auto_create Automatically create the directory if missing?
 * @return null|string null if missing or can't create
 */
$get_cache_time = static function (bool $auto_create = true) {
    foreach (scandir(PFAD_ROOT . PATH_STATIC_MINIFY) as $entry) {
        if (ctype_digit($entry)) {
            return $entry;
        }
    }

    if (!$auto_create) {
        return null;
    }

    $time = (string)time();
    $dir  = PFAD_ROOT . PATH_STATIC_MINIFY . $time;
    if (!mkdir($dir) && !is_dir($dir)) {
        return null;
    }

    return $time;
};

$app = (require __DIR__ . '/bootstrap.php');
/* @var Minify\App $app */

if (!$app->config->enableStatic) {
    die('Minify static serving is not enabled. Set $min_enableStatic = true; in config.php');
}

if (!is_writable(PFAD_ROOT . PATH_STATIC_MINIFY)) {
    http_response_code(500);
    die('Directory is not writable.');
}
$root_uri = dirname($_SERVER['SCRIPT_NAME']);
$uri      = substr($_SERVER['REQUEST_URI'], strlen($root_uri));
if ($uri === '' || !str_contains($_SERVER['REQUEST_URI'], PFAD_INCLUDES_LIBS)) {
    // handle rewrite of templates_c/min/static to /static
    $uri = substr($_SERVER['REQUEST_URI'], strpos($_SERVER['REQUEST_URI'], '/static') + strlen('/static'));
}
if (!preg_match('~^/(\d+)/(.*)$~', $uri, $m)) {
    http_response_code(404);
    die('File not found');
}

$requested_cache_dir = $m[1];
$query               = $m[2];

// we basically want canonical querystrings because we make a file for each one.
// manual parsing is the only way to do this. The MinApp controller will validate
// these parameters anyway.
$get_params = [];
foreach (explode('&', $query) as $piece) {
    if (!str_contains($piece, '=')) {
        $send_400();
    }

    [$key, $value] = explode('=', $piece, 2);
    if (!in_array($key, ['f', 'g', 'b', 'z'])) {
        $send_400();
    }

    if (isset($get_params[$key])) {
        // already used
        $send_400();
    }

    if ($key === 'z' && !preg_match('~^\.(css|js)$~', $value, $m)) {
        $send_400();
    }

    $get_params[$key] = urldecode($value);
}

$cache_time = $get_cache_time();
if (!$cache_time) {
    http_response_code(500);
    die('Directory is not writable.');
}

$app->env = new Minify_Env([
    'get' => $get_params,
]);
$ctrl     = $app->controller;
$options  = $app->serveOptions;
$sources  = $ctrl->createConfiguration($options)->getSources();
if (!$sources) {
    http_response_code(404);
    die('File not found');
}
if ($sources[0]->getId() === 'id::missingFile') {
    $send_400('Bad URL: missing file');
}

// we need URL to end in appropriate extension
$type = $sources[0]->getContentType();
$ext  = ($type === Minify::TYPE_JS) ? '.js' : '.css';
if (!str_ends_with($query, $ext)) {
    $send_301("$root_uri/$cache_time/$query&z=$ext");
}

// fix the cache dir in the URL
if ($cache_time !== $requested_cache_dir) {
    $send_301("$root_uri/$cache_time/$query");
}

$content = $app->minify->combine($sources);

// save and send file
$file = PFAD_ROOT . PATH_STATIC_MINIFY . $cache_time . '/' . $query;
if (!is_dir(dirname($file))) {
    mkdir(dirname($file), 0777, true);
}

file_put_contents($file, $content);

header("Content-Type: $type;charset=utf-8");
header('Cache-Control: max-age=31536000');
echo $content;
