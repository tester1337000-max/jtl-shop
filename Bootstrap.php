<?php

declare(strict_types=1);

namespace Template\NOVA;

use JTL\Shop;
use JTL\Template\Bootstrapper;
use scc\ComponentRegistratorInterface;
use scc\DefaultComponentRegistrator;
use scc\Renderer;
use scc\RendererInterface;
use Smarty\Smarty;

/**
 * Class Bootstrap
 * @package Template\NOVA
 */
class Bootstrap extends Bootstrapper
{
    /**
     * @var ComponentRegistratorInterface|null
     */
    protected ?ComponentRegistratorInterface $scc = null;

    /**
     * @var RendererInterface|null
     */
    protected ?RendererInterface $renderer = null;

    /**
     * @inheritdoc
     */
    public function boot(): void
    {
        parent::boot();
        $this->registerPlugins();
    }

    protected function registerPlugins(): void
    {
        $smarty = $this->getSmarty();
        if ($smarty === null) {
            // this will never happen but it calms the IDE down
            return;
        }
        $plugins        = new Plugins($this->getDB(), $this->getCache());
        $this->renderer = new Renderer($smarty);
        $this->scc      = new DefaultComponentRegistrator($this->renderer);
        $this->scc->registerComponents();

        if (isset($_GET['scc-demo']) && Shop::isAdmin()) {
            $smarty->display('demo.tpl');
            die();
        }
        $func = Smarty::PLUGIN_FUNCTION;
        $mod  = Smarty::PLUGIN_MODIFIER;

        $smarty->registerPlugin($func, 'gibPreisStringLocalizedSmarty', $plugins->getLocalizedPrice(...))
            ->registerPlugin($func, 'getBoxesByPosition', $plugins->getBoxesByPosition(...))
            ->registerPlugin($func, 'has_boxes', $plugins->hasBoxes(...))
            ->registerPlugin($func, 'imageTag', $plugins->getImgTag(...))
            ->registerPlugin($func, 'getCheckBoxForLocation', $plugins->getCheckBoxForLocation(...))
            ->registerPlugin($func, 'hasCheckBoxForLocation', $plugins->hasCheckBoxForLocation(...))
            ->registerPlugin($func, 'aaURLEncode', $plugins->aaURLEncode(...))
            ->registerPlugin($func, 'get_navigation', $plugins->getNavigation(...))
            ->registerPlugin($func, 'get_category_array', $plugins->getCategoryArray(...))
            ->registerPlugin($func, 'get_category_parents', $plugins->getCategoryParents(...))
            ->registerPlugin($func, 'prepare_image_details', $plugins->prepareImageDetails(...))
            ->registerPlugin($func, 'get_manufacturers', $plugins->getManufacturers(...))
            ->registerPlugin($func, 'get_cms_content', $plugins->getCMSContent(...))
            ->registerPlugin($func, 'get_static_route', $plugins->getStaticRoute(...))
            ->registerPlugin($func, 'hasOnlyListableVariations', $plugins->hasOnlyListableVariations(...))
            ->registerPlugin($func, 'get_product_list', $plugins->getProductList(...))
            ->registerPlugin($func, 'captchaMarkup', $plugins->captchaMarkup(...))
            ->registerPlugin($func, 'getStates', $plugins->getStates(...))
            ->registerPlugin($func, 'getDecimalLength', $plugins->getDecimalLength(...))
            ->registerPlugin($func, 'getUploaderLang', $plugins->getUploaderLang(...))
            ->registerPlugin($func, 'getCountry', $plugins->getCountry(...))
            ->registerPlugin($func, 'sanitizeTitle', $plugins->sanitizeTitle(...))
            ->registerPlugin($mod, 'seofy', $plugins->seofy(...))
            ->registerPlugin($mod, 'has_trans', $plugins->hasTranslation(...))
            ->registerPlugin($mod, 'trans', $plugins->getTranslation(...))
            ->registerPlugin($mod, 'transByISO', $plugins->getTranslationByISO(...))
            ->registerPlugin($mod, 'transById', $plugins->getTranslationById(...))
            ->registerPlugin($mod, 'formatForMicrodata', $plugins->formatForMicrodata(...));
    }
}
