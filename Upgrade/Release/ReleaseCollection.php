<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Release;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use JTL\Backend\Upgrade\Channel;

final class ReleaseCollection
{
    /**
     * @var Collection<int, Release>|null
     */
    private ?Collection $releases = null;

    public function __construct(
        private readonly ReleaseDB $releaseDB,
        private readonly ReleaseDownloader $releaseDownloader = new ReleaseDownloader(new Client()),
        private readonly JsonValidator $jsonValidator = new JsonValidator(),
    ) {
    }

    private function fetchReleaseDataFromAPI(): string
    {
        try {
            $res  = $this->releaseDownloader->download();
            $body = (string)$res->getBody();
        } catch (Exception $e) {
            throw new Exception(\__('Invalid response') . ': ' . $e->getMessage());
        }
        if ($this->jsonValidator->validate($body) === false) {
            throw new Exception(\__('Invalid release data'));
        }
        $this->releaseDB->saveReleaseData($body, $res->getStatusCode());

        return $body;
    }

    /**
     * @return Collection<int, Release>
     */
    private function fetchReleaseData(): Collection
    {
        try {
            $releases = $this->releaseDB->fetchReleaseDataFromDB() ?? $this->fetchReleaseDataFromAPI();
        } catch (Exception $e) {
            $releases = $this->releaseDB->fetchReleaseDataFromDB(false);
            if ($releases === null) {
                throw $e;
            }
        }
        $data = (array)\json_decode($releases, false, 512, \JSON_THROW_ON_ERROR);

        return \collect($data)->mapInto(Release::class)
            ->sort(fn(Release $a, Release $b): int => $a->version->greaterThan($b->version) ? 1 : -1);
    }

    /**
     * @return Collection<int, Release>
     */
    private function getReleaseCollection(): Collection
    {
        if ($this->releases === null) {
            $this->releases = $this->fetchReleaseData();
        }

        return $this->releases;
    }

    /**
     * @return Collection<int, Release>
     */
    public function getReleases(Channel $channel): Collection
    {
        return $this->getReleaseCollection()
            ->filter(fn(Release $item): bool => $item->channel === $channel);
    }

    public function getReleaseByID(int $id): Release
    {
        return $this->getReleaseCollection()->first(fn(Release $rls): bool => $rls->id === $id)
            ?? throw new InvalidArgumentException(\__('Release not found'));
    }

    public function getReleasyByVersionString(string $version): Release
    {
        return $this->getReleaseCollection()->first(fn(Release $rls): bool => (string)$rls->version === $version)
            ?? throw new InvalidArgumentException(\__('Release not found'));
    }
}
