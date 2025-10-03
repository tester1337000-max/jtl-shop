<?php

declare(strict_types=1);

namespace JTL\Backend\Settings\Sections;

use JTL\Backend\Settings\Item;
use JTL\Backend\Settings\Manager;
use JTL\DB\DbInterface;
use JTL\DB\SqlObject;
use JTL\Helpers\Text;
use JTL\L10n\GetText;
use JTL\MagicCompatibilityTrait;
use JTL\Settings\Option\Product;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use stdClass;

use function Functional\filter;
use function Functional\flatten;

/**
 * Class Base
 * @package JTL\Backend\Settings\Sections
 */
class Base implements SectionInterface
{
    use MagicCompatibilityTrait;

    protected bool $hasSectionMarkup = false;

    protected DbInterface $db;

    protected JTLSmarty $smarty;

    protected string $name = '';

    protected string $sectionMarkup = '';

    protected int $menuID = 0;

    protected int $sortID = 0;

    protected int $configCount = 0;

    protected string $permission;

    /**
     * @var array<mixed>
     */
    protected array $configData;

    protected GetText $getText;

    /**
     * @var Item[]
     */
    protected array $items = [];

    /**
     * @var Subsection[]
     */
    protected array $subsections = [];

    protected ?string $url;

    protected int $updateErrors = 0;

    protected bool $loaded = false;

    /**
     * @var array<string, string>
     */
    protected static array $mapping = [
        'cName'                 => 'Name',
        'kEinstellungenSektion' => 'SectionID',
        'nSort'                 => 'Sort',
        'anz'                   => 'ConfigCount',
        'kAdminmenueGruppe'     => 'MenuID'
    ];

    /**
     * @inheritdoc
     */
    public function __construct(protected Manager $manager, protected int $id)
    {
        $this->db      = $manager->getDB();
        $this->smarty  = $manager->getSmarty();
        $this->getText = $manager->getGetText();
        $this->initBaseData();
    }

    /**
     * @inheritdoc
     */
    public function load(?SqlObject $sql = null): void
    {
        $sql         = $this->sanitizeSQL($sql);
        $data        = $this->db->getObjects(
            'SELECT ec.*, e.cWert AS currentValue, ted.cWert AS defaultValue
                FROM teinstellungenconf AS ec
                LEFT JOIN teinstellungen AS e
                    ON e.cName = ec.cWertName
                LEFT JOIN teinstellungen_default AS ted
                    ON ted.cName = ec.cWertName
                WHERE ' . $sql->getWhere() . '
                GROUP BY ec.kEinstellungenConf
                ORDER BY ' . $sql->getOrder(),
            $sql->getParams()
        );
        $configItems = [];
        foreach ($data as $item) {
            $config = new Item();
            if ($item->cConf === 'N' && ($item->cInputTyp === '' || $item->cInputTyp === null)) {
                $config = new Subsection();
            }
            $config->parseFromDB($item);
            $this->getText->localizeConfig($config);
            $this->setConfigValues($config);
            $configItems[] = $config;
            $this->items[] = $config;
        }
        $this->initSubsections($configItems);
    }

    protected function initBaseData(): void
    {
        $data = $this->db->select('teinstellungensektion', 'kEinstellungenSektion', $this->id);
        if ($data !== null) {
            $this->configCount = $this->db->getSingleInt(
                "SELECT COUNT(*) AS cnt
                    FROM teinstellungenconf
                    WHERE kEinstellungenSektion = :sid
                        AND cConf = 'Y'
                        AND nStandardAnzeigen = 1
                        AND nModul = 0",
                'cnt',
                ['sid' => $this->id]
            );
            $this->name        = \__('configsection_' . $this->id);
            $this->menuID      = (int)$data->kAdminmenueGruppe;
            $this->sortID      = (int)$data->nSort;
            $this->permission  = $data->cRecht;
        }
    }

    protected function validateNumberRange(int $min, int $max, Item $item, mixed $confValue): bool
    {
        if ($min <= $confValue && $confValue <= $max) {
            return true;
        }
        $this->manager->getAlertService()->addDanger(
            \sprintf(\__('errrorNumberRange'), \__($item->getName()), $min, $max),
            'errrorNumberRange'
        );

        return false;
    }

    /**
     * @inheritdoc
     */
    public function validate(Item $conf, mixed $confValue): bool
    {
        return match ($conf->getValueName()) {
            'bilder_jpg_quali' => $this->validateNumberRange(0, 100, $conf, $confValue),
            'cron_freq'        => $this->validateNumberRange(10, 999999, $conf, $confValue),
            default            => true,
        };
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
        $value   = new stdClass();
        $updated = [];
        if ($this->loaded === false) {
            $this->load();
        }
        foreach ($this->getItems() as $item) {
            $id = $item->getValueName();
            if (!isset($data[$id])) {
                continue;
            }
            if (empty($data[$id]) && ($item->getInputType() === 'pass')) {
                $data[$id]       = $item->getCurrentValue();
                $unfiltered[$id] = $item->getCurrentValue();
            }
            $value->cWert                 = $data[$id];
            $value->cName                 = $id;
            $value->kEinstellungenSektion = $item->getConfigSectionID();
            $this->setConfigValue($value, $item->getInputType() ?? 'text', $data, $unfiltered);
            if (!$this->validate($item, $data[$id])) {
                $this->updateErrors++;
                continue;
            }
            if (\is_array($data[$id])) {
                $this->manager->addLogListbox($id, $data[$id]);
            }
            if ($value->cName === Product::SIMILAR_ITEMS_QTY->value) {
                $value->cWert = \max((int)$value->cWert, 0);
            }
            $this->db->delete(
                'teinstellungen',
                ['kEinstellungenSektion', 'cName'],
                [$item->getConfigSectionID(), $id]
            );
            if (\is_array($data[$id])) {
                foreach ($data[$id] as $cWert) {
                    $value->cWert = $cWert;
                    $this->db->insert('teinstellungen', $value);
                }
            } else {
                $this->db->insert('teinstellungen', $value);
                $this->manager->addLog(
                    $id,
                    $item->getCurrentValue(),
                    $data[$id]
                );
            }
            $updated[] = ['id' => $id, 'value' => $data[$id]];
        }
        Shop::Container()->getCache()->flushTags($tags);

        return $updated;
    }

    /**
     * @inheritdoc
     */
    public function setConfigValue(stdClass $object, string $type, array $data, array $unfiltered): void
    {
        switch ($type) {
            case 'kommazahl':
                $object->cWert = (float)\str_replace(',', '.', $object->cWert);
                break;
            case 'zahl':
            case 'number':
                $object->cWert = (int)$object->cWert;
                break;
            case 'pass':
                $object->cWert = $unfiltered[$object->cName];
                break;
            default:
                break;
        }
    }

    /**
     * @inheritdoc
     */
    public function getSectionMarkup(): string
    {
        return $this->sectionMarkup;
    }

    /**
     * @inheritdoc
     */
    public function setSectionMarkup(string $markup): void
    {
        $this->sectionMarkup = $markup;
    }

    /**
     * @inheritdoc
     */
    public function filter(string $filter): array
    {
        $keys = [
            'configgroup_5_product_question'  => [
                'configgroup_5_product_question',
                'artikeldetails_fragezumprodukt_anzeigen',
                'artikeldetails_fragezumprodukt_email',
                'produktfrage_abfragen_anrede',
                'produktfrage_abfragen_vorname',
                'produktfrage_abfragen_nachname',
                'produktfrage_abfragen_firma',
                'produktfrage_abfragen_tel',
                'produktfrage_abfragen_fax',
                'produktfrage_abfragen_mobil',
                'produktfrage_kopiekunde',
                'produktfrage_sperre_minuten',
                'produktfrage_abfragen_captcha'
            ],
            'configgroup_5_product_available' => [
                'configgroup_5_product_available',
                'benachrichtigung_nutzen',
                'benachrichtigung_abfragen_vorname',
                'benachrichtigung_abfragen_nachname',
                'benachrichtigung_sperre_minuten',
                'benachrichtigung_abfragen_captcha',
                'benachrichtigung_min_lagernd'
            ]
        ];
        if (!\extension_loaded('soap')) {
            $keys['configgroup_6_vat_id'] = [
                'shop_ustid_bzstpruefung',
                'shop_ustid_force_remote_check'
            ];
        }

        $keysToFilter = ($filter !== '' && isset($keys[$filter]))
            ? $keys[$filter]
            : flatten($keys);
        $filtered     = [];
        $this->items  = filter(
            $this->getItems(),
            fn(Item $e): bool => !\in_array($e->getValueName(), $keysToFilter, true)
        );
        foreach ($this->getSubsections() as $subsection) {
            foreach ($subsection->getItems() as $i => $item) {
                if (\in_array($item->getValueName(), $keysToFilter, true)) {
                    $filtered[] = $item;
                    $subsection->removeItemAtIndex($i);
                }
            }
        }

        return $filtered;
    }

    /**
     * @inheritdoc
     */
    public function getID(): int
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     */
    public function setID(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @inheritdoc
     */
    public function getMenuID(): int
    {
        return $this->menuID;
    }

    /**
     * @inheritdoc
     */
    public function setMenuID(int $menuID): void
    {
        $this->menuID = $menuID;
    }

    /**
     * @inheritdoc
     */
    public function getSortID(): int
    {
        return $this->sortID;
    }

    /**
     * @inheritdoc
     */
    public function setSortID(int $sortID): void
    {
        $this->sortID = $sortID;
    }

    /**
     * @inheritdoc
     */
    public function getPermission(): string
    {
        return $this->permission;
    }

    /**
     * @inheritdoc
     */
    public function setPermission(string $permission): void
    {
        $this->permission = $permission;
    }

    /**
     * @return Item[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @inheritdoc
     */
    public function setItems(array $items): void
    {
        $this->items = $items;
    }

    /**
     * @inheritdoc
     */
    public function getConfigCount(): int
    {
        return $this->configCount;
    }

    /**
     * @inheritdoc
     */
    public function setConfigCount(int $configCount): void
    {
        $this->configCount = $configCount;
    }

    /**
     * @inheritdoc
     */
    public function getSubsections(): array
    {
        return $this->subsections;
    }

    /**
     * @param Subsection[] $subsections
     */
    public function setSubsections(array $subsections): void
    {
        $this->subsections = $subsections;
    }

    /**
     * @inheritdoc
     */
    public function hasSectionMarkup(): bool
    {
        return $this->hasSectionMarkup;
    }

    /**
     * @inheritdoc
     */
    public function setHasSectionMarkup(bool $hasSectionMarkup): void
    {
        $this->hasSectionMarkup = $hasSectionMarkup;
    }

    /**
     * @inheritdoc
     */
    public function getURL(): ?string
    {
        return $this->url;
    }

    /**
     * @inheritdoc
     */
    public function setURL(?string $url): void
    {
        $this->url = $url;
    }

    /**
     * @inheritdoc
     */
    public function getUpdateErrors(): int
    {
        return $this->updateErrors;
    }

    /**
     * @inheritdoc
     */
    public function setUpdateErrors(int $updateErrors): void
    {
        $this->updateErrors = $updateErrors;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $data = \get_object_vars($this);
        unset($data['smarty'], $data['db'], $data['getText'], $data['manager']);

        return $data;
    }

    private function sanitizeSQL(?SqlObject $sql): SqlObject
    {
        if ($sql === null) {
            $sql = new SqlObject();
            $sql->setWhere('ec.kEinstellungenSektion = :sid');
            $sql->addParam('sid', $this->id);
        }
        if ($sql->getOrder() === '') {
            $sql->setOrder('ec.nSort');
        }

        return $sql;
    }

    private function setConfigValues(Item|Subsection $config): void
    {
        if ($config->getInputType() === 'listbox' && $config->getID() === 492) {
            $config->setValues(
                $this->db->getObjects(
                    'SELECT kKundengruppe AS cWert, cName
                            FROM tkundengruppe
                            ORDER BY cStandard DESC'
                )
            );
        } elseif (\in_array($config->getInputType(), ['selectbox', 'listbox'], true)) {
            $setValues = $this->db->selectAll(
                'teinstellungenconfwerte',
                'kEinstellungenConf',
                $config->getID(),
                '*',
                'nSort'
            );
            $this->getText->localizeConfigValues($config, $setValues);
            $config->setValues($setValues);
        } elseif ($config->getInputType() === 'selectkdngrp') {
            $config->setValues(
                $this->db->getObjects(
                    'SELECT kKundengruppe, cName
                            FROM tkundengruppe
                            ORDER BY cStandard DESC'
                )
            );
        }
        if ($config->getInputType() === 'listbox') {
            $setValue = $this->db->selectAll(
                'teinstellungen',
                ['kEinstellungenSektion', 'cName'],
                [$config->getConfigSectionID(), $config->getValueName()]
            );
            $config->setSetValue($setValue);
        } elseif ($config->getInputType() === 'selectkdngrp') {
            $setValue = $this->db->selectAll(
                'teinstellungen',
                ['kEinstellungenSektion', 'cName'],
                [$config->getConfigSectionID(), $config->getValueName()]
            );
            $config->setSetValue($setValue);
        } else {
            $setValue = $this->db->select(
                'teinstellungen',
                'kEinstellungenSektion',
                $config->getConfigSectionID(),
                'cName',
                $config->getValueName()
            );
            $config->setSetValue(
                isset($setValue->cWert)
                    ? Text::htmlentities($setValue->cWert)
                    : null
            );
        }
    }

    /**
     * @param array<Item|Subsection> $configItems
     */
    private function initSubsections(array $configItems): void
    {
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
            $this->loaded        = true;
        }
    }
}
