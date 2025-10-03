<?php

declare(strict_types=1);

namespace JTL\OPC;

use Exception;
use JTL\Backend\AdminIO;
use JTL\Catalog\Category\Kategorie;
use JTL\Events\Dispatcher;
use JTL\Events\Event;
use JTL\Filter\Config;
use JTL\Filter\FilterInterface;
use JTL\Filter\Items\Category;
use JTL\Filter\Items\Characteristic;
use JTL\Filter\Items\Manufacturer;
use JTL\Filter\Items\PriceRange;
use JTL\Filter\Items\Rating;
use JTL\Filter\Items\Search;
use JTL\Filter\Items\SearchSpecial;
use JTL\Filter\ProductFilter;
use JTL\Filter\States\DummyState;
use JTL\Filter\Type;
use JTL\Helpers\Request;
use JTL\Helpers\Tax;
use JTL\L10n\GetText;
use JTL\OPC\Portlets\MissingPortlet\MissingPortlet;
use JTL\Shop;

/**
 * Class Service
 * @package JTL\OPC
 */
class Service
{
    protected string $adminName = '';

    protected string $adminLangTag = '';

    public function __construct(protected DB $db, private readonly GetText $getText)
    {
        $this->adminLangTag = Shop::getCurAdminLangTag() ?? 'de-DE';
        $this->getText->setLanguage($this->adminLangTag)->loadAdminLocale('pages/opc');
    }

    /**
     * @return string[] list of the OPC service methods to be exposed for AJAX requests
     */
    public function getIOFunctionNames(): array
    {
        return [
            'getIOFunctionNames',
            'getBlueprints',
            'getBlueprint',
            'getBlueprintInstance',
            'getBlueprintPreview',
            'saveBlueprint',
            'deleteBlueprint',
            'getPortletInstance',
            'getPortletPreviewHtml',
            'getConfigPanelHtml',
            'getFilteredProductIds',
            'getFilterOptions',
            'getFilterList',
        ];
    }

    /**
     * @return string[]
     */
    public function getEditorMessages(): array
    {
        $messages     = [];
        $messageNames = [
            'opcImportSuccessTitle',
            'opcImportSuccess',
            'opcImportUnmappedS',
            'opcImportUnmappedP',
            'btnTitleCopyArea',
            'offscreenAreasDivider',
            'yesDeleteArea',
            'Cancel',
            'opcPageLocked',
            'dbUpdateNeeded',
            'indefinitePeriodOfTime',
            'notScheduled',
            'now',
            'portletProblemFormInForm',
        ];
        foreach ([13, 14, 7] as $i => $stepcount) {
            for ($j = 0; $j < $stepcount; $j++) {
                $messageNames[] = 'tutStepTitle_' . $i . '_' . $j;
                $messageNames[] = 'tutStepText_' . $i . '_' . $j;
            }
        }
        foreach ($messageNames as $name) {
            $messages[$name] = \__($name);
        }

        return $messages;
    }

    public function registerAdminIOFunctions(AdminIO $io): void
    {
        $adminAccount = $io->getAccount()?->account() ?? false;
        if ($adminAccount === false) {
            throw new Exception('Admin account was not set on AdminIO.');
        }
        $this->adminName = $adminAccount->cLogin;
        foreach ($this->getIOFunctionNames() as $functionName) {
            $publicFunctionName = 'opc' . \ucfirst($functionName);
            $io->register($publicFunctionName, [$this, $functionName], null, 'OPC_VIEW');
        }
    }

    public function getAdminSessionToken(): ?string
    {
        return Shop::getAdminSessionToken();
    }

    /**
     * @param bool $withInactive
     * @return PortletGroup[]
     * @throws Exception
     */
    public function getPortletGroups(bool $withInactive = false): array
    {
        return $this->db->getPortletGroups($withInactive);
    }

    /**
     * @param bool $withInactive
     * @return Portlet[]
     * @throws Exception
     */
    public function getAllPortlets(bool $withInactive = false): array
    {
        return $this->db->getAllPortlets($withInactive);
    }

    /**
     * @return array<string, string>
     * @throws Exception
     */
    public function getPortletInitScriptUrls(): array
    {
        $scripts = [];
        foreach ($this->getAllPortlets() as $portlet) {
            foreach ($portlet->getEditorInitScripts() as $script) {
                $path = $portlet->getBasePath() . $script;
                $url  = $portlet->getBaseUrl() . $script;
                if (!\array_key_exists($url, $scripts) && \file_exists($path)) {
                    $scripts[$url] = $url;
                }
            }
        }

        return $scripts;
    }

    /**
     * @param bool $withInactive
     * @return Blueprint[]
     * @throws Exception
     */
    public function getBlueprints(bool $withInactive = false): array
    {
        $blueprints = [];
        foreach ($this->db->getAllBlueprintIds($withInactive) as $blueprintId) {
            $blueprints[] = $this->getBlueprint($blueprintId);
        }

        return $blueprints;
    }

    public function getBlueprint(int $id): Blueprint
    {
        $blueprint = (new Blueprint())->setId($id);
        $this->db->loadBlueprint($blueprint);

        return $blueprint;
    }

    public function getBlueprintInstance(int $id): PortletInstance
    {
        $instance = $this->getBlueprint($id)->getInstance();

        Dispatcher::getInstance()->fire(Event::OPC_SERVICE_GETBLUEPRINTINSTANCE, [
            'id'       => $id,
            'instance' => &$instance
        ]);

        return $instance;
    }

    public function getBlueprintPreview(int $id): string
    {
        return $this->getBlueprintInstance($id)->getPreviewHtml();
    }

    /**
     * @param string|null       $name
     * @param array<mixed>|null $data
     * @throws Exception
     */
    public function saveBlueprint(?string $name, ?array $data): void
    {
        if (!isset($name)) {
            throw new Exception('The OPC blueprint data to be saved is incomplete or invalid.');
        }
        if (!isset($data)) {
            throw new Exception('The OPC blueprint data is incomplete or invalid.');
        }
        if (!isset($data['class'])) {
            throw new Exception('The OPC blueprint data must contain a class name.');
        }
        $this->db->saveBlueprint((new Blueprint())->deserialize(['name' => $name, 'content' => $data]));
    }

    public function deleteBlueprint(int $id): void
    {
        $this->db->deleteBlueprint((new Blueprint())->setId($id));
    }

    /**
     * @param class-string<Portlet> $class
     * @param array<mixed>|null     $data
     * @throws Exception
     */
    public function createPortletInstance(string $class, ?array $data = null): PortletInstance
    {
        $portlet = $this->db->getPortlet($class);

        if ($portlet instanceof MissingPortlet) {
            $instance = new MissingPortletInstance($portlet, $portlet->getMissingClass(), $data);
        } else {
            $instance = new PortletInstance($portlet, $data);
        }

        return $instance;
    }

    /**
     * @param array{class: class-string<Portlet>, missingClass: class-string<Portlet>|null,
     *     properties: array<mixed>} $data
     * @return PortletInstance
     * @throws Exception
     */
    public function getPortletInstance(array $data): PortletInstance
    {
        if ($data['class'] === 'MissingPortlet') {
            return $this->createPortletInstance($data['missingClass'], $data);
        }

        return $this->createPortletInstance($data['class'], $data);
    }

    /**
     * @param array{class: class-string<Portlet>, missingClass: class-string<Portlet>|null,
     *      properties: array<mixed>} $data
     * @return string
     * @throws Exception
     */
    public function getPortletPreviewHtml(array $data): string
    {
        return $this->getPortletInstance($data)->getPreviewHtml();
    }

    /**
     * @param class-string<Portlet>      $portletClass
     * @param class-string<Portlet>|null $missingClass
     * @param array<mixed>               $props
     * @return string
     * @throws Exception
     */
    public function getConfigPanelHtml(string $portletClass, ?string $missingClass, array $props): string
    {
        return $this->getPortletInstance([
            'class'        => $portletClass,
            'missingClass' => $missingClass,
            'properties'   => $props,
        ])->getConfigPanelHtml();
    }

    public function isEditMode(): bool
    {
        return Request::verifyGPDataString('opcEditMode') === 'yes';
    }

    public function isOPCInstalled(): bool
    {
        return $this->db->isOPCInstalled();
    }

    public function shopHasUpdates(): bool
    {
        return $this->db->shopHasUpdates();
    }

    public function isPreviewMode(): bool
    {
        return Request::verifyGPDataString('opcPreviewMode') === 'yes';
    }

    public function getEditedPageKey(): int
    {
        return Request::verifyGPCDataInt('opcEditedPageKey');
    }

    /**
     * @param array<array{class: class-string<FilterInterface>, value: string}> $enabledFilters
     * @throws \SmartyException
     */
    public function getFilterList(string $propname, array $enabledFilters = []): string
    {
        return Shop::Smarty()->assign('propname', $propname)
            ->assign('filters', $this->getFilterOptions($enabledFilters))
            ->fetch(\PFAD_ROOT . \PFAD_ADMIN . 'opc/tpl/config/filter-list.tpl');
    }

    /**
     * @param class-string<FilterInterface> $filterClass
     * @param array<mixed>                  $params
     * @param mixed                         $value
     * @param ProductFilter                 $pf
     */
    public function getFilterClassParamMapping(
        string $filterClass,
        array &$params,
        mixed $value,
        ProductFilter $pf
    ): void {
        switch ($filterClass) {
            case Category::class:
                $params['kKategorie'] = $value;
                break;
            case Characteristic::class:
                $params['MerkmalFilter_arr'][] = $value;
                break;
            case PriceRange::class:
                $params['cPreisspannenFilter'] = $value;
                break;
            case Manufacturer::class:
                $params['manufacturerFilters'][] = $value;
                break;
            case Rating::class:
                $params['nBewertungSterneFilter'] = $value;
                break;
            case SearchSpecial::class:
                $params['kSuchspecialFilter'] = $value;
                break;
            case Search::class:
                $params['kSuchFilter']      = $value;
                $params['SuchFilter'][]     = $value;
                $params['SuchFilter_arr'][] = $value;
                break;
            default:
                /** @var FilterInterface $instance */
                $instance                       = new $filterClass($pf);
                $_GET[$instance->getUrlParam()] = $value;
                break;
        }
    }

    public function overrideConfig(ProductFilter $pf): void
    {
        $config = $pf->getFilterConfig()->getConfig();
        if (isset($config['navigationsfilter'])) {
            $config['navigationsfilter']['allgemein_kategoriefilter_benutzen']    = 'Y';
            $config['navigationsfilter']['allgemein_availabilityfilter_benutzen'] = 'Y';
            $config['navigationsfilter']['allgemein_herstellerfilter_benutzen']   = 'Y';
            $config['navigationsfilter']['bewertungsfilter_benutzen']             = 'Y';
            $config['navigationsfilter']['preisspannenfilter_benutzen']           = 'Y';
            $config['navigationsfilter']['merkmalfilter_verwenden']               = 'Y';
            $config['navigationsfilter']['manufacturer_filter_type']              = 'O';
            $config['navigationsfilter']['allgemein_suchspecialfilter_benutzen']  = 'Y';
            $config['navigationsfilter']['kategoriefilter_anzeigen_als']          = 'KA';
            $pf->getFilterConfig()->setConfig($config);
        }
    }

    /**
     * @param array<array{class: class-string<FilterInterface>, value: string}> $enabledFilters
     * @return array<array{name: string, class: class-string<FilterInterface>,
     *      options: array{name: string, class: class-string<FilterInterface>, count: int, value: mixed}}>
     */
    public function getFilterOptions(array $enabledFilters = []): array
    {
        Shop::set('checkCategoryVisibility', false);
        Shop::set('skipProductVisibilityCheck', true);
        Tax::setTaxRates();
        $pf         = new ProductFilter(
            Config::getDefault(),
            Shop::Container()->getDB(),
            Shop::Container()->getCache()
        );
        $results    = [];
        $enabledMap = [];
        $params     = [
            'MerkmalFilter_arr'   => [],
            'SuchFilter_arr'      => [],
            'SuchFilter'          => [],
            'manufacturerFilters' => []
        ];
        foreach ($enabledFilters as $enabledFilter) {
            $this->getFilterClassParamMapping($enabledFilter['class'], $params, $enabledFilter['value'], $pf);
            $enabledMap[$enabledFilter['class'] . ':' . $enabledFilter['value']] = true;
        }
        $this->overrideConfig($pf);
        $pf->setBaseState((new DummyState($pf))->init(0));
        $pf->initStates($params, false);
        foreach ($pf->getAvailableFilters() as $availableFilter) {
            $class = $availableFilter->getClassName();
            if ($class !== Manufacturer::class) {
                $availableFilter->setType(Type::AND);
            }
            $name    = $availableFilter->getFrontendName();
            $options = [];
            if ($class === Characteristic::class) {
                foreach ($availableFilter->getOptions() as $option) {
                    foreach ($option->getOptions() as $suboption) {
                        $value    = $suboption->kMerkmalWert;
                        $mapindex = $class . ':' . $value;
                        if (!isset($enabledMap[$mapindex])) {
                            $options[] = [
                                'name'    => $suboption->getName(),
                                'tooltip' => $suboption->getName(),
                                'value'   => $value,
                                'count'   => $suboption->getCount(),
                                'class'   => $class,
                            ];
                        }
                    }
                }
            } else {
                foreach ($availableFilter->getOptions() as $option) {
                    /** @var string $value */
                    $value    = $option->getValue();
                    $mapindex = $class . ':' . $value;
                    if (isset($enabledMap[$mapindex])) {
                        continue;
                    }
                    $optionName = $option->getName();
                    if (\is_int($value) && $option->getClassName() === Category::class) {
                        $category = new Kategorie($value);
                        if ($category->getParentID() > 0) {
                            $categoryPaths = $category->getCategoryPath();
                            $lastOption    = \array_pop($categoryPaths);
                            $optionName    = $lastOption . ' (';
                            foreach ($categoryPaths as $categoryPath) {
                                $optionName .= $categoryPath . ' -> ';
                            }
                            $optionName = \substr($optionName, 0, -4) . ')';
                        }
                    }
                    $options[] = [
                        'name'    => $option->getName(),
                        'tooltip' => $optionName,
                        'value'   => $value,
                        'count'   => $option->getCount(),
                        'class'   => $class,
                    ];
                }
            }

            if (\count($options) > 0) {
                $results[] = [
                    'name'    => $name,
                    'class'   => $class,
                    'options' => $options,
                ];
            }
        }

        return $results;
    }

    public function getAdminLangTag(): string
    {
        return $this->adminLangTag;
    }

    public function getInputTypeTplPath(string $type): string
    {
        $path = \PFAD_ROOT . \PFAD_ADMIN . 'opc/tpl/config/config.' . $type . '.tpl';

        if (\file_exists($path)) {
            return $path;
        }

        return $this->db->getInputTplPathFromPlugin($type);
    }

    /**
     * @return \stdClass[]
     */
    public function getCustomerGroups(): array
    {
        return $this->db->getCustomerGroups();
    }

    public function getCustomerGroupName(int $id): string
    {
        foreach ($this->getCustomerGroups() as $group) {
            if ($group->id === $id) {
                return $group->name;
            }
        }

        return '';
    }
}
