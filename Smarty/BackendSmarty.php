<?php

declare(strict_types=1);

namespace JTL\Smarty;

use JTL\Backend\AdminTemplate;
use JTL\Backend\Notification;
use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Helpers\Form;
use JTL\Helpers\Request;
use JTL\Helpers\Text;
use JTL\Language\LanguageHelper;
use JTL\Profiler;
use JTL\Router\Route;
use JTL\Shop;
use scc\DefaultComponentRegistrator;
use sccbs3\Bs3sccRenderer;

/**
 * Class BackendSmarty
 * @package \JTL\Smarty
 */
class BackendSmarty extends JTLSmarty
{
    protected string $templateDir = 'bootstrap';

    public function __construct(private readonly DbInterface $db, private readonly JTLCacheInterface $cache)
    {
        parent::__construct(false, ContextType::BACKEND);
    }

    protected function initTemplate(): ?string
    {
        $compileDir = \PFAD_ROOT . \PFAD_ADMIN . \PFAD_COMPILEDIR;
        if (!\is_dir($compileDir) && !\mkdir($compileDir) && !\is_dir($compileDir)) {
            throw new \RuntimeException(\sprintf('Directory "%s" could not be created', $compileDir));
        }
        $this->setCaching(self::CACHING_OFF)
            ->setDebugging(\SMARTY_DEBUG_CONSOLE)
            ->setTemplateDir([$this->context => \PFAD_ROOT . \PFAD_ADMIN . \PFAD_TEMPLATES . $this->templateDir])
            ->setCompileDir($compileDir)
            ->setConfigDir(\PFAD_ROOT . \PFAD_ADMIN . \PFAD_TEMPLATES . $this->templateDir . '/lang/');

        return null;
    }

    protected function registerPlugins(): void
    {
        parent::registerPlugins();
        $scc     = new DefaultComponentRegistrator(new Bs3sccRenderer($this));
        $plugins = new BackendPlugins($this->db, $this);
        $scc->registerComponents();
        $plugins->registerPlugins();
    }

    /**
     * @inheritdoc
     */
    protected function init(?string $parent = null): void
    {
        $template           = AdminTemplate::getInstance($this->db, $this->cache);
        $shopURL            = Shop::getURL();
        $adminURL           = Shop::getAdminURL();
        $currentTemplateDir = $this->getTemplateUrlPath();
        $availableLanguages = LanguageHelper::getInstance($this->db, $this->cache)->gibInstallierteSprachen();
        $resourcePaths      = $template->getResources(false);
        $gettext            = Shop::Container()->getGetText();
        $langTag            = $_SESSION['AdminAccount']->language ?? $gettext->getLanguage();
        $faviconUrl         = $adminURL . (\file_exists(\PFAD_ROOT . \PFAD_ADMIN . 'favicon.ico')
                ? '/favicon.ico'
                : '/favicon-default.ico');

        $_SESSION['adminTheme'] = $_SESSION['adminTheme'] ?? $this->db->selectSingleRow(
            'tadminlogin',
            'kAdminlogin',
            Shop::Container()->getAdminAccount()->getID()
        )->theme ?? 'auto';

        $this->assignDeprecated('URL_SHOP', $shopURL, '5.2.0')
            ->assignDeprecated('session_name', \session_name(), '5.2.0')
            ->assignDeprecated('session_id', \session_id(), '5.2.0')
            ->assignDeprecated('PFAD_CODEMIRROR', $shopURL . '/' . \PFAD_CODEMIRROR, '5.2.0')
            ->assignDeprecated('Einstellungen', $this->config, '5.2.0')
            ->assign('jtl_token', Form::getTokenInput())
            ->assign('shopURL', $shopURL)
            ->assign('adminURL', $adminURL)
            ->assign('adminTplVersion', $template->version)
            ->assign('currentTemplateDir', $currentTemplateDir)
            ->assign('templateBaseURL', $adminURL . '/' . $currentTemplateDir)
            ->assign('admin_css', $resourcePaths['css'])
            ->assign('admin_js', $resourcePaths['js'])
            ->assign('config', $this->config)
            ->assign('notifications', Notification::getInstance($this->db))
            ->assign('alertList', Shop::Container()->getAlertService())
            ->assign('language', $langTag)
            ->assign('sprachen', $availableLanguages)
            ->assign('availableLanguages', $availableLanguages)
            ->assign('languageName', \Locale::getDisplayLanguage($langTag, $langTag))
            ->assign('languages', $gettext->getAdminLanguages())
            ->assign('faviconAdminURL', $faviconUrl)
            ->assign('cTab', Text::filterXSS(Request::verifyGPDataString('tab')))
            ->assign(
                'wizardDone',
                (($this->config['global']['global_wizard_done'] ?? 'Y') === 'Y'
                    || !\str_contains($_SERVER['REQUEST_URI'], Route::WIZARD))
                && !Request::getVar('fromWizard')
            )
            ->assign('themeMode', $_SESSION['adminTheme']);
    }

    /**
     * @inheritdoc
     */
    public function display($template = null, $cacheID = null, $compileID = null, $parent = null): void
    {
        parent::display(
            $this->getResourceName($template),
            $cacheID,
            $compileID,
            $parent
        );
        Profiler::finalize();
    }
}
