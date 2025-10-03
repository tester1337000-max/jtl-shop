<?php

declare(strict_types=1);

namespace JTL\dbeS\Sync;

use JTL\dbeS\Starter;

/**
 * Class Data
 * @package JTL\dbeS\Sync
 */
final class Data extends AbstractSync
{
    public function handle(Starter $starter): void
    {
        foreach ($starter->getXML() as $item) {
            /**
             * @var string               $file
             * @var array<string, mixed> $xml
             */
            [$file, $xml] = [\key($item), \reset($item)];
            if (\str_contains($file, 'ack_verfuegbarkeitsbenachrichtigungen.xml')) {
                $this->handleAvailabilityMessages($xml);
            } elseif (\str_contains($file, 'ack_uploadqueue.xml')) {
                $this->handleUploadQueueAck($xml);
            }
        }
    }

    /**
     * @param array<mixed> $xml
     */
    private function handleAvailabilityMessages(array $xml): void
    {
        $source = $xml['ack_verfuegbarkeitsbenachrichtigungen']['kVerfuegbarkeitsbenachrichtigung'] ?? [];
        if (\is_numeric($source)) {
            $source = [$source];
        }
        foreach (\array_filter(\array_map('\intval', $source)) as $msg) {
            $this->db->update(
                'tverfuegbarkeitsbenachrichtigung',
                'kVerfuegbarkeitsbenachrichtigung',
                $msg,
                (object)['cAbgeholt' => 'Y']
            );
        }
    }

    /**
     * @param array<mixed> $xml
     */
    private function handleUploadQueueAck(array $xml): void
    {
        $source = $xml['ack_uploadqueue']['kuploadqueue'] ?? [];
        if (\is_numeric($source)) {
            $source = [$source];
        }
        foreach (\array_filter(\array_map('\intval', $source)) as $queueID) {
            if ($queueID > 0) {
                $this->db->delete('tuploadqueue', 'kUploadqueue', $queueID);
            }
        }
    }
}
