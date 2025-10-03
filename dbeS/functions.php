<?php

declare(strict_types=1);

use JTL\Shop;

function translateError(string $error): string
{
    if (preg_match('/Maximum execution time of (\d+) second.? exceeded/', $error, $matches)) {
        $seconds = (int)$matches[1];
        $error   = 'Maximale Ausführungszeit von ' . $seconds . ' Sekunden überschritten';
    } elseif (preg_match('/Allowed memory size of (\d+) bytes exhausted/', $error, $matches)) {
        $limit = (int)$matches[1];
        $error = 'Erlaubte Speichergröße von ' . $limit . ' Bytes erschöpft';
    }

    return mb_convert_encoding($error, 'ISO-8859-1', 'UTF-8');
}

function handleError(string $output): string
{
    $error = error_get_last();
    if ($error !== null && $error['type'] === E_ERROR) {
        $errorMsg = translateError($error['message']) . "\n";
        $errorMsg .= 'Datei: ' . ($error['file'] ?? '');
        Shop::Container()->getLogService()->error($errorMsg);
        if (ini_get('display_errors') !== '0') {
            return $errorMsg;
        }
    }

    return $output;
}

/**
 * prints fatal sync exception and exits with die()
 *
 * wawi codes:
 * 0: HTTP_NOERROR
 * 1: HTTP_DBERROR
 * 2: AUTH OK, ZIP CORRUPT
 * 3: HTTP_LOGIN
 * 4: HTTP_AUTH
 * 5: HTTP_BADINPUT
 * 6: HTTP_AUTHINVALID
 * 7: HTTP_AUTHCLOSED
 * 8: HTTP_CUSTOMERR
 * 9: HTTP_EBAYERROR
 *
 * @param string   $msg - Exception Message
 * @param int|null $wawiExceptionCode - code (0-9)
 */
function syncException(string $msg, ?int $wawiExceptionCode = null): never
{
    $output = '';
    if ($wawiExceptionCode !== null) {
        $output .= $wawiExceptionCode . "\n";
    }
    $output .= $msg;
    Shop::Container()->getLogService()->error('SyncException: ' . $output);
    die(mb_convert_encoding($output, 'ISO-8859-1', 'auto'));
}
