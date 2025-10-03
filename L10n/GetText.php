<?php

declare(strict_types=1);

namespace JTL\L10n;

use FilesystemIterator;
use Gettext\Generator\ArrayGenerator;
use Gettext\Loader\MoLoader;
use Gettext\Translations;
use Gettext\TranslatorFunctions;
use JTL\Backend\Settings\Item;
use JTL\Plugin\Admin\ListingItem as PluginListingItem;
use JTL\Plugin\PluginInterface;
use JTL\Template\Admin\ListingItem as TemplateListingItem;
use JTL\Template\Model;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use stdClass;

/**
 * Class GetText
 * @package JTL\L10n
 */
class GetText extends GetTextBC
{
    private string $langTag = 'de-DE';

    /**
     * @var array<string, Translations|null>
     */
    private array $translations = [];

    public function __construct(private Translator $translator = new Translator())
    {
        TranslatorFunctions::register($this->translator);
        $this->setLanguage()->loadAdminLocale('base');
    }

    public function getLanguage(): string
    {
        return $this->langTag;
    }

    public function getMoPath(string $dir, string $domain): string
    {
        return $dir . 'locale/' . $this->langTag . '/' . $domain . '.mo';
    }

    private function getAdminMoPath(string $domain): string
    {
        return $this->getMoPath(\PFAD_ROOT . \PFAD_ADMIN, $domain);
    }

    private function getTemplateMoPath(string $domain, Model $template): string
    {
        return $this->getMoPath(\PFAD_ROOT . \PFAD_TEMPLATES . $template->getDir() . '/', $domain);
    }

    private function getPluginMoPath(string $domain, PluginInterface $plugin): string
    {
        return $this->getMoPath($plugin->getPaths()->getBasePath(), $domain);
    }

    public function loadLocaleFile(string $path): self
    {
        if (\array_key_exists($path, $this->translations)) {
            return $this;
        }
        $this->translations[$path] = null;
        if (!\file_exists($path)) {
            return $this;
        }
        $this->translator->addTranslations($this->loadCached($path));

        return $this;
    }

    private function loadTranslations(string $dir, string $domain): self
    {
        return $this->loadLocaleFile($this->getMoPath($dir, $domain));
    }

    public function loadAdminLocale(string $domain): self
    {
        return $this->loadLocaleFile($this->getAdminMoPath($domain));
    }

    public function loadPluginLocale(string $domain, PluginInterface $plugin): self
    {
        return $this->loadLocaleFile($this->getPluginMoPath($domain, $plugin));
    }

    public function loadTemplateLocale(string $domain, Model $template): self
    {
        return $this->loadLocaleFile($this->getTemplateMoPath($domain, $template));
    }

    public function loadPluginItemLocale(string $domain, PluginListingItem $item): self
    {
        return $this->loadTranslations(\PFAD_ROOT . \PLUGIN_DIR . $item->getDir() . '/', $domain);
    }

    public function loadTemplateItemLocale(string $domain, TemplateListingItem $item): self
    {
        return $this->loadTranslations(\PFAD_ROOT . \PFAD_TEMPLATES . $item->getDir() . '/', $domain);
    }

    public function getAdminTranslations(string $domain): ?Translations
    {
        $path = $this->getAdminMoPath($domain);
        $this->loadLocaleFile($path);

        $this->translations[$path] = (new MoLoader())->loadFile($path);

        return $this->translations[$path];
    }

    public function setLanguage(?string $langTag = null): self
    {
        $langTag = $langTag ?? $_SESSION['AdminAccount']->language ?? $this->langTag;
        if ($this->langTag === $langTag) {
            return $this;
        }
        $oldLangTag         = $this->langTag;
        $oldTranslations    = $this->translations;
        $this->langTag      = $langTag;
        $this->translations = [];
        $this->translator   = new Translator();
        TranslatorFunctions::register($this->translator);
        if (empty($oldLangTag)) {
            return $this;
        }
        foreach ($oldTranslations as $path => $trans) {
            $newPath = \str_replace('/' . $oldLangTag . '/', '/' . $langTag . '/', $path);
            $this->loadLocaleFile($newPath);
        }

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getAdminLanguages(): array
    {
        $languages = [];
        foreach (\scandir(\PFAD_ROOT . \PFAD_ADMIN . 'locale/', \SCANDIR_SORT_ASCENDING) ?: [] as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }
            $locale = \Locale::getDisplayLanguage($dir, $dir);
            if ($locale !== false) {
                $languages[$dir] = $locale;
            }
        }

        return $languages;
    }

    public function loadConfigLocales(bool $withGroups = false, bool $withSections = false): void
    {
        $this->loadAdminLocale('configs/configs')
            ->loadAdminLocale('configs/values')
            ->loadAdminLocale('configs/groups');
        if ($withGroups) {
            $this->loadAdminLocale('configs/groups');
        }
        if ($withSections) {
            $this->loadAdminLocale('configs/sections');
        }
    }

    public function localizeConfig(Item $config): void
    {
        if ($config->isConfigurable()) {
            $config->setName(\__($config->getValueName() . '_name'));
            $config->setDescription(\__($config->getValueName() . '_desc'));
            if ($config->getDescription() === $config->getValueName() . '_desc') {
                $config->setDescription('');
            }
        } else {
            $config->setName(\__($config->getValueName()));
        }
    }

    /**
     * @param Item       $config
     * @param stdClass[] $values
     */
    public function localizeConfigValues(Item $config, array $values): void
    {
        foreach ($values as $value) {
            $value->cName = \__($config->getValueName() . '_value(' . $value->cWert . ')');
        }
    }

    /**
     * @param string $path
     * @return array<string, mixed>
     */
    private function loadCached(string $path): array
    {
        $cacheFile = $path;
        if (\str_starts_with($path, \PFAD_ROOT)) {
            $cacheFile = \str_replace(\PFAD_ROOT, \DIR_LOCALE_CACHE, $cacheFile);
        }
        $cacheFile   .= '.php';
        $cacheMTime  = \file_exists($cacheFile) ? \filemtime($cacheFile) : 0;
        $moFileMTime = \file_exists($path) ? \filemtime($path) : 0;
        if (\PLUGIN_DEV_MODE === true || $cacheMTime < $moFileMTime) {
            $dir     = \dirname($cacheFile);
            $nocache = \PLUGIN_DEV_MODE;
            if ($nocache === false && (!\is_dir($dir) && !\mkdir($dir, 0777, true) && !\is_dir($dir))) {
                $nocache = true;
            }
            $this->translations[$path] = (new MoLoader())->loadFile($path);
            $translations              = (new ArrayGenerator())->generateArray($this->translations[$path]);
            if ($nocache === false) {
                \file_put_contents($cacheFile, "<?php\n return " . \var_export($translations, true) . ';');
            }
        } else {
            $translations = require $cacheFile;
        }

        return $translations;
    }

    public function flushCache(): void
    {
        if (!\is_dir(\DIR_LOCALE_CACHE)) {
            return;
        }
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                \DIR_LOCALE_CACHE,
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS
            ),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        /** @var \SplFileInfo $item */
        foreach ($iter as $item) {
            if ($item->isLink() || $item->isFile()) {
                \unlink($item->getPathname());
            } elseif ($item->isDir()) {
                \rmdir($item->getPathname());
            }
        }
    }

    public function translatePluginOrCoreMessage(?string $pluginId, string $original): string
    {
        return $this->translator->translatePluginOrCoreMessage($pluginId, $original);
    }
}
