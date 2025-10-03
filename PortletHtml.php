<?php

declare(strict_types=1);

namespace JTL\OPC;

use JTL\Plugin\Plugin;
use JTL\Shop;
use JTL\Smarty\ContextType;

/**
 * Trait PortletHtml
 * @package JTL\OPC
 */
trait PortletHtml
{
    /**
     * @throws \Exception
     */
    public function getPreviewHtml(PortletInstance $instance): string
    {
        return $this->getPreviewHtmlFromTpl($instance);
    }

    /**
     * @throws \Exception
     */
    public function getFinalHtml(PortletInstance $instance, bool $inContainer = true): string
    {
        return $this->getFinalHtmlFromTpl($instance, $inContainer);
    }

    /**
     * @throws \Exception
     */
    public function getConfigPanelHtml(PortletInstance $instance): string
    {
        return $this->getAutoConfigPanelHtml($instance);
    }

    public function getButtonHtml(): string
    {
        return \file_get_contents($this->getDefaultIconSvgPath()) . '<span>' . $this->getTitle() . '</span>';
    }

    public function getFontAwesomeButtonHtml(string $faClasses): string
    {
        return '<i class="' . $faClasses . '"></i><span>' . $this->getTitle() . '</span>';
    }

    /**
     * @return string[]
     */
    public function getEditorInitScripts(): array
    {
        return ['editor_init.js'];
    }

    final public function getBasePath(): string
    {
        $plugin = $this->getPlugin();
        if ($plugin !== null) {
            /** @var Plugin $plugin */
            return $plugin->getPaths()->getPortletsPath() . $this->getClass() . '/';
        }

        return \PFAD_ROOT . \PFAD_INCLUDES . 'src/OPC/Portlets/' . $this->getClass() . '/';
    }

    final public function getBaseUrl(): string
    {
        $plugin = $this->getPlugin();
        if ($plugin !== null) {
            /** @var Plugin $plugin */
            return $plugin->getPaths()->getPortletsUrl() . $this->getClass() . '/';
        }

        return Shop::getURL() . '/' . \PFAD_INCLUDES . 'src/OPC/Portlets/' . $this->getClass() . '/';
    }

    /**
     * @throws \Exception
     */
    final protected function getPreviewHtmlFromTpl(PortletInstance $instance): string
    {
        return $this->getHtmlFromTpl($instance, true);
    }

    /**
     * @throws \SmartyException
     */
    final protected function getFinalHtmlFromTpl(PortletInstance $instance, bool $inContainer = true): string
    {
        return $this->getHtmlFromTpl($instance, false, $inContainer);
    }

    /**
     * @throws \SmartyException
     */
    final protected function getHtmlFromTpl(
        PortletInstance $instance,
        bool $isPreview,
        bool $inContainer = true
    ): string {
        $tplPath = $this->getBasePath() . $this->getClass() . '.tpl';
        if (\file_exists($tplPath) === false) {
            $tplPath = \PFAD_ROOT . \PFAD_INCLUDES . 'src/OPC/Portlets/GenericPortlet/GenericPortlet.tpl';
        }

        return Shop::Smarty(false, ContextType::FRONTEND)->assign('isPreview', $isPreview)
            ->assign('portlet', $this)
            ->assign('instance', $instance)
            ->assign('inContainer', $inContainer)
            ->fetch($tplPath);
    }

    /**
     * @throws \Exception
     */
    final protected function getConfigPanelHtmlFromTpl(PortletInstance $instance): string
    {
        return Shop::Smarty()->assign('portlet', $this)
            ->assign('instance', $instance)
            ->fetch($this->getBasePath() . 'configpanel.tpl');
    }

    /**
     * @throws \SmartyException
     */
    final protected function getAutoConfigPanelHtml(PortletInstance $instance): string
    {
        $desc = $this->getPropertyDesc();
        $tabs = $this->getPropertyTabs();
        foreach ($tabs as $tabname => $propnames) {
            if (\is_string($propnames)) {
                if ($propnames === 'styles') {
                    $tabs[$tabname] = $this->getStylesPropertyDesc();
                } elseif ($propnames === 'animations') {
                    $tabs[$tabname] = $this->getAnimationsPropertyDesc();
                }
            } else {
                foreach ($propnames as $i => $propname) {
                    $tabs[$tabname][$propname] = $desc[$propname];
                    unset($tabs[$tabname][$i], $desc[$propname]);
                }
            }
        }

        if (\count($desc) > 0) {
            $tabs = [\__('general') => $desc] + $tabs;
        }

        return Shop::Smarty()
            ->assign('portlet', $this)
            ->assign('instance', $instance)
            ->assign('tabs', $tabs)
            ->assign('opc', Shop::Container()->getOPC())
            ->fetch(\PFAD_ROOT . \PFAD_ADMIN . 'opc/tpl/config/autoconfig-panel.tpl');
    }

    final protected function getPreviewRootHtml(
        PortletInstance $instance,
        string $tag = 'div',
        string $innerHtml = ''
    ): string {
        $attributes    = $instance->getAttributeString();
        $dataAttribute = $instance->getDataAttributeString();

        return '<' . $tag . ' ' . $attributes . ' ' . $dataAttribute . '>' . $innerHtml . '</' . $tag . '>';
    }

    final protected function getFinalRootHtml(
        PortletInstance $instance,
        string $tag = 'div',
        string $innerHtml = ''
    ): string {
        $attributes = $instance->getAttributeString();

        return '<' . $tag . ' ' . $attributes . '>' . $innerHtml . '</' . $tag . '>';
    }

    final protected function getDefaultIconSvgPath(): string
    {
        $path = $this->getBasePath() . 'icon.svg';

        if (\file_exists($path) === false) {
            $path = \PFAD_ROOT . \PFAD_INCLUDES . 'src/OPC/Portlets/GenericPortlet/generic.icon.svg';
        }

        return $path;
    }

    final protected function getCommonResource(string $name): string
    {
        return Shop::getURL() . '/' . \PFAD_INCLUDES . 'src/OPC/Portlets/common/' . $name;
    }

    final public function getDefaultPreviewImageUrl(): string
    {
        return Shop::getURL() . '/' . \BILD_KEIN_KATEGORIEBILD_VORHANDEN;
    }

    final public function getFontAwesomeIcon(string $faCode): string
    {
        /** @var array<string, string> $faTable */
        $faTable    = include \PFAD_ROOT . \PFAD_TEMPLATES . 'NOVA/themes/base/fontawesome/metadata/icons.php';
        $faGlyphHex = $faTable[$faCode];
        $faClass    = \substr($faCode, 0, 3);

        return '<span class="opc-Icon opc-Icon-' . $faClass . '">&#x' . $faGlyphHex . ';</span>';
    }
}
