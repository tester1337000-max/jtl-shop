<?php

declare(strict_types=1);

namespace JTL\dbeS\Sync;

use JTL\dbeS\Starter;
use SimpleXMLElement;
use stdClass;

/**
 * Class ImageCheck
 * @package JTL\dbeS\Sync
 */
final class ImageCheck extends AbstractSync
{
    public function handle(Starter $starter): void
    {
        foreach ($starter->getXML(true) as $item) {
            /**
             * @var string           $file
             * @var SimpleXMLElement $xml
             */
            [$file, $xml] = [\key($item), \reset($item)];
            if (\str_contains($file, 'bildercheck.xml')) {
                $this->handleCheck($xml);
            }
        }
    }

    private function handleCheck(SimpleXMLElement $xml): never
    {
        $found  = [];
        $sqls   = [];
        $object = $this->getObject($xml);
        foreach ($object->items as $item) {
            $hash   = $this->db->escape($item->hash);
            $sqls[] = '(kBild = ' . $item->id . " && cPfad = '" . $hash . "')";
        }
        $sqlOr = \implode(' || ', $sqls);
        foreach ($this->db->getObjects('SELECT kBild AS id, cPfad AS hash FROM tbild WHERE ' . $sqlOr) as $image) {
            $image->id = (int)$image->id;
            $storage   = \PFAD_ROOT . \PFAD_MEDIA_IMAGE_STORAGE . $image->hash;
            if (\file_exists($storage)) {
                $found[] = $image->id;
            } else {
                $this->logger->debug(
                    'Dropping orphan {file} -> {hash}: no such file',
                    ['file' => $image->id, 'hash' => $image->hash]
                );
                $this->db->delete('tbild', 'kBild', $image->id);
                $this->db->delete('tartikelpict', 'kBild', $image->id);
            }
        }
        $missing = \array_filter($object->items, static fn($item): bool => !\in_array($item->id, $found, true));
        $ids     = \array_map(static fn($item) => $item->id, $missing);
        $idlist  = \implode(';', $ids);
        $this->pushResponse("0;\n<bildcheck><notfound>" . $idlist . '</notfound></bildcheck>');
    }

    private function pushResponse(string $content): never
    {
        \ob_clean();
        echo $content;
        exit;
    }

    private function getObject(SimpleXMLElement $xml): stdClass
    {
        $cloudURL = (string)$xml->attributes()->cloudURL;
        $check    = (object)[
            'url'   => $cloudURL,
            'cloud' => \strlen($cloudURL) > 0,
            'items' => []
        ];
        foreach ($xml->children() as $child) {
            $check->items[] = (object)[
                'id'   => (int)$child->attributes()->kBild,
                'hash' => (string)$child->attributes()->cHash
            ];
        }

        return $check;
    }
}
