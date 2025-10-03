<?php

declare(strict_types=1);

namespace JTL\dbeS\Sync;

use JTL\dbeS\Starter;
use JTL\Extensions\Config\Group;
use SimpleXMLElement;

/**
 * Class Configurations
 * @package JTL\dbeS\Sync
 */
final class ConfigGroups extends AbstractSync
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
            if ($fileName === 'del_konfig.xml') {
                $this->handleDeletes($xml);
            } elseif ($fileName === 'konfig.xml') {
                $this->handleInserts($xml);
            }
        }
    }

    /**
     * @param SimpleXMLElement $xml
     */
    private function handleInserts(SimpleXMLElement $xml): void
    {
        foreach ($xml->tkonfiggruppe as $groupData) {
            $group = $this->mapper->map($groupData, 'mKonfigGruppe');
            $this->upsert('tkonfiggruppe', [$group], 'kKonfiggruppe');
            foreach ($groupData->tkonfiggruppesprache as $localized) {
                $this->upsert(
                    'tkonfiggruppesprache',
                    [$this->mapper->map($localized, 'mKonfigSprache')],
                    'kKonfiggruppe',
                    'kSprache'
                );
            }
            $this->deleteConfigItem((int)$group->kKonfiggruppe);
            foreach ($groupData->tkonfigitem as $item) {
                $this->upsert(
                    'tkonfigitem',
                    [$this->mapper->map($item, 'mKonfigItem')],
                    'kKonfigitem'
                );
                foreach ($item->tkonfigitemsprache as $localized) {
                    $this->upsert(
                        'tkonfigitemsprache',
                        [$this->mapper->map($localized, 'mKonfigSprache')],
                        'kKonfigitem',
                        'kSprache'
                    );
                }
                foreach ($item->tkonfigitempreis as $price) {
                    $this->upsert(
                        'tkonfigitempreis',
                        [$this->mapper->map($price, 'mKonfigItemPreis')],
                        'kKonfigitem',
                        'kKundengruppe'
                    );
                }
            }
        }
    }

    /**
     * @param SimpleXMLElement $xml
     */
    private function handleDeletes(SimpleXMLElement $xml): void
    {
        if (!Group::checkLicense()) {
            return;
        }
        foreach ($xml->kKonfiggruppe as $groupID) {
            $this->deleteGroup((int)$groupID);
        }
    }

    private function deleteGroup(int $id): void
    {
        $this->db->delete('tkonfiggruppe', 'kKonfiggruppe', $id);
    }

    private function deleteConfigItem(int $id): void
    {
        $this->db->delete('tkonfigitem', 'kKonfiggruppe', $id);
    }
}
