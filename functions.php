<?php

declare(strict_types=1);

/**
 * @param string $text
 * @param int    $sec
 * @return resource
 */
function createCaptcha(string $text, int $sec)
{
    $fonts = getFonts();
    $font  = __DIR__ . '/ttf/' . $fonts[array_rand($fonts)];
    $text  = mb_convert_case($text, MB_CASE_UPPER);
    $im    = imagecreatetruecolor(200, 60);
    imagefilledrectangle($im, 0, 0, 199, 59, imagecolorallocate($im, 255, 255, 255));

    if ($sec >= 3) {
        for ($i = 0; $i < 8; $i++) {
            imageline(
                $im,
                random_int(0, 200),
                random_int(0, 60),
                random_int(0, 200),
                random_int(0, 60),
                imagecolorallocate($im, random_int(0, 230), random_int(0, 230), random_int(0, 230))
            );
        }
    }

    imagettftext(
        $im,
        35,
        random_int(-20, 20),
        20,
        40,
        imagecolorallocate($im, random_int(0, 215), random_int(0, 215), random_int(0, 215)),
        $font,
        $text[0]
    );
    imagettftext(
        $im,
        35,
        random_int(-20, 20),
        70,
        40,
        imagecolorallocate($im, random_int(0, 215), random_int(0, 215), random_int(0, 215)),
        $font,
        $text[1]
    );
    imagettftext(
        $im,
        35,
        random_int(-20, 20),
        110,
        40,
        imagecolorallocate($im, random_int(0, 215), random_int(0, 215), random_int(0, 215)),
        $font,
        $text[2]
    );
    imagettftext(
        $im,
        35,
        random_int(-20, 20),
        150,
        40,
        imagecolorallocate($im, random_int(0, 215), random_int(0, 215), random_int(0, 215)),
        $font,
        $text[3]
    );

    if ($sec >= 3) {
        for ($i = 0; $i < 8; $i++) {
            imageline(
                $im,
                random_int(0, 200),
                random_int(0, 60),
                random_int(0, 200),
                random_int(0, 60),
                imagecolorallocate($im, random_int(0, 250), random_int(0, 250), random_int(0, 250))
            );
        }
    }

    return $im;
}

/**
 * @param string $encoded
 * @return string
 */
function decodeCode(string $encoded): string
{
    if (!$encoded) {
        return '0';
    }
    $key  = BLOWFISH_KEY;
    $mod1 = (mb_ord($key[0]) + mb_ord($key[1]) + mb_ord($key[2])) % 9 + 1;
    $mod2 = mb_strlen($_SERVER['DOCUMENT_ROOT'] ?? PFAD_ROOT) % 9 + 1;

    $s1e = (int)mb_substr($encoded, 12, 3) + $mod2 - $mod1 - 123;
    $s2e = (int)mb_substr($encoded, 15, 3) + $mod1 - $mod2 - 234;
    $s3e = (int)mb_substr($encoded, 3, 3) - $mod1 - 345;
    $s4e = (int)mb_substr($encoded, 7, 3) - $mod2 - 456;

    return chr($s1e) . chr($s2e) . chr($s3e) . chr($s4e);
}

function getFonts(): array
{
    $fonts  = [];
    $folder = dir(__DIR__ . '/ttf/');
    while ($font = $folder->read()) {
        if (mb_stripos($font, '.ttf') !== false) {
            $fonts[] = $font;
        }
    }
    $folder->close();

    return $fonts;
}

function outputCaptcha(): void
{
    header('Content-type: image/png');
    imagepng(createCaptcha(decodeCode((string)$_GET['c']), (int)$_GET['s']));
}
