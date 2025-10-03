<?php

declare(strict_types=1);

namespace JTL\dbeS\Sync;

use DateTimeImmutable;
use JTL\dbeS\Starter;
use SimpleXMLElement;

/**
 * Class DeliveryNotes
 * @package JTL\dbeS\Sync
 */
final class DeliveryNotes extends AbstractSync
{
    public function handle(Starter $starter): void
    {
        foreach ($starter->getXML(true) as $item) {
            /**
             * @var string           $file
             * @var SimpleXMLElement $xml
             */
            [$file, $xml] = [\key($item), \reset($item)];

            $fileName = \pathinfo($file, \PATHINFO_BASENAME);
            if ($fileName === 'lief.xml') {
                $this->handleInserts($xml);
            } elseif ($fileName === 'del_lief.xml') {
                $this->handleDeletes($xml);
            }
        }
    }

    /**
     * @param SimpleXMLElement $xml
     */
    private function handleInserts(SimpleXMLElement $xml): void
    {
        foreach ($xml->tlieferschein as $item) {
            $deliveryNote = $this->mapper->map($item, 'mLieferschein');
            if ((int)$deliveryNote->kInetBestellung <= 0) {
                continue;
            }
            $deliveryNote->dErstellt = (new DateTimeImmutable($deliveryNote->dErstellt))->getTimestamp();
            $this->upsert('tlieferschein', [$deliveryNote], 'kLieferschein');

            foreach ($item->tlieferscheinpos as $xmlItem) {
                $sItem                = $this->mapper->map($xmlItem, 'mLieferscheinpos');
                $sItem->kLieferschein = $deliveryNote->kLieferschein;
                $this->upsert('tlieferscheinpos', [$sItem], 'kLieferscheinPos');

                foreach ($xmlItem->tlieferscheinposInfo as $info) {
                    $posInfo                   = $this->mapper->map($info, 'mLieferscheinposinfo');
                    $posInfo->kLieferscheinPos = $sItem->kLieferscheinPos;
                    $this->upsert('tlieferscheinposinfo', [$posInfo], 'kLieferscheinPosInfo');
                }
            }

            \executeHook(\HOOK_DELIVERYNOTES_XML_INSERT, ['deliveryNote' => $item]);
            foreach ($item->tversand as $shipping) {
                $shipping                = $this->mapper->map($shipping, 'mVersand');
                $shipping->kLieferschein = $deliveryNote->kLieferschein;
                $shipping->dErstellt     = (new DateTimeImmutable($shipping->dErstellt))->getTimestamp();
                $this->upsert('tversand', [$shipping], 'kVersand');

                \executeHook(\HOOK_DELIVERYNOTES_XML_SHIPPING, ['shipping' => $shipping]);
            }
        }
    }

    /**
     * @param SimpleXMLElement $xml
     */
    private function handleDeletes(SimpleXMLElement $xml): void
    {
        $items = $xml->kLieferschein;
        if (!\is_array($items)) {
            $items = (array)$items;
        }
        foreach (\array_filter(\array_map('\intval', $items)) as $id) {
            \executeHook(\HOOK_DELIVERYNOTES_XML_DELETE, ['deliveryNoteID' => $id]);

            $this->db->delete('tversand', 'kLieferschein', $id);
            $this->db->delete('tlieferschein', 'kLieferschein', $id);
            foreach (
                $this->db->selectAll(
                    'tlieferscheinpos',
                    'kLieferschein',
                    $id,
                    'kLieferscheinPos'
                ) as $item
            ) {
                $this->db->delete(
                    'tlieferscheinpos',
                    'kLieferscheinPos',
                    (int)$item->kLieferscheinPos
                );
                $this->db->delete(
                    'tlieferscheinposinfo',
                    'kLieferscheinPos',
                    (int)$item->kLieferscheinPos
                );
            }
        }
    }
}
