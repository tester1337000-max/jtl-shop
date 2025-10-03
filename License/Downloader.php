<?php

declare(strict_types=1);

namespace JTL\License;

use GuzzleHttp\Client;
use JTL\License\Exception\ApiResultCodeException;
use JTL\License\Exception\ChecksumValidationException;
use JTL\License\Exception\DownloadValidationException;
use JTL\License\Exception\FilePermissionException;
use JTL\License\Struct\Release;

/**
 * Class Downloader
 * @package JTL\License
 */
class Downloader
{
    /**
     * @throws DownloadValidationException
     * @throws FilePermissionException
     * @throws ApiResultCodeException
     * @throws ChecksumValidationException
     */
    public function downloadRelease(Release $available): string
    {
        if (!$this->validateDownloadArchive($available)) {
            throw new DownloadValidationException('Could not validate archive');
        }
        $url = $available->getDownloadURL();
        if ($url === null) {
            throw new DownloadValidationException('No download URL found');
        }
        $file = $this->downloadItemArchive($url, \basename($url));
        if (!$this->validateChecksum($file, $available->getChecksum())) {
            if (\file_exists($file)) {
                \unlink($file);
            }
            throw new ChecksumValidationException('Archive checksum validation failed');
        }

        return $file;
    }

    /**
     * @throws FilePermissionException
     * @throws ApiResultCodeException
     */
    private function downloadItemArchive(string $url, string $targetName): string
    {
        $fileName = \PFAD_ROOT . \PFAD_DBES_TMP . \basename($targetName);
        $resource = \fopen($fileName, 'wb+');
        if ($resource === false) {
            throw new FilePermissionException('Cannot open file ' . $fileName);
        }
        $client = new Client();
        $res    = $client->request('GET', $url, ['sink' => $resource]);
        if ($res->getStatusCode() !== 200) {
            throw new ApiResultCodeException('Did not get 200 OK result code form api but ' . $res->getStatusCode());
        }

        return $fileName;
    }

    private function validateDownloadArchive(Release $available): bool
    {
        if ($available->getDownloadURL() === null) {
            return false;
        }
        if (\parse_url($available->getDownloadURL(), \PHP_URL_SCHEME) !== 'https') {
            return false;
        }
        // @todo: signature validation
        return true;
    }

    private function validateChecksum(string $file, string $checksum): bool
    {
        return \sha1_file($file) === $checksum;
    }
}
