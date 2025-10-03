<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade;

use JTL\DB\DbInterface;
use JTL\Session\Backend;
use JTL\Shop;
use stdClass;

use function Functional\map;

final class Channels
{
    public const ACTIVE_CHANNEL_INDEX = 'active_upgrade_channel';

    public static function getActiveChannel(?DbInterface $db = null): Channel
    {
        /** @var Channel|null $channel */
        $channel = Backend::get(self::ACTIVE_CHANNEL_INDEX);
        if ($channel === null) {
            /** @var string|null $channelName */
            $channelName = ($db ?? Shop::Container()->getDB())->select('tversion', [], [])?->releaseType;
            $channel     = Channel::from(\strtoupper($channelName ?? Channel::STABLE->value));
            Backend::set(self::ACTIVE_CHANNEL_INDEX, $channel);
        }

        return $channel;
    }

    /**
     * @return array<object{id: int, name: string, disabled: bool, dangerous: bool, selected: bool}&stdClass>
     */
    public static function getAvailableChannels(): array
    {
        return map(
            Channel::cases(),
            static fn(Channel $channel, int $idx): stdClass => (object)[
                'id'        => $idx + 1,
                'name'      => $channel->value,
                'disabled'  => self::isDisabled($channel),
                'dangerous' => $channel !== Channel::STABLE,
                'selected'  => $channel === self::getActiveChannel()
            ]
        );
    }

    private static function isDisabled(Channel $channel): bool
    {
        return match ($channel) {
            Channel::BLEEDING_EDGE => !\SHOW_UPGRADE_CHANNEL_BLEEDING_EDGE,
            Channel::ALPHA         => !\SHOW_UPGRADE_CHANNEL_ALPHA,
            Channel::BETA          => !\SHOW_UPGRADE_CHANNEL_BETA,
            default                => false,
        };
    }

    public static function updateActiveChannel(Channel $channel): void
    {
        Backend::set(self::ACTIVE_CHANNEL_INDEX, $channel);
    }
}
