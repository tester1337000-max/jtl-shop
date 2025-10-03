<?php

declare(strict_types=1);

namespace JTL\L10n;

class Translator extends \Gettext\Translator
{
    public function translatePluginOrCoreMessage(?string $pluginId, string $original): string
    {
        $translation = $this->getTranslation($pluginId, null, $original);

        if (isset($translation[0]) && $translation[0] !== '') {
            return $translation[0];
        }

        if (!empty($pluginId)) {
            // if not found in plugin domain, try again in core domain
            return parent::translate(null, null, $original);
        }

        return $original;
    }
}
