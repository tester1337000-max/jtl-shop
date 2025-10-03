<?php

declare(strict_types=1);

namespace JTL\Smarty;

use JSMin\JSMin;
use JSMin\UnterminatedStringException;
use JTL\Events\Dispatcher;
use JTL\Helpers\GeneralObject;
use JTL\Language\LanguageHelper;
use JTL\phpQuery\phpQuery;
use JTL\Plugin\Helper;
use JTL\Profiler;
use JTL\Shop;
use JTL\Shopsetting;
use JTL\Template\BootChecker;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Smarty\Smarty;
use Smarty\Template;

/**
 * Class JTLSmarty
 * @package \JTL\Smarty
 */
class JTLSmarty extends Smarty
{
    /**
     * @var array<string, string[]>
     */
    public array $config;

    /**
     * @var JTLSmarty[]
     */
    private static array $instance = [];

    public static bool $isChildTemplate = false;

    protected string $templateDir;

    /**
     * @var array<string, string>
     */
    private array $mapping = [];

    protected \Smarty $smarty4;

    protected string $rootTemplateName;

    /**
     * @param bool                         $fast - set to true when init from backend to avoid setting session data
     * @param string                       $context
     * @param array<string, string[]>|null $config
     * @param bool                         $workaround - indicates an early call for JTLSmarty::getInstance()
     * before new() was called
     */
    public function __construct(
        bool $fast = false,
        public string $context = ContextType::FRONTEND,
        ?array $config = null,
        bool $workaround = false
    ) {
        parent::__construct();

        if (\SMARTY_LEGACY_MODE) {
            $this->smarty4 = new \Smarty();
        }

        self::$_CHARSET = \JTL_CHARSET;
        $this->setErrorReporting(\SMARTY_LOG_LEVEL)
            ->setForceCompile(\SMARTY_FORCE_COMPILE)
            ->setDebugging(\SMARTY_DEBUG_CONSOLE)
            ->setUseSubDirs(\SMARTY_USE_SUB_DIRS);
        $this->config = $config ?? Shopsetting::getInstance()->getAll();
        $parent       = $this->initTemplate();
        if ($fast === false) {
            $this->registerPlugins();
            $this->init($parent);
        }
        if ($workaround === false) {
            // do not register instance when called from getInstance() to avoid skipping hooks
            self::$instance[$context] = $this;
            if ($fast === false && $context !== ContextType::BACKEND) {
                \executeHook(\HOOK_SMARTY_INC, ['smarty' => $this]);
            }
        }
    }

    public static function getInstance(bool $fast = false, string $context = ContextType::FRONTEND): self
    {
        $instance   = self::$instance[$context] ?? null;
        $workaround = $context === ContextType::FRONTEND && $instance === null;
        if ($workaround === true) {
            foreach (\debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS) as $item) {
                if (isset($item['file']) && \str_contains($item['file'], \PLUGIN_DIR)) {
                    Shop::Container()->getLogService()->info(
                        'Smarty invoked too early at {file} - please contact plugin author.',
                        ['file' => $item['file']]
                    );
                    break;
                }
            }
        }

        return $instance ?? new self($fast, $context, null, $workaround);
    }

    public static function hasInstance(string $context): bool
    {
        return (self::$instance[$context] ?? null) !== null;
    }

    protected function initTemplate(): ?string
    {
        $model = Shop::Container()->getTemplateService()->getActiveTemplate();
        if ($model->getTemplate() === null) {
            throw new RuntimeException('Cannot load template ' . ($model->getName() ?? ''));
        }
        $paths      = $model->getPaths();
        $tplDir     = $model->getDir();
        $parent     = $model->getParent();
        $compileDir = $paths->getCompileDir();
        if (!\is_dir($compileDir) && !\mkdir($compileDir) && !\is_dir($compileDir)) {
            throw new RuntimeException(\sprintf('Directory "%s" could not be created', $compileDir));
        }
        $this->setTemplateDir([]);
        $this->setCompileDir($compileDir)
            ->setCacheDir($paths->getCacheDir())
            ->assign('tplDir', $paths->getBaseDir())
            ->assign('parentTemplateDir');
        $parentDir = $paths->getParentDir();
        if ($parent !== null && $parentDir !== null) {
            self::$isChildTemplate = true;
            $this->assign('tplDir', $parentDir)
                ->assign('parent_template_path', $parentDir)
                ->assign('parentTemplateDir', $paths->getParentRelDir())
                ->addTemplateDir($parentDir, $parent);
        }
        $this->addTemplateDir($paths->getBaseDir(), $this->context);
        foreach (Helper::getTemplatePaths() as $moduleId => $path) {
            $templateKey = 'plugin_' . $moduleId;
            $this->addTemplateDir($path, $templateKey);
        }
        if (($bootstrapper = BootChecker::bootstrap($tplDir) ?? BootChecker::bootstrap($parent)) !== null) {
            $bootstrapper->setSmarty($this);
            $bootstrapper->setTemplate($model);
            $bootstrapper->boot();
        }
        $this->templateDir = $tplDir;

        return $parent;
    }

    protected function registerPlugins(): void
    {
        $pluginCollection = new PluginCollection($this, LanguageHelper::getInstance());
        $pluginCollection->registerPlugins();
        $pluginCollection->registerPhpFunctions();
        $pluginCollection->registerShopClasses();
    }

    /**
     * @param string|null $parent
     */
    protected function init(?string $parent = null): void
    {
        if (\SMARTY_LEGACY_MODE) {
            $this->smarty4->template_class = \SHOW_TEMPLATE_HINTS > 0
                ? JTLSmartyTemplateHints::class
                : JTLSmartyTemplateClass::class;
        }
        $this->setCacheLifetime(86400);
        $this->setCachingParams($this->config);
        /** @var string $tplDir */
        $tplDir = $this->getTemplateDir($this->context);
        global $smarty;
        $smarty = $this;
        if (\file_exists($tplDir . 'php/functions_custom.php')) {
            require_once $tplDir . 'php/functions_custom.php';
        } elseif (\file_exists($tplDir . 'php/functions.php')) {
            require_once $tplDir . 'php/functions.php';
        } elseif ($parent !== null && \file_exists(\PFAD_ROOT . \PFAD_TEMPLATES . $parent . '/php/functions.php')) {
            require_once \PFAD_ROOT . \PFAD_TEMPLATES . $parent . '/php/functions.php';
        }
    }

    /**
     * @param array<string, string[]>|null $config
     */
    public function setCachingParams(?array $config = null): self
    {
        $config = $config ?? Shop::getSettings([\CONF_CACHING]);

        return $this->setCaching(self::CACHING_OFF)
            ->setCompileCheck((int)(($config['caching']['compile_check'] ?? 'Y') === 'Y'));
    }

    public function getTemplateUrlPath(): string
    {
        return \PFAD_TEMPLATES . $this->templateDir . '/';
    }

    public function outputFilter(string $tplOutput, $template): string
    {
        if ($template->template_resource !== $this->rootTemplateName) {
            return $tplOutput;
        }
        $hookList = Helper::getHookList();
        if (
            GeneralObject::hasCount(\HOOK_SMARTY_OUTPUTFILTER, $hookList)
            || \count(Dispatcher::getInstance()->getListeners('shop.hook.' . \HOOK_SMARTY_OUTPUTFILTER)) > 0
        ) {
            $this->unregisterFilter('output', [$this, 'outputFilter']);
            $doc = phpQuery::newDocumentHTML($tplOutput, \JTL_CHARSET);
            \executeHook(\HOOK_SMARTY_OUTPUTFILTER, ['smarty' => $this, 'document' => $doc]);
            $tplOutput = $doc->htmlOuter();
        }

        return ($this->config['template']['general']['minify_html'] ?? 'N') === 'Y'
            ? $this->minifyHTML(
                $tplOutput,
                ($this->config['template']['general']['minify_html_css'] ?? 'N') === 'Y',
                ($this->config['template']['general']['minify_html_js'] ?? 'N') === 'Y'
            )
            : $tplOutput;
    }

    /**
     * @inheritdoc
     * @param mixed $parent
     */
    public function isCached($template = null, $cacheID = null, $compileID = null, $parent = null): bool
    {
        return false;
    }

    /**
     * @param int|bool $mode
     * @return $this
     */
    public function setCaching($mode): self
    {
        if (\SMARTY_LEGACY_MODE) {
            $this->smarty4->caching = (int)$mode;
        } else {
            $this->caching = (int)$mode;
        }

        return $this;
    }

    /**
     * @param bool $mode
     * @return $this
     */
    public function setDebugging($mode): self
    {
        if (\SMARTY_LEGACY_MODE) {
            $this->smarty4->debugging = $mode;
        } else {
            $this->debugging = $mode;
        }

        return $this;
    }

    private function minifyHTML(string $html, bool $minifyCSS = false, bool $minifyJS = false): string
    {
        $options = [];
        if ($minifyCSS === true) {
            $options['cssMinifier'] = [\Minify_CSSmin::class, 'minify'];
        }
        if ($minifyJS === true) {
            $options['jsMinifier'] = [JSMin::class, 'minify'];
        }
        try {
            $res = (new \Minify_HTML($html, $options))->process();
        } catch (UnterminatedStringException) {
            $res = $html;
        }

        return $res;
    }

    /**
     * @deprecated since 5.6.0
     */
    public function getCustomFile(string $filename): string
    {
        if (
            self::$isChildTemplate === true
            || !isset($this->config['template']['general']['use_customtpl'])
            || $this->config['template']['general']['use_customtpl'] !== 'Y'
        ) {
            // disabled on child templates for now
            return $filename;
        }
        $file   = \basename($filename, '.tpl');
        $dir    = \dirname($filename);
        $custom = !\str_contains($dir, \PFAD_ROOT)
            ? $this->getTemplateDir($this->context) . (($dir === '.')
                ? ''
                : ($dir . '/')) . $file . '_custom.tpl'
            : ($dir . '/' . $file . '_custom.tpl');

        return \file_exists($custom) ? $custom : $filename;
    }

    /**
     * @inheritdoc
     * @throws \SmartyException
     */
    public function fetch($template = null, $cacheID = null, $compileID = null, $parent = null): string
    {
        if (\is_string($template)) {
            $templateName = $this->getResourceName($template);
        } else {
            $templateName = $template;
        }
        if (\SMARTY_LEGACY_MODE) {
            $debug = $this->smarty4->_debug->template_data ?? null;
            $res   = $this->smarty4->fetch($templateName, $cacheID, $compileID, $parent);
            if ($debug !== null) {
                $this->smarty4->_debug->template_data = \array_merge($debug, $this->smarty4->_debug->template_data);
            }
        } else {
            $debug = $this->_debug->template_data ?? null;
            $res   = parent::fetch($templateName, $cacheID, $compileID);
            if ($debug !== null) {
                // fetch overwrites the old debug data so we have to merge it with our previously saved data
                $this->_debug->template_data = \array_merge($debug, $this->_debug->template_data);
            }
        }

        return $res;
    }

    /**
     * @inheritdoc
     * @param mixed $parent
     */
    public function display($template = null, $cacheID = null, $compileID = null, $parent = null): void
    {
        if ($this->context === ContextType::FRONTEND) {
            $this->rootTemplateName = $template;
            $this->registerFilter('output', [$this, 'outputFilter']);
        }
        $templateName = $this->getResourceName($template);
        if (\SMARTY_LEGACY_MODE) {
            $this->smarty4->display($templateName, $cacheID, $compileID, $parent);
        } else {
            parent::display($templateName, $cacheID, $compileID);
        }
    }

    public function getResponse(string $template): ResponseInterface
    {
        if (\SMARTY_LEGACY_MODE) {
            $smartyInstance = $this->smarty4;
        } else {
            $smartyInstance = $this;
        }

        if ($this->context === ContextType::FRONTEND) {
            $this->rootTemplateName = $template;
            $this->registerFilter('output', [$this, 'outputFilter']);
            /** @var JTLSmartyTemplateClass $tpl */
            $tpl = $smartyInstance->createTemplate(
                $this->getResourceName($template),
                null,
                null,
                $this,
                false
            );
            if (\SMARTY_LEGACY_MODE) {
                $tpl->noOutputFilter = false;
            }
        } else {
            $tpl = $smartyInstance->createTemplate($template, null, null, $this, false);
        }

        if (\SMARTY_LEGACY_MODE) {
            $res = $this->smarty4->fetch($tpl);
        } else {
            $res = parent::fetch($tpl);
        }
        $prf      = Profiler::finalize(false);
        $response = new Response();
        $response->getBody()->write($res . $prf);

        return $response;
    }

    public function getCacheID(): null
    {
        return null;
    }

    public function getResourceName(?string $resourceName): ?string
    {
        if ($resourceName === null) {
            return null;
        }
        $transform = false;
        if (\str_starts_with($resourceName, 'string:') || \str_contains($resourceName, '[')) {
            return $resourceName;
        }
        if (\str_starts_with($resourceName, 'file:')) {
            $resourceName = \str_replace('file:', '', $resourceName);
            $transform    = true;
        }
        $mapped = $this->mapping[$resourceName] ?? null;
        if ($mapped !== null) {
            return $mapped;
        }
        $res = $this->extendResource($resourceName, $transform);

        $this->mapping[$resourceName] = $res;

        return $res;
    }

    protected function extendResource(string $resourceName, bool $transform): string
    {
        if ($this->context !== ContextType::FRONTEND) {
            return $this->getResourceString($resourceName, $transform);
        }
        $cfbName = $resourceName;
        \executeHook(\HOOK_SMARTY_FETCH_TEMPLATE, [
            'original'  => &$resourceName,
            'custom'    => &$resourceName,
            'fallback'  => &$resourceName,
            'out'       => &$cfbName,
            'transform' => $transform
        ]);
        if ($resourceName !== $cfbName) {
            return $this->getResourceString($cfbName, $transform);
        }
        $extends = $this->getExtends($cfbName);
        if (\count($extends) > 1) {
            $transform = false;
            $cfbName   = \sprintf(
                'extends:%s',
                \implode('|', $extends)
            );
        }

        return $this->getResourceString($cfbName, $transform);
    }

    /**
     * @return string[]
     */
    private function getExtends(string $resourceCfbName): array
    {
        $extends = [];
        /** @var array<string, string> $templateDirs */
        $templateDirs = $this->getTemplateDir();
        foreach ($templateDirs as $module => $templateDir) {
            if (\str_starts_with($module, 'plugin_')) {
                $pluginID    = \mb_substr($module, 7);
                $templateVar = 'oPlugin_' . $pluginID;
                if ($this->getTemplateVars($templateVar) === null) {
                    $plugin = Helper::getPluginById($pluginID);
                    $this->assign($templateVar, $plugin);
                }
            }
            if (\file_exists($templateDir . $resourceCfbName)) {
                $extends[] = \sprintf('[%s]%s', $module, $resourceCfbName);
            }
        }

        return $extends;
    }

    private function getResourceString(string $resource, bool $transform): string
    {
        return $transform ? ('file:' . $resource) : $resource;
    }

    public function setUseSubDirs($useSubDirs): self
    {
        if (\SMARTY_LEGACY_MODE) {
            $this->smarty4->setUseSubDirs((bool)$useSubDirs);
        } else {
            parent::setUseSubDirs((bool)$useSubDirs);
        }

        return $this;
    }

    /**
     * @param bool $force
     * @return $this
     */
    public function setForceCompile($force): self
    {
        if (\SMARTY_LEGACY_MODE) {
            $this->smarty4->setForceCompile((bool)$force);
        } else {
            parent::setForceCompile((bool)$force);
        }

        return $this;
    }

    /**
     * @param int $check
     * @return $this
     */
    public function setCompileCheck($check): self
    {
        if (\SMARTY_LEGACY_MODE) {
            $this->smarty4->setCompileCheck($check);
        } else {
            parent::setCompileCheck($check);
        }

        return $this;
    }

    /**
     * @param int $reporting
     * @return $this
     */
    public function setErrorReporting($reporting): self
    {
        if (\SMARTY_LEGACY_MODE) {
            $this->smarty4->setErrorReporting($reporting);
        } else {
            parent::setErrorReporting($reporting);
        }

        return $this;
    }

    public static function getIsChildTemplate(): bool
    {
        return self::$isChildTemplate;
    }

    /**
     * When Smarty is used in an insecure context (e.g. when third parties are granted access to shop admin) this
     * function activates a secure mode that:
     *   - deactivates {php}-tags
     *   - removes php code (that could be written to a file an then be executes)
     *   - applies a whitelist for php functions (Smarty modifiers and functions)
     *
     * @return $this
     * @deprecated since 5.6.0
     */
    public function activateBackendSecurityMode(): self
    {
        return $this;
    }

    /**
     * Get a list of php functions that should be safe to use in an insecure context.
     *
     * @return string<callable>[]
     */
    public function getSecurePhpFunctions(): array
    {
        static $functions;
        if ($functions === null) {
            $functions = \array_map('\trim', \explode(',', \SECURE_PHP_FUNCTIONS));
        }

        return $functions;
    }

    public function assignDeprecated(string $name, mixed $value, string $version): self
    {
        if (\SMARTY_LEGACY_MODE) {
            $this->smarty4->tpl_vars[$name] = new DeprecatedVariable($value, $name, $version);
        } else {
            $variable = new DeprecatedVariableSmarty5($value);
            $variable->setName($name)->setVersion($version);
            $this->tpl_vars[$name] = $variable;
        }

        return $this;
    }

    /**
     * @inheritdoc
     * @param string|string[] $tpl_var
     */
    public function clearAssign($tpl_var): self
    {
        if (\SMARTY_LEGACY_MODE) {
            $this->smarty4->clearAssign($tpl_var);
        } else {
            parent::clearAssign($tpl_var);
        }

        return $this;
    }

    /**
     * @inheritdoc
     * @param string|string[] $tpl_var
     */
    public function assign($tpl_var, $value = null, $nocache = false, $scope = null): self
    {
        if (\SMARTY_LEGACY_MODE) {
            $this->smarty4->assign($tpl_var, $value, $nocache);
        } else {
            parent::assign($tpl_var, $value, $nocache, $scope);
        }

        return $this;
    }

    /**
     * @inheritdoc
     * @param mixed|null $cache_attr
     */
    public function registerPlugin($type, $name, $callback, $cacheable = true, mixed $cache_attr = null): self
    {
        if (\SMARTY_LEGACY_MODE) {
            $this->smarty4->registerPlugin($type, $name, $callback, $cacheable, $cache_attr);
        } else {
            parent::registerPlugin($type, $name, $callback, $cacheable);
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function registerClass($class_name, $class_impl): self
    {
        if (\SMARTY_LEGACY_MODE) {
            $this->smarty4->registerClass($class_name, $class_impl);
        } else {
            parent::registerClass($class_name, $class_impl);
        }

        return $this;
    }

    /**
     * @inheritdoc
     * @param string[]|string $template_dir
     * @param bool            $isConfig
     */
    public function setTemplateDir($template_dir, $isConfig = false): self
    {
        if (\SMARTY_LEGACY_MODE) {
            $this->smarty4->setTemplateDir($template_dir, $isConfig);
        } else {
            parent::setTemplateDir($template_dir, $isConfig);
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setCompileDir($compile_dir): self
    {
        if (\SMARTY_LEGACY_MODE) {
            $this->smarty4->setCompileDir($compile_dir);
        } else {
            parent::setCompileDir($compile_dir);
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setCacheDir($cache_dir): self
    {
        if (\SMARTY_LEGACY_MODE) {
            $this->smarty4->setCacheDir($cache_dir);
        } else {
            parent::setCacheDir($cache_dir);
        }

        return $this;
    }

    /**
     * @inheritdoc
     * @param string|string[] $template_dir
     */
    public function addTemplateDir($template_dir, $key = null, $isConfig = false): self
    {
        if (\SMARTY_LEGACY_MODE) {
            $this->smarty4->addTemplateDir($template_dir, $key, $isConfig);
        } else {
            parent::addTemplateDir($template_dir, $key, $isConfig);
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setCacheLifetime($cache_lifetime): self
    {
        if (\SMARTY_LEGACY_MODE) {
            $this->smarty4->setCacheLifetime($cache_lifetime);
        } else {
            parent::setCacheLifetime($cache_lifetime);
        }

        return $this;
    }

    /**
     * @param string          $type
     * @param string|callable $name
     */
    public function unregisterFilter($type, $name): self
    {
        if (\SMARTY_LEGACY_MODE) {
            $this->smarty4->unregisterFilter($type, $name);
        } else {
            parent::unregisterFilter($type, $name);
        }

        return $this;
    }

    /**
     * @inheritdoc
     * @return string[]|string|null
     */
    public function getTemplateDir($index = null, $isConfig = false): array|string|null
    {
        if (\SMARTY_LEGACY_MODE) {
            return $this->smarty4->getTemplateDir($index, $isConfig);
        }

        return parent::getTemplateDir($index, $isConfig);
    }

    /**
     * @inheritdoc
     */
    public function registerFilter($type, $callback, $name = null): self
    {
        if (\SMARTY_LEGACY_MODE) {
            $this->smarty4->registerFilter($type, $callback, $name);
        } else {
            parent::registerFilter($type, $callback, $name);
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getTemplateVars($varName = null, $searchParents = true)
    {
        if (\SMARTY_LEGACY_MODE) {
            return $this->smarty4->getTemplateVars($varName, null, $searchParents);
        }

        return parent::getTemplateVars($varName, $searchParents);
    }

    /**
     * @param array<mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (\SMARTY_LEGACY_MODE) {
            return $this->smarty4->$name(...$arguments);
        }

        return parent::$name(...$arguments);
    }

    /**
     * @param string $config_dir
     */
    public function setConfigDir($config_dir): self
    {
        if (\SMARTY_LEGACY_MODE) {
            $this->smarty4->setConfigDir($config_dir);
        } else {
            parent::setConfigDir($config_dir);
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function registerResource($name, $resource_handler): self
    {
        if (\SMARTY_LEGACY_MODE) {
            $this->smarty4->registerResource($name, $resource_handler);
        } else {
            parent::registerResource($name, $resource_handler);
        }

        return $this;
    }

    /**
     * @inheritdoc
     * @param array<mixed> $data
     * @param string       $resource_name
     * @param string|null  $parent
     * @param string|null  $compile_id
     * @param int|null     $caching
     * @param int|null     $cache_lifetime
     * @param string|null  $cache_id
     */
    public function doCreateTemplate(
        $resource_name,
        $cache_id = null,
        $compile_id = null,
        $parent = null,
        $caching = null,
        $cache_lifetime = null,
        bool $isConfig = false,
        array $data = []
    ): Template {
        return parent::doCreateTemplate(
            $this->getResourceName($resource_name),
            $cache_id,
            $compile_id,
            $parent,
            $caching,
            $cache_lifetime,
            $isConfig,
            $data
        );
    }
}
