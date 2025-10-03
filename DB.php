<?php

declare(strict_types=1);

namespace JTL\OPC;

use Exception;
use InvalidArgumentException;
use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\L10n\GetText;
use JTL\OPC\Portlets\MissingPortlet\MissingPortlet;
use JTL\Plugin\PluginLoader;
use JTL\Update\Updater;
use stdClass;

use function Functional\map;

/**
 * Class DB
 * @package JTL\OPC
 */
class DB
{
    /**
     * @var array<string, stdClass|null>
     */
    protected array $mapping;

    protected PluginLoader $pluginLoader;

    /**
     * @var stdClass[]|null
     */
    protected ?array $allCustomerGroups = null;

    public function __construct(
        protected DbInterface $shopDB,
        protected JTLCacheInterface $cache,
        protected GetText $getText
    ) {
        /** @var array<string, stdClass|null>|false $mapping */
        $mapping            = $this->cache->get('jtl_opc_mapping');
        $this->mapping      = $mapping ?: [];
        $this->pluginLoader = new PluginLoader($this->shopDB, $this->cache);
    }

    /**
     * @return int[]
     */
    public function getAllBlueprintIds(bool $withInactive = false): array
    {
        return map(
            $this->shopDB->selectAll(
                'topcblueprint',
                $withInactive ? [] : 'bActive',
                $withInactive ? [] : 1,
                'kBlueprint'
            ),
            static fn(stdClass $e): int => (int)$e->kBlueprint
        );
    }

    public function blueprintExists(Blueprint $blueprint): bool
    {
        return \is_object($this->shopDB->select('topcblueprint', 'kBlueprint', $blueprint->getId()));
    }

    public function deleteBlueprint(Blueprint $blueprint): self
    {
        $this->shopDB->delete('topcblueprint', 'kBlueprint', $blueprint->getId());

        return $this;
    }

    /**
     * @throws Exception
     */
    public function loadBlueprint(Blueprint $blueprint): void
    {
        $blueprintDB = $this->shopDB->select('topcblueprint', 'kBlueprint', $blueprint->getId());
        if ($blueprintDB === null) {
            throw new Exception('The OPC blueprint with the id \'' . $blueprint->getId() . '\' could not be found.');
        }
        $content = \json_decode($blueprintDB->cJson, true, 512, \JSON_THROW_ON_ERROR);

        $blueprint->setId((int)$blueprintDB->kBlueprint)
            ->deserialize(['name' => $blueprintDB->cName, 'content' => $content]);

        if ((int)$blueprintDB->kPlugin > 0) {
            $this->pluginLoader->init((int)$blueprintDB->kPlugin);
            $blueprint->setName(\__($blueprint->getName()));
        }
    }

    /**
     * @throws Exception
     */
    public function saveBlueprint(Blueprint $blueprint): self
    {
        if ($blueprint->getName() === '') {
            throw new Exception('The OPC blueprint data to be saved is incomplete or invalid.');
        }

        $blueprintDB = (object)[
            'kBlueprint' => $blueprint->getId(),
            'cName'      => $blueprint->getName(),
            'cJson'      => \json_encode($blueprint->getInstance(), \JSON_THROW_ON_ERROR),
        ];

        if ($this->blueprintExists($blueprint)) {
            $res = $this->shopDB->update('topcblueprint', 'kBlueprint', $blueprint->getId(), $blueprintDB);
            if ($res === -1) {
                throw new Exception('The OPC blueprint could not be updated in the DB.');
            }
        } else {
            $key = $this->shopDB->insert('topcblueprint', $blueprintDB);
            if ($key === 0) {
                throw new Exception('The OPC blueprint could not be inserted into the DB.');
            }

            $blueprint->setId($key);
        }

        return $this;
    }

    /**
     * @return PortletGroup[]
     * @throws Exception
     */
    public function getPortletGroups(bool $withInactive = false): array
    {
        $groupNames = $this->shopDB->getObjects('SELECT DISTINCT(cGroup) FROM topcportlet ORDER BY cGroup ASC');
        $groups     = [];
        foreach ($groupNames as $groupName) {
            $groups[] = $this->getPortletGroup($groupName->cGroup, $withInactive);
        }

        return $groups;
    }

    /**
     * @throws Exception
     */
    public function getPortletGroup(string $groupName, bool $withInactive = false): PortletGroup
    {
        $portletsDB   = $this->shopDB->selectAll(
            'topcportlet',
            $withInactive ? 'cGroup' : ['cGroup', 'bActive'],
            $withInactive ? $groupName : [$groupName, 1],
            'cClass',
            'cTitle'
        );
        $portletGroup = new PortletGroup($groupName);
        foreach ($portletsDB as $portletDB) {
            $portletGroup->addPortlet($this->getPortlet($portletDB->cClass));
        }

        return $portletGroup;
    }

    /**
     * @return Portlet[]
     * @throws Exception
     */
    public function getAllPortlets(bool $withInactive = false): array
    {
        $portlets   = [];
        $portletsDB = $this->shopDB->selectAll(
            'topcportlet',
            $withInactive ? [] : 'bActive',
            $withInactive ? [] : 1,
            'cClass',
            'cTitle'
        );
        foreach ($portletsDB as $portletDB) {
            $portlets[] = $this->getPortlet($portletDB->cClass);
        }

        return $portlets;
    }

    public function getPortletCount(): int
    {
        return $this->shopDB->getSingleInt('SELECT COUNT(kPortlet) AS cnt FROM topcportlet', 'cnt');
    }

    /**
     * @param class-string<Portlet> $class
     */
    protected function getPortletByClassName(string $class): ?stdClass
    {
        if (\array_key_exists($class, $this->mapping)) {
            return $this->mapping[$class];
        }
        $mapping = $this->shopDB->select('topcportlet', 'cClass', $class);
        if ($mapping !== null) {
            $mapping->kPortlet = (int)$mapping->kPortlet;
            $mapping->kPlugin  = (int)$mapping->kPlugin;
            $mapping->bActive  = (int)$mapping->bActive;
        }
        $this->mapping[$class] = $mapping;
        $this->cache->set('jtl_opc_mapping', $this->mapping, [\CACHING_GROUP_OPC]);

        return $mapping;
    }

    /**
     * @param class-string<Portlet> $class
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function getPortlet(string $class): Portlet
    {
        if ($class === '') {
            throw new InvalidArgumentException('The OPC portlet class name "' . $class . '" is invalid.');
        }
        $plugin     = null;
        $pluginID   = 0;
        $data       = $this->getPortletByClassName($class);
        $installed  = $data !== null;
        $active     = $installed && $data->bActive === 1;
        $fromPlugin = $installed && $data->kPlugin > 0;
        /** @var class-string $fullClass */
        $fullClass = '\JTL\OPC\Portlets\\' . $class . '\\' . $class;
        if ($data !== null && $fromPlugin) {
            $pluginID = $data->kPlugin;
            if (\SAFE_MODE === true) {
                $active = 0;
            } else {
                $plugin    = $this->pluginLoader->init($pluginID);
                $fullClass = '\Plugin\\' . $plugin->getPluginID() . '\Portlets\\' . $class . '\\' . $class;
            }
        }

        if ($installed && $active) {
            /** @var Portlet $portlet */
            $portlet = \class_exists($fullClass)
                ? new $fullClass($class, $data->kPortlet, $this->shopDB, $this->cache, $this->getText, $plugin)
                : new Portlet($class, $data->kPortlet, $this->shopDB, $this->cache, $this->getText, $plugin);

            return $portlet->setTitle($data->cTitle)
                ->setGroup($data->cGroup)
                ->setActive($data->bActive === 1);
        }
        $portlet = new MissingPortlet('MissingPortlet', 0, $this->shopDB, $this->cache, $this->getText);
        $portlet->setMissingClass($class)
            ->setTitle(\__('missingPortlet') . ' "' . $class . '"')
            ->setGroup('hidden')
            ->setActive(false);

        if ($fromPlugin) {
            $portlet->setInactivePlugin($plugin)
                ->setTitle(\__('missingPortlet') . ' "' . $class . '" (' . $pluginID . ')');
        }

        return $portlet;
    }

    public function isOPCInstalled(): bool
    {
        /** @var bool $installed */
        $installed = $this->cache->get('opc_installed');
        if ($installed === false) {
            $installed = $this->shopDB->select('tmigration', 'kMigration', 20180507101900) !== null;
            $this->cache->set('opc_installed', $installed);
        }

        return $installed;
    }

    /**
     * @throws Exception
     */
    public function shopHasUpdates(): bool
    {
        return (new Updater($this->shopDB))->hasPendingUpdates();
    }

    /**
     * @throws Exception
     */
    public function getInputTplPathFromPlugin(string $name): string
    {
        $parts = \explode('.', $name);

        if (\count($parts) !== 2) {
            throw new Exception(
                'The Portlet Input type name \'' . $name . '\' is invalid.'
            );
        }

        $inputDB = $this->shopDB->select('portlet_input_type', 'name', $name);

        if ($inputDB === null) {
            throw new Exception(
                'The Portlet Input type with the name \'' . $name . '\' could not be found.'
            );
        }

        $plugin = $this->pluginLoader->init((int)$inputDB->plugin_id);

        return $plugin->getPaths()->getBasePath() . 'portlet_input_types/' . $parts[1] . '.tpl';
    }

    /**
     * @return stdClass[]
     */
    public function getCustomerGroups(): array
    {
        if ($this->allCustomerGroups !== null) {
            return $this->allCustomerGroups;
        }
        $this->allCustomerGroups = \array_map(static function (stdClass $item): stdClass {
            $item->id = (int)$item->id;

            return $item;
        }, $this->shopDB->getObjects('SELECT kKundengruppe AS id, cName AS name FROM tkundengruppe'));

        return $this->allCustomerGroups;
    }
}
