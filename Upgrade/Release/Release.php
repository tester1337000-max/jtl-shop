<?php

declare(strict_types=1);

namespace JTL\Backend\Upgrade\Release;

use DateTime;
use Exception;
use JTL\Backend\Upgrade\Channel;
use JTL\Plugin\Admin\Markdown;
use JTLShop\SemVer\Version;
use stdClass;

final class Release
{
    public int $id;

    public Channel $channel;

    public string $downloadURL;

    public string $checksum;

    public string $changelog;

    public Version $version;

    public bool $isNewer;

    public DateTime $date;

    public ?Version $phpMinVersion = null;

    public ?Version $phpMaxVersion = null;

    public ?Version $minShopVersion = null;

    public bool $phpVersionOK = true;

    public bool $shopVersionOK = true;

    public string $errorMsg = '';

    /**
     * @param object{id: int|string, channel: string, changelog: string, downloadUrl: string, sha1: string,
     *      last_modified: string, minShopVersion?: string, minPhpVersion?: string,
     *      maxPhpVersion?: string, reference: string}&stdClass $data
     */
    public function __construct(stdClass $data)
    {
        $md                = new Markdown();
        $this->channel     = Channel::from(\strtoupper($data->channel));
        $this->changelog   = $md->text($data->changelog ?? '');
        $this->id          = (int)$data->id;
        $this->downloadURL = $data->downloadUrl;
        $this->checksum    = $data->sha1;
        $this->date        = new DateTime($data->last_modified);
        $this->parseVersions($data);
        $this->checkPhpVersion();
    }

    /**
     * @param object{id: int|string, channel: string, changelog: string, downloadUrl: string, sha1: string,
     *      last_modified: string, minShopVersion?: string, minPhpVersion?: string,
     *      maxPhpVersion?: string, reference: string}&stdClass $data
     */
    private function parseVersions(stdClass $data): void
    {
        $currentShopVersion = Version::parse(\APPLICATION_VERSION);
        try {
            // the "v" prefix must not be present in originalVersion to avoid writing it to the db table tversion
            $this->version = Version::parse($data->reference);
            $this->version->setPrefix('');
            $this->version->setOriginalVersion((string)$this->version);
        } catch (Exception) {
            $this->version = Version::parse('0.0.0');
        }
        if (isset($data->minShopVersion)) {
            $this->minShopVersion = Version::parse($data->minShopVersion);
            if ($this->minShopVersion->greaterThan($currentShopVersion)) {
                $this->shopVersionOK = false;
                $this->errorMsg      = \__('Shop version too low');
            }
        }
        if (isset($data->minPhpVersion)) {
            $this->phpMinVersion = Version::parse($data->minPhpVersion);
        }
        if (isset($data->maxPhpVersion)) {
            $this->phpMaxVersion = Version::parse($data->maxPhpVersion);
        }
        $this->isNewer = $this->version->greaterThan($currentShopVersion);
    }

    private function checkPhpVersion(): void
    {
        $version = new Version();
        $version->setMajor(\PHP_MAJOR_VERSION);
        $version->setMinor(\PHP_MINOR_VERSION);
        if ($this->phpMaxVersion !== null && $version->greaterThan($this->phpMaxVersion)) {
            $this->phpVersionOK = false;
            $this->errorMsg     = \__('PHP version too high');
        } elseif ($this->phpMinVersion !== null && $version->smallerThan($this->phpMinVersion)) {
            $this->phpVersionOK = false;
            $this->errorMsg     = \__('PHP version too low');
        }
    }
}
