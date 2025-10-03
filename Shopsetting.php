<?php

declare(strict_types=1);

namespace JTL;

use ArrayAccess;
use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Helpers\Text;
use stdClass;

use function Functional\reindex;

/**
 * Class Shopsetting
 * @package JTL
 * @implements ArrayAccess<string, array<string, mixed>>
 */
final class Shopsetting implements ArrayAccess
{
    private static ?self $instance = null;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $container = [];

    /**
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $allSettings = null;

    /**
     * @var array<int, string>
     */
    private static array $mapping = [
        \CONF_GLOBAL              => 'global',
        \CONF_STARTSEITE          => 'startseite',
        \CONF_EMAILS              => 'emails',
        \CONF_ARTIKELUEBERSICHT   => 'artikeluebersicht',
        \CONF_ARTIKELDETAILS      => 'artikeldetails',
        \CONF_KUNDEN              => 'kunden',
        \CONF_LOGO                => 'logo',
        \CONF_KAUFABWICKLUNG      => 'kaufabwicklung',
        \CONF_BOXEN               => 'boxen',
        \CONF_BILDER              => 'bilder',
        \CONF_SONSTIGES           => 'sonstiges',
        \CONF_ZAHLUNGSARTEN       => 'zahlungsarten',
        \CONF_PLUGINZAHLUNGSARTEN => 'pluginzahlungsarten',
        \CONF_KONTAKTFORMULAR     => 'kontakt',
        \CONF_SHOPINFO            => 'shopinfo',
        \CONF_RSS                 => 'rss',
        \CONF_VERGLEICHSLISTE     => 'vergleichsliste',
        \CONF_PREISVERLAUF        => 'preisverlauf',
        \CONF_BEWERTUNG           => 'bewertung',
        \CONF_NEWSLETTER          => 'newsletter',
        \CONF_KUNDENFELD          => 'kundenfeld',
        \CONF_NAVIGATIONSFILTER   => 'navigationsfilter',
        \CONF_EMAILBLACKLIST      => 'emailblacklist',
        \CONF_METAANGABEN         => 'metaangaben',
        \CONF_NEWS                => 'news',
        \CONF_SITEMAP             => 'sitemap',
        \CONF_SUCHSPECIAL         => 'suchspecials',
        \CONF_TEMPLATE            => 'template',
        \CONF_AUSWAHLASSISTENT    => 'auswahlassistent',
        \CONF_CRON                => 'cron',
        \CONF_FS                  => 'fs',
        \CONF_CACHING             => 'caching',
        \CONF_CONSENTMANAGER      => 'consentmanager',
        \CONF_BRANDING            => 'branding'
    ];

    private function __construct(private readonly DbInterface $db, private readonly JTLCacheInterface $cache)
    {
        self::$instance = $this;
        $this->preLoad();
    }

    private function __clone()
    {
        // this class must not be cloned
    }

    public static function getInstance(?DbInterface $db = null, ?JTLCacheInterface $cache = null): self
    {
        return self::$instance ?? new self($db ?? Shop::Container()->getDB(), $cache ?? Shop::Container()->getCache());
    }

    /**
     * for rare cases when options are modified and directly re-assigned to smarty
     * do not call this function otherwise.
     */
    public function reset(): self
    {
        $this->container = [];

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    /**
     * @inheritdoc
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->container[$offset]);
    }

    /**
     * @inheritdoc
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->container[$offset]);
    }

    /**
     * @param array<string, mixed> $value
     */
    public function overrideSection(int $sectionID, array $value): void
    {
        /** @var string|null $mapping */
        $mapping = self::mapSettingName($sectionID);
        if ($mapping !== null) {
            $this->container[$mapping]   = $value;
            $this->allSettings[$mapping] = $value;
        }
    }

    /**
     * @inheritdoc
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (isset($this->container[$offset])) {
            return $this->container[$offset];
        }
        /** @var int|null|false $section */
        $section = self::mapSettingName(null, $offset);
        $cacheID = 'setting_' . $section;
        if ($section === false || $section === null) {
            return null;
        }
        if ($section === \CONF_TEMPLATE) {
            /** @var stdClass[] $settings */
            $settings = $this->cache->get(
                $cacheID,
                function ($cache, $id, &$content, &$tags): bool {
                    $content = $this->getTemplateConfig();
                    $tags    = [\CACHING_GROUP_TEMPLATE, \CACHING_GROUP_OPTION];

                    return true;
                }
            );
            if (\is_array($settings)) {
                foreach ($settings as $templateSection => $templateSetting) {
                    $this->container[$offset][$templateSection] = $templateSetting;
                }
            }
        } elseif ($section === \CONF_BRANDING) {
            return $this->cache->get(
                $cacheID,
                function ($cache, $id, &$content, &$tags): bool {
                    $content = $this->getBrandingConfig();
                    $tags    = [\CACHING_GROUP_OPTION];

                    return true;
                }
            );
        } else {
            /** @var stdClass[] $settings */
            $settings = $this->cache->get(
                $cacheID,
                function ($cache, $id, &$content, &$tags) use ($section): bool {
                    $content = $this->getSectionData($section);
                    $tags    = [\CACHING_GROUP_OPTION];

                    return true;
                }
            );
            if (\is_countable($settings) && \count($settings) > 0) {
                $this->addContainerData($offset, $settings);
            }
        }

        return $this->container[$offset] ?? null;
    }

    /**
     * @param stdClass[] $settings
     */
    private function addContainerData(string $offset, array $settings): void
    {
        $this->container[$offset] = [];
        foreach ($settings as $setting) {
            if ($setting->type === 'listbox') {
                if (!isset($this->container[$offset][$setting->cName])) {
                    $this->container[$offset][$setting->cName] = [];
                }
                $this->container[$offset][$setting->cName][] = $setting->cWert;
            } elseif ($setting->type === 'number') {
                $this->container[$offset][$setting->cName] = (int)$setting->cWert;
            } else {
                $this->container[$offset][$setting->cName] = $setting->cWert;
            }
        }
    }

    /**
     * @return stdClass[]
     */
    private function getSectionData(int $section): array
    {
        if ($section === \CONF_PLUGINZAHLUNGSARTEN) {
            return $this->db->getObjects(
                "SELECT cName, cWert, '' AS type
                     FROM tplugineinstellungen
                     WHERE cName LIKE '%_min%' 
                        OR cName LIKE '%_max'"
            );
        }

        return $this->db->getObjects(
            'SELECT teinstellungen.cName, teinstellungen.cWert, teinstellungenconf.cInputTyp AS type
                FROM teinstellungen
                LEFT JOIN teinstellungenconf
                    ON teinstellungenconf.cWertName = teinstellungen.cName
                    AND teinstellungenconf.kEinstellungenSektion = teinstellungen.kEinstellungenSektion
                WHERE teinstellungen.kEinstellungenSektion = :section',
            ['section' => $section]
        );
    }

    /**
     * @param int|int[] $sections
     * @return array<string, array<string, mixed>|null>
     */
    public function getSettings(int|array $sections): array
    {
        $ret = [];
        foreach ((array)$sections as $section) {
            $mapping = self::mapSettingName($section);
            if ($mapping !== null) {
                $ret[$mapping] = $this[$mapping];
            }
        }

        return $ret;
    }

    public function getValue(int $sectionID, string $option): mixed
    {
        $section = $this->getSection($sectionID);

        return $section[$option] ?? null;
    }

    public function getString(string $key, int $sectionID = \CONF_GLOBAL): string
    {
        return (string)($this->getValue($sectionID, $key) ?? '');
    }

    public function getBool(string $key, int $sectionID = \CONF_GLOBAL): bool
    {
        $value = $this->getValue($sectionID, $key);
        if (\is_bool($value)) {
            return $value;
        }

        return \in_array($value, ['1', 1, 'true', 'on', 'yes', 'Y', 'y'], true);
    }

    public function getInt(string $key, int $sectionID = \CONF_GLOBAL): int
    {
        $value = $this->getValue($sectionID, $key);
        if (\is_numeric($value)) {
            return (int)$value;
        }

        return 0;
    }

    /**
     * @param int $sectionID
     * @return array<string, mixed>|null
     */
    public function getSection(int $sectionID): ?array
    {
        $settings = $this->getSettings([$sectionID]);

        return $settings[self::mapSettingName($sectionID)] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSectionByName(string $name): ?array
    {
        $section = $this[$name] ?? null;
        if ($section !== null) {
            return $section;
        }
        foreach (self::$mapping as $id => $sectionName) {
            if ($sectionName === $name) {
                return $this->getSection($id);
            }
        }

        return null;
    }

    public static function mapSettingName(?int $section = null, ?string $name = null): mixed
    {
        if ($section === null && $name === null) {
            return false;
        }
        if ($section !== null && isset(self::$mapping[$section])) {
            return self::$mapping[$section];
        }
        if ($name !== null && ($key = \array_search($name, self::$mapping, true)) !== false) {
            return $key;
        }

        return null;
    }

    /**
     * @return array<string, stdClass[]>
     */
    private function getBrandingConfig(): array
    {
        $data = $this->db->getObjects(
            'SELECT tbrandingeinstellung.*, tbranding.kBranding AS id, tbranding.cBildKategorie AS type, 
            tbrandingeinstellung.cPosition AS position, tbrandingeinstellung.cBrandingBild AS path,
            tbrandingeinstellung.dTransparenz AS transparency, tbrandingeinstellung.dGroesse AS size
                FROM tbrandingeinstellung
                INNER JOIN tbranding 
                    ON tbrandingeinstellung.kBranding = tbranding.kBranding
                WHERE tbrandingeinstellung.nAktiv = 1'
        );
        foreach ($data as $item) {
            $item->size         = (int)$item->size;
            $item->transparency = (int)$item->transparency;
            $item->path         = \PFAD_ROOT . \PFAD_BRANDINGBILDER . $item->path;
            $item->imagesizes   = Text::parseSSK($item->imagesizes ?? '');
            unset(
                $item->kBrandingEinstellung,
                $item->kBranding,
                $item->nAktiv,
                $item->cPosition,
                $item->cBrandingBild,
                $item->dTransparenz,
                $item->dRandabstand,
                $item->dGroesse,
            );
        }

        return reindex($data, fn(stdClass $e) => $e->type);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getTemplateConfig(): array
    {
        $data     = $this->db->getObjects(
            "SELECT cSektion AS sec, cWert AS val, cName AS name 
                FROM ttemplateeinstellungen 
                WHERE cTemplate = (SELECT cTemplate FROM ttemplate WHERE eTyp = 'standard')"
        );
        $settings = [];
        /** @var stdClass&object{sec: string, val: string, name: string} $setting */
        foreach ($data as $setting) {
            if (!isset($settings[$setting->sec])) {
                $settings[$setting->sec] = [];
            }
            $settings[$setting->sec][$setting->name] = $setting->val;
        }

        return $settings;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getAll(): array
    {
        if ($this->allSettings !== null) {
            return $this->allSettings;
        }
        $result   = [];
        $settings = $this->db->getArrays(
            'SELECT teinstellungen.kEinstellungenSektion, teinstellungen.cName, teinstellungen.cWert,
                teinstellungenconf.cInputTyp AS type
                FROM teinstellungen
                LEFT JOIN teinstellungenconf
                    ON teinstellungenconf.cWertName = teinstellungen.cName
                    AND teinstellungenconf.kEinstellungenSektion = teinstellungen.kEinstellungenSektion
                ORDER BY kEinstellungenSektion'
        );
        foreach (self::$mapping as $mappingID => $sectionName) {
            foreach ($settings as $setting) {
                $sectionID = (int)$setting['kEinstellungenSektion'];
                if ($sectionID !== $mappingID) {
                    continue;
                }
                if (!isset($result[$sectionName])) {
                    $result[$sectionName] = [];
                }
                if ($setting['type'] === 'listbox') {
                    if (!isset($result[$sectionName][$setting['cName']])) {
                        $result[$sectionName][$setting['cName']] = [];
                    }
                    $result[$sectionName][$setting['cName']][] = $setting['cWert'];
                } elseif ($setting['type'] === 'number') {
                    $result[$sectionName][$setting['cName']] = (int)$setting['cWert'];
                } elseif ($setting['type'] !== '') {
                    $result[$sectionName][$setting['cName']] = $setting['cWert'];
                }
            }
        }
        $result['template'] = $this->getTemplateConfig();
        $result['branding'] = $this->getBrandingConfig();
        $this->allSettings  = $result;

        return $result;
    }

    /**
     * preload the _container variable with one single sql statement or one single cache call
     *
     * @return array<string, array<string, mixed>>
     */
    public function preLoad(): array
    {
        $cacheID = 'settings_all_preload';
        /** @var false|array<string, array<string, mixed>> $result */
        $result = $this->cache->get($cacheID);
        if ($result === false) {
            $result = $this->getAll();
            $this->cache->set($cacheID, $result, [\CACHING_GROUP_TEMPLATE, \CACHING_GROUP_OPTION, \CACHING_GROUP_CORE]);
        }
        $this->container   = $result;
        $this->allSettings = $result;

        return $result;
    }
}
