<?php

declare(strict_types=1);

namespace JTL\dbeS\Push;

use JTL\Shop;
use ZipArchive;

/**
 * Class MediaFiles
 * @package JTL\dbeS\Push
 */
final class MediaFiles extends AbstractPush
{
    /**
     * @return string|no-return
     */
    public function getData()
    {
        $xml     = '<?xml version="1.0" ?>' . "\n";
        $xml     .= '<mediafiles url="' . Shop::getURL() . '/' . \PFAD_MEDIAFILES . '">' . "\n";
        $xml     .= $this->getDirContent(\PFAD_ROOT . \PFAD_MEDIAFILES, false);
        $xml     .= $this->getDirContent(\PFAD_ROOT . \PFAD_MEDIAFILES, true);
        $xml     .= '</mediafiles>' . "\n";
        $zip     = \time() . '.jtl';
        $xmlfile = \fopen(self::TEMP_DIR . self::XML_FILE, 'wb');
        if ($xmlfile === false) {
            \syncException('Unable to open file: ' . self::TEMP_DIR . self::XML_FILE, 5);
        }
        \fwrite($xmlfile, $xml);
        \fclose($xmlfile);
        if (!\file_exists(self::TEMP_DIR . self::XML_FILE)) {
            return $xml;
        }
        $archive = new ZipArchive();
        if (
            $archive->open(self::TEMP_DIR . $zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== false
            && $archive->addFile(self::TEMP_DIR . self::XML_FILE, self::XML_FILE) !== false
        ) {
            $archive->close();
            \readfile(self::TEMP_DIR . $zip);
            exit;
        }
        $archive->close();
        \syncException($archive->getStatusString());
    }

    private function getDirContent(string $dir, bool $filesOnly): string
    {
        $xml    = '';
        $handle = \opendir($dir);
        if ($handle === false) {
            return $xml;
        }
        while (($file = \readdir($handle)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (!$filesOnly && \is_dir($dir . '/' . $file)) {
                $xml .= '<dir cName="' . $file . '">' . "\n";
                $xml .= $this->getDirContent($dir . '/' . $file, false);
                $xml .= $this->getDirContent($dir . '/' . $file, true);
                $xml .= "</dir>\n";
            } elseif ($filesOnly && \is_file($dir . '/' . $file)) {
                $time = \filemtime($dir . '/' . $file) ?: null;
                if ($time === null) {
                    $this->logger->warning(
                        'Could not get filemtime for file {dir}/{file}',
                        ['dir' => $dir, 'file' => $file]
                    );
                }
                $xml .= '<file cName="' . $file . '" nSize="' . \filesize($dir . '/' . $file) . '" dTime="'
                    . \date('Y-m-d H:i:s', $time) . '"/>' . "\n";
            }
        }
        \closedir($handle);

        return $xml;
    }
}
