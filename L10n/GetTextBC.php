<?php

declare(strict_types=1);

namespace JTL\L10n;

use Gettext\Translations;
use JTL\Backend\Settings\Item;
use JTL\Plugin\PluginInterface;
use JTL\Template\Model;
use stdClass;

abstract class GetTextBC
{
    /**
     * @deprecated since 5.6.0
     */
    public function getDefaultLanguage(): string
    {
        \trigger_error(__METHOD__ . ' is deprecated.', \E_USER_DEPRECATED);
        return 'de-DE';
    }

    /**
     * @deprecated since 5.6.0
     */
    public function getAdminDir(): string
    {
        \trigger_error(__METHOD__ . ' is deprecated.', \E_USER_DEPRECATED);
        return \PFAD_ROOT . \PFAD_ADMIN;
    }

    /**
     * @deprecated since 5.6.0
     */
    public function getTemplateDir(Model $template): string
    {
        \trigger_error(__METHOD__ . ' is deprecated.', \E_USER_DEPRECATED);
        return \PFAD_ROOT . \PFAD_TEMPLATES . $template->getDir() . '/';
    }

    /**
     * @deprecated since 5.6.0
     */
    public function getPluginDir(PluginInterface $plugin): string
    {
        \trigger_error(__METHOD__ . ' is deprecated.', \E_USER_DEPRECATED);
        return $plugin->getPaths()->getBasePath();
    }

    /**
     * @deprecated since 5.6.0
     */
    public function localizeConfigSection(stdClass $section): void
    {
        \trigger_error(__METHOD__ . ' is deprecated.', \E_USER_DEPRECATED);
        $section->cName = \__('configsection_' . $section->kEinstellungenSektion);
    }

    /**
     * @deprecated since 5.6.0
     */
    public function localizeConfigValue(Item $config, stdClass $value): void
    {
        \trigger_error(__METHOD__ . ' is deprecated.', \E_USER_DEPRECATED);
        $value->cName = \__($config->getValueName() . '_value(' . $value->cWert . ')');
    }

    /**
     * @deprecated since 5.6.0
     */
    public function localizeConfigSections(): void
    {
        \trigger_error(__METHOD__ . ' is deprecated.', \E_USER_DEPRECATED);
    }

    /**
     * @deprecated since 5.6.0
     */
    public function getTranslations(): ?Translations
    {
        \trigger_error(__METHOD__ . ' is deprecated.', \E_USER_DEPRECATED);
        return null;
    }

    /**
     * @deprecated since 5.6.0
     */
    public function localizeConfigs(): void
    {
        \trigger_error(__METHOD__ . ' is deprecated.', \E_USER_DEPRECATED);
    }
}
