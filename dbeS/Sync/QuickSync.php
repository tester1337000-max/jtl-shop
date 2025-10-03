<?php

declare(strict_types=1);

namespace JTL\dbeS\Sync;

use JTL\dbeS\Starter;
use JTL\Settings\Option\Product;
use JTL\Settings\Section;
use JTL\Settings\Settings;
use stdClass;

use function Functional\map;

/**
 * Class QuickSync
 * @package JTL\dbeS\Sync
 */
final class QuickSync extends AbstractSync
{
    public function handle(Starter $starter): void
    {
        $this->db->query('START TRANSACTION');
        foreach ($starter->getXML() as $item) {
            /**
             * @var string               $file
             * @var array<string, mixed> $xml
             */
            [$file, $xml] = [\key($item), \reset($item)];
            if (\str_contains($file, 'quicksync.xml')) {
                $this->handleInserts($xml);
            }
        }
        $this->db->query('COMMIT');
    }

    /**
     * @param array<mixed> $xml
     */
    private function handleInserts(array $xml): void
    {
        $source = $xml['quicksync']['tartikel'] ?? null;
        if (!\is_array($source)) {
            return;
        }
        $products = $this->mapper->mapArray($xml['quicksync'], 'tartikel', 'mArtikelQuickSync');
        $count    = \count($products);
        if ($count < 2) {
            $this->handleNewPriceFormat((int)$products[0]->kArtikel, $source);
            $this->handlePriceHistory((int)$products[0]->kArtikel, $source);
        } else {
            for ($i = 0; $i < $count; ++$i) {
                $this->handleNewPriceFormat((int)$products[$i]->kArtikel, $source[$i]);
                $this->handlePriceHistory((int)$products[$i]->kArtikel, $source[$i]);
            }
        }
        $this->insertProducts($products);
    }

    /**
     * @param stdClass[] $products
     */
    private function insertProducts(array $products): void
    {
        $clearTags = [];
        $minStock  = Settings::fromSection(Section::PRODUCT)->int(Product::AVAILABILITY_REQ_MIN_QTY);
        foreach ($products as $product) {
            $id = (int)$product->kArtikel;
            if (isset($product->fLagerbestand) && $product->fLagerbestand > 0) {
                $delta = $this->db->getSingleObject(
                    "SELECT SUM(pos.nAnzahl) AS totalquantity
                        FROM tbestellung b
                        JOIN twarenkorbpos pos
                            ON pos.kWarenkorb = b.kWarenkorb
                        WHERE b.cAbgeholt = 'N'
                            AND pos.kArtikel = :pid",
                    ['pid' => $id]
                );
                if ($delta !== null && $delta->totalquantity > 0) {
                    $product->fLagerbestand -= $delta->totalquantity;
                }
            }
            if ($product->fLagerbestand < 0) {
                $product->fLagerbestand = 0;
            }

            $upd                        = new stdClass();
            $upd->fLagerbestand         = $product->fLagerbestand;
            $upd->fStandardpreisNetto   = $product->fStandardpreisNetto;
            $upd->dLetzteAktualisierung = 'NOW()';
            $this->db->update('tartikel', 'kArtikel', $id, $upd);
            \executeHook(\HOOK_QUICKSYNC_XML_BEARBEITEINSERT, ['oArtikel' => $product]);
            $parentProduct = $this->db->select(
                'tartikel',
                'kArtikel',
                $id,
                null,
                null,
                null,
                null,
                false,
                'kVaterArtikel'
            );
            if ($parentProduct !== null && !empty($parentProduct->kVaterArtikel)) {
                $clearTags[] = (int)$parentProduct->kVaterArtikel;
            }
            $clearTags[] = $id;
            $this->sendAvailabilityMails($product, $minStock);
        }
        $clearTags = \array_unique($clearTags);
        $this->cache->flushTags(map($clearTags, fn($e): string => \CACHING_GROUP_ARTICLE . '_' . $e));
    }
}
