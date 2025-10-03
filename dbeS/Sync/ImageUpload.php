<?php

declare(strict_types=1);

namespace JTL\dbeS\Sync;

use JTL\dbeS\Starter;
use SimpleXMLElement;

/**
 * Class ImageUpload
 * @package JTL\dbeS\Sync
 */
final class ImageUpload extends AbstractSync
{
    public function handle(Starter $starter): void
    {
        foreach ($starter->getXML(true) as $item) {
            /**
             * @var string           $file
             * @var SimpleXMLElement $xml
             */
            [$file, $xml] = [\key($item), \reset($item)];
            if (\str_contains($file, 'images.xml')) {
                $this->handleInserts(\pathinfo($file, \PATHINFO_DIRNAME) . '/', $xml);
                return;
            }
        }
    }

    private function handleInserts(string $tmpDir, SimpleXMLElement $xml): void
    {
        $items = $this->getArray($xml);
        foreach ($items as $item) {
            $tmpfile = $tmpDir . $item->kBild;
            if (!\file_exists($tmpfile)) {
                $this->logger->notice('Cannot find image: {img}', ['img' => $tmpfile]);
                continue;
            }
            if (\copy($tmpfile, \PFAD_ROOT . \PFAD_MEDIA_IMAGE_STORAGE . $item->cPfad)) {
                $this->upsert('tbild', [$item], 'kBild');
                $this->db->update(
                    'tartikelpict',
                    'kBild',
                    (int)$item->kBild,
                    (object)['cPfad' => $item->cPfad]
                );
            } else {
                $this->logger->error(
                    'Error copying image "{source}" to "{target}"',
                    [
                        'source' => $tmpfile,
                        'target' => \PFAD_ROOT . \PFAD_MEDIA_IMAGE_STORAGE . $item->cPfad
                    ]
                );
            }
        }
    }

    /**
     * @param SimpleXMLElement $xml
     * @return \stdClass[]
     */
    private function getArray(SimpleXMLElement $xml): array
    {
        $items = [];
        foreach ($xml->children() as $child) {
            $items[] = (object)[
                'kBild' => (int)$child->attributes()->kBild,
                'cPfad' => (string)$child->attributes()->cHash
            ];
        }

        return $items;
    }
}
