<?php

declare(strict_types=1);

namespace JTL\Backend\Settings\Sections;

use JTL\Helpers\Text;
use JTL\Shop;
use stdClass;

/**
 * Class PaymentMethod
 * @package JTL\Backend\Settings\Sections
 */
class PaymentMethod extends Base
{
    /**
     * @inheritdoc
     */
    public function update(array $data, bool $filter = true, array $tags = [\CACHING_GROUP_OPTION]): array
    {
        $unfiltered = $data;
        if ($filter === true) {
            $data = Text::filterXSS($data);
        }
        $updated = [];
        if ($this->loaded === false) {
            $this->load();
        }
        foreach ($this->getItems() as $item) {
            $id = $item->getValueName();
            if (!isset($data[$id])) {
                continue;
            }
            $ins                        = new stdClass();
            $ins->cWert                 = $data[$id];
            $ins->cName                 = $id;
            $ins->kEinstellungenSektion = \CONF_ZAHLUNGSARTEN;
            $ins->cModulId              = $data['cModulId'];
            $this->setConfigValue($ins, $item->getInputType() ?? 'text', $data, $unfiltered);
            if (\is_string($ins->cWert)) {
                $ins->cWert = \mb_substr($ins->cWert, 0, 255);
            }
            $this->db->delete(
                'teinstellungen',
                ['kEinstellungenSektion', 'cName'],
                [\CONF_ZAHLUNGSARTEN, $id]
            );
            $this->db->insert('teinstellungen', $ins);
            $updated[] = ['id' => $id, 'value' => $data[$id]];
        }
        Shop::Container()->getCache()->flushTags($tags);

        return $updated;
    }
}
