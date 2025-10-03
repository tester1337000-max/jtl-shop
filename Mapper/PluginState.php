<?php

declare(strict_types=1);

namespace JTL\Mapper;

use JTL\L10n\GetText;
use JTL\Plugin\State;

/**
 * Class PluginState
 * @package JTL\Mapper
 */
class PluginState
{
    public function __construct(protected GetText $getText)
    {
        $this->getText->loadAdminLocale('pages/pluginverwaltung');
    }

    public function map(int $state): string
    {
        return match ($state) {
            State::DISABLED                 => 'Deaktiviert',
            State::ACTIVATED                => 'Aktiviert',
            State::ERRONEOUS                => 'Fehlerhaft',
            State::UPDATE_FAILED            => 'Update fehlgeschlagen',
            State::LICENSE_KEY_MISSING      => 'Lizenzschlüssel fehlt',
            State::LICENSE_KEY_INVALID      => 'Lizenzschlüssel ungültig',
            State::EXS_LICENSE_EXPIRED      => 'Lizenz abgelaufen',
            State::EXS_SUBSCRIPTION_EXPIRED => 'Subscription abgelaufen',
            default                         => 'Unbekannt',
        };
    }

    public function mapTranslated(int $state): string
    {
        return \__($this->map($state));
    }
}
