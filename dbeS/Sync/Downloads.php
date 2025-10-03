<?php

declare(strict_types=1);

namespace JTL\dbeS\Sync;

use JTL\dbeS\Starter;
use JTL\Extensions\Download\Download;
use stdClass;

/**
 * Class Downloads
 * @package JTL\dbeS\Sync
 */
final class Downloads extends AbstractSync
{
    public function handle(Starter $starter): void
    {
        foreach ($starter->getXML() as $item) {
            /**
             * @var string               $file
             * @var array<string, mixed> $xml
             */
            [$file, $xml] = [\key($item), \reset($item)];
            if (\str_contains($file, 'del_download.xml')) {
                $this->handleDeletes($xml);
            } else {
                $this->handleInserts($xml);
            }
        }
    }

    /**
     * @param array<mixed> $xml
     */
    private function handleDeletes(array $xml): void
    {
        if (!Download::checkLicense()) {
            return;
        }
        $source = $xml['del_downloads']['kDownload'] ?? [];
        if (\is_numeric($source)) {
            $source = [$source];
        }
        foreach (\array_filter(\array_map('\intval', $source)) as $downloadID) {
            $this->delete($downloadID);
        }
    }

    /**
     * @param array<mixed> $xml
     */
    private function handleInserts(array $xml): void
    {
        $downloads = $this->mapper->mapArray($xml['tDownloads'], 'tDownload', 'mDownload');
        if (isset($xml['tDownloads']['tDownload attr']) && \is_array($xml['tDownloads']['tDownload attr'])) {
            if ($downloads[0]->kDownload > 0) {
                $this->handleDownload($xml['tDownloads']['tDownload'], $downloads[0]);
            }
        } else {
            foreach ($downloads as $i => $download) {
                if ($download->kDownload > 0) {
                    $this->handleDownload($xml['tDownloads']['tDownload'][$i], $download);
                }
            }
        }
    }

    /**
     * @param array<mixed> $xml
     */
    private function handleDownload(array $xml, stdClass $download): void
    {
        $localized = $this->mapper->mapArray($xml, 'tDownloadSprache', 'mDownloadSprache');
        if (\count($localized) > 0) {
            $this->upsert('tdownload', [$download], 'kDownload');
            foreach ($localized as $item) {
                $item->kDownload = $download->kDownload;
                $this->upsert('tdownloadsprache', [$item], 'kDownload', 'kSprache');
            }
        }
    }

    private function delete(int $id): void
    {
        $this->db->queryPrepared(
            'DELETE tdownload, tdownloadhistory, tdownloadsprache, tartikeldownload
            FROM tdownload
            JOIN tdownloadsprache 
                ON tdownloadsprache.kDownload = tdownload.kDownload
            LEFT JOIN tartikeldownload 
                ON tartikeldownload.kDownload = tdownload.kDownload
            LEFT JOIN tdownloadhistory 
                ON tdownloadhistory.kDownload = tdownload.kDownload
            WHERE tdownload.kDownload = :dlid',
            ['dlid' => $id]
        );
    }
}
