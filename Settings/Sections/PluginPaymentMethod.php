<?php

declare(strict_types=1);

namespace JTL\Backend\Settings\Sections;

use JTL\Backend\Settings\Item;
use JTL\DB\SqlObject;
use JTL\Helpers\Text;
use JTL\Shop;
use stdClass;

/**
 * Class PluginPaymentMethod
 * @package JTL\Backend\Settings\Sections
 */
class PluginPaymentMethod extends Base
{
    /**
     * @inheritdoc
     */
    public function load(?SqlObject $sql = null): void
    {
        if ($sql === null) {
            $sql = new SqlObject();
            $sql->setWhere(' 1 = 1');
        }

        $data = $this->db->getObjects(
            'SELECT *, kPluginEinstellungenConf AS kEinstellungenConf,
                kPluginEinstellungenConf AS kEinstellungenSektion
                FROM tplugineinstellungenconf
                WHERE ' . $sql->getWhere() . '
                 ORDER BY nSort',
            $sql->getParams()
        );

        $configItems = [];
        foreach ($data as $item) {
            if ($item->cConf === 'N' && ($item->cInputTyp === '' || $item->cInputTyp === null)) {
                $config = new Subsection();
            } else {
                $config = new Item();
            }
            $config->parseFromDB($item);
            if (\in_array($config->getInputType(), ['selectbox', 'listbox'], true)) {
                $setValues = $this->db->selectAll(
                    'tplugineinstellungenconfwerte',
                    'kPluginEinstellungenConf',
                    $config->getID(),
                    '*',
                    'nSort'
                );
                foreach ($setValues as $confKey) {
                    $confKey->cName = \__($confKey->cName);
                }
                $config->setValues($setValues);
            }
            $setValue = $this->db->select(
                'tplugineinstellungen',
                'kPlugin',
                $config->getPluginID(),
                'cName',
                $config->getValueName()
            );
            $config->setName(\__($config->getName()));
            $config->setSetValue(
                $setValue !== null && isset($setValue->cWert)
                    ? Text::htmlentities($setValue->cWert)
                    : null
            );
            $configItems[] = $config;
            $this->items[] = $config;
        }
        $this->subsections = [];
        $currentSubsection = null;
        foreach ($configItems as $item) {
            if (\get_class($item) === Subsection::class) {
                if ($currentSubsection !== null) {
                    $this->subsections[] = $currentSubsection;
                }
                $currentSubsection = $item;
            } else {
                if ($currentSubsection === null) {
                    $currentSubsection = new Subsection();
                }
                $currentSubsection->addItem($item);
            }
        }
        if ($currentSubsection !== null) {
            $this->subsections[] = $currentSubsection;
        }
    }

    /**
     * @inheritdoc
     */
    public function update(array $data, bool $filter = true, array $tags = [\CACHING_GROUP_OPTION]): array
    {
        $unfiltered = $data;
        if ($filter === true) {
            $data = Text::filterXSS($data);
        }
        $kPlugin = $data['kPlugin'];
        $updated = [];
        if ($this->loaded === false) {
            $this->load();
        }
        foreach ($this->getItems() as $item) {
            $id = $item->getValueName();
            if (!isset($data[$id])) {
                continue;
            }
            $ins          = new stdClass();
            $ins->cName   = $id;
            $ins->cWert   = $data[$id];
            $ins->kPlugin = $kPlugin;
            $this->setConfigValue($ins, $item->getInputType() ?? 'text', $data, $unfiltered);
            if (\is_string($ins->cWert)) {
                $ins->cWert = \mb_substr($ins->cWert, 0, 255);
            }
            $this->db->delete(
                'tplugineinstellungen',
                ['kPlugin', 'cName'],
                [$kPlugin, $id]
            );
            $this->db->insert('tplugineinstellungen', $ins);
            $updated[] = ['id' => $id, 'value' => $data[$id]];
        }
        Shop::Container()->getCache()->flushTags($tags);

        return $updated;
    }
}
