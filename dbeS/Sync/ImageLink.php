<?php

declare(strict_types=1);

namespace JTL\dbeS\Sync;

use JTL\dbeS\Starter;
use JTL\Media\Image\Product;
use SimpleXMLElement;
use stdClass;

use function Functional\map;

/**
 * Class ImageLink
 * @package JTL\dbeS\Sync
 */
final class ImageLink extends AbstractSync
{
    public function handle(Starter $starter): void
    {
        $productIDs = [];
        foreach ($starter->getXML(true) as $item) {
            /**
             * @var string           $file
             * @var SimpleXMLElement $xml
             */
            [$file, $xml] = [\key($item), \reset($item)];
            if (\str_contains($file, 'del_bildartikellink.xml')) {
                $productIDs[] = $this->handleDeletes($xml);
            } elseif (\str_contains($file, 'bildartikellink.xml')) {
                $productIDs[] = $this->handleInserts($xml);
            }
        }
        $productIDs = \array_merge(...$productIDs);
        Product::clearCache($productIDs);
        $this->cache->flushTags(map($productIDs, fn(int $pid): string => \CACHING_GROUP_ARTICLE . '_' . $pid));
    }

    /**
     * @param SimpleXMLElement $xml
     * @return int[]
     */
    private function handleInserts(SimpleXMLElement $xml): array
    {
        $productIDs = [];
        foreach ($this->getArray($xml) as $item) {
            // save old image path if any - needed to check if image file can be deleted afterwards
            $old = $this->db->select('tartikelpict', 'kArtikel', (int)$item->kArtikel, 'nNr', (int)$item->nNr);
            // delete link first. Important because jtl-wawi does not send del_bildartikellink when image is updated.
            $this->db->delete(
                'tartikelpict',
                ['kArtikel', 'nNr'],
                [(int)$item->kArtikel, (int)$item->nNr]
            );
            $productIDs[] = (int)$item->kArtikel;
            $this->upsert('tartikelpict', [$item], 'kArtikelPict');
            if ($old !== null) {
                $used = $this->db->getSingleInt(
                    'SELECT COUNT(*) AS cnt
                        FROM tartikelpict
                        WHERE cPfad = :path',
                    'cnt',
                    ['path' => $old->cPfad]
                );
                // the old image file is not used by any other product - can be deleted from filesystem
                if ($used === 0) {
                    $this->deleteProductImage($old->cPfad);
                }
            }

            \executeHook(\HOOK_ARTIKELBILD_XML_BEARBEITET, [
                'kArtikel' => (int)$item->kArtikel,
                'kBild'    => (int)$item->kBild
            ]);
        }

        return $productIDs;
    }

    /**
     * @param SimpleXMLElement $xml
     * @return int[]
     */
    private function handleDeletes(SimpleXMLElement $xml): array
    {
        $productIDs = [];
        foreach ($this->getItemsToDelete($xml) as $item) {
            $this->deleteImageItem($item);
            $productIDs[] = $item->kArtikel;
        }

        return $productIDs;
    }

    private function deleteImageItem(stdClass $item): void
    {
        $image = $this->db->select('tartikelpict', 'kArtikel', $item->kArtikel, 'nNr', $item->nNr);
        if ($image === null) {
            return;
        }
        // is last reference
        $res = $this->db->getSingleInt(
            'SELECT COUNT(*) AS cnt FROM tartikelpict WHERE kBild = :iid',
            'cnt',
            ['iid' => (int)$image->kBild]
        );
        if ($res === 1) {
            $this->db->delete('tbild', 'kBild', (int)$image->kBild);
            $this->deleteProductImage($image->cPfad);
        }
        $this->db->delete(
            'tartikelpict',
            ['kArtikel', 'nNr'],
            [(int)$item->kArtikel, (int)$item->nNr]
        );
    }

    /**
     * @param SimpleXMLElement $xml
     * @return stdClass[]
     */
    private function getItemsToDelete(SimpleXMLElement $xml): array
    {
        $items = [];
        foreach ($xml->children() as $child) {
            $items[] = (object)[
                'nNr'      => (int)$child->nNr,
                'kArtikel' => (int)$child->kArtikel
            ];
        }

        return $items;
    }

    /**
     * @param SimpleXMLElement $xml
     * @return stdClass[]
     */
    private function getArray(SimpleXMLElement $xml): array
    {
        $items = [];
        foreach ($xml->children() as $child) {
            $item    = (object)[
                'cPfad'        => '',
                'kBild'        => (int)$child->attributes()->kBild,
                'nNr'          => (int)$child->attributes()->nNr,
                'kArtikel'     => (int)$child->attributes()->kArtikel,
                'kArtikelPict' => (int)$child->attributes()->kArtikelPict
            ];
            $imageID = (int)$child->attributes()->kBild;
            $image   = $this->db->select('tbild', 'kBild', $imageID);
            if ($image !== null) {
                $item->cPfad = $image->cPfad;
                $items[]     = $item;
            } else {
                $this->logger->debug('Missing reference in tbild (Key: {key})', ['key' => $imageID]);
            }
        }

        return $items;
    }

    private function deleteProductImage(string $path): void
    {
        $storage = \PFAD_ROOT . \PFAD_MEDIA_IMAGE_STORAGE . $path;
        if (\file_exists($storage)) {
            @\unlink($storage);
        }
    }
}
