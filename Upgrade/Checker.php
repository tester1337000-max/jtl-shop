<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade;

use Illuminate\Support\Collection;
use JTL\Backend\Upgrade\Release\Release;
use JTL\Backend\Upgrade\Release\ReleaseCollection;
use JTL\DB\DbInterface;
use JTLShop\SemVer\Version;

final class Checker
{
    /**
     * @var Collection<int, Release>
     */
    private Collection $newerReleases;

    public function __construct(private readonly DbInterface $db, private readonly ReleaseCollection $downloader)
    {
        $this->newerReleases = new Collection();
    }

    public function check(): void
    {
        /** @var string|null $channel */
        $channel = $this->db->select('tversion', [], [])->releaseType ?? null;
        if (empty($channel)) {
            $channel = Channel::STABLE->value;
        }
        $version = Version::parse(\APPLICATION_VERSION);
        $channel = \strtoupper($channel);

        $this->newerReleases = $this->downloader->getReleases(Channel::from($channel))
            ->filter(fn(Release $release): bool => $release->version->greaterThan($version))
            ->sort(fn(Release $a, Release $b): int => $a->version->greaterThan($b->version) ? 1 : -1);
    }

    public function hasUpgrade(): bool
    {
        return $this->newerReleases->count() > 0;
    }

    public function getNextUpgrade(): ?Release
    {
        return $this->newerReleases->first();
    }

    public function getLatestUpgrade(): ?Release
    {
        return $this->newerReleases->last();
    }
}
