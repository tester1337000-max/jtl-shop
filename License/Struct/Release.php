<?php

declare(strict_types=1);

namespace JTL\License\Struct;

use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use JTLShop\SemVer\Version;
use stdClass;

/**
 * Class Release
 * @package JTL\License
 */
class Release
{
    public const PHP_VERSION_OK = 0;

    public const PHP_VERSION_LOW = -1;

    public const PHP_VERSION_HIGH = 1;

    public const TYPE_SECURITY = 'security';

    public const TYPE_FEATURE = 'feature';

    public const TYPE_BUGFIX = 'bugfix';

    private Version $version;

    private string $type;

    private DateTime $releaseDate;

    private string $shortDescription;

    private string $downloadUrl;

    /**
     * @var string - sha1 checksum
     */
    private string $checksum;

    private ?Version $phpMinVersion = null;

    private ?Version $phpMaxVersion = null;

    /**
     * @var self::PHP_VERSION_*
     */
    private int $phpVersionOK = self::PHP_VERSION_OK;

    private bool $includesSecurityFixes = false;

    public function __construct(?stdClass $json = null)
    {
        if ($json !== null) {
            $this->fromJSON($json);
        }
    }

    public function fromJSON(stdClass $json): void
    {
        $this->setVersion(Version::parse($json->version));
        $this->setType($json->type);
        $this->setReleaseDate($json->release_date);
        $this->setShortDescription($json->short_description);
        $this->setDownloadURL($json->download_url);
        $this->setChecksum($json->checksum ?? '');
        $this->setIncludesSecurityFixes($json->includes_security_fixes ?? false);
        if (isset($json->min_php_version)) {
            $this->setPhpMinVersion(Version::parse($json->min_php_version));
        }
        if (isset($json->max_php_version)) {
            $this->setPhpMaxVersion(Version::parse($json->max_php_version));
        }
        $this->checkPhpVersion();
    }

    private function checkPhpVersion(): void
    {
        $version = new Version();
        $version->setMajor(\PHP_MAJOR_VERSION);
        $version->setMinor(\PHP_MINOR_VERSION);
        if ($this->getPhpMaxVersion() !== null && $version->greaterThan($this->getPhpMaxVersion())) {
            $this->setPhpVersionOK(self::PHP_VERSION_HIGH);
        } elseif ($this->getPhpMinVersion() !== null && $version->smallerThan($this->getPhpMinVersion())) {
            $this->setPhpVersionOK(self::PHP_VERSION_LOW);
        }
    }

    public function getVersion(): Version
    {
        return $this->version;
    }

    public function setVersion(Version $version): void
    {
        $this->version = $version;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getReleaseDate(): DateTime
    {
        return $this->releaseDate;
    }

    public function setReleaseDate(DateTime|string $releaseDate): void
    {
        $this->releaseDate = \is_string($releaseDate)
            ? Carbon::createFromTimeString($releaseDate, 'UTC')
                ->toDateTime()
                ->setTimezone(new DateTimeZone(\SHOP_TIMEZONE))
            : $releaseDate;
    }

    public function getShortDescription(): string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(string $shortDescription): void
    {
        $this->shortDescription = $shortDescription;
    }

    public function getDownloadURL(): ?string
    {
        return $this->downloadUrl;
    }

    public function setDownloadURL(string $downloadURL): void
    {
        $this->downloadUrl = $downloadURL;
    }

    public function getChecksum(): string
    {
        return $this->checksum;
    }

    public function setChecksum(string $checksum): void
    {
        $this->checksum = $checksum;
    }

    public function includesSecurityFixes(): bool
    {
        return $this->includesSecurityFixes;
    }

    public function setIncludesSecurityFixes(bool $includesSecurityFixes): void
    {
        $this->includesSecurityFixes = $includesSecurityFixes;
    }

    public function getPhpMinVersion(): ?Version
    {
        return $this->phpMinVersion;
    }

    public function setPhpMinVersion(?Version $phpMinVersion): void
    {
        $this->phpMinVersion = $phpMinVersion;
    }

    public function getPhpMaxVersion(): ?Version
    {
        return $this->phpMaxVersion;
    }

    public function setPhpMaxVersion(?Version $phpMaxVersion): void
    {
        $this->phpMaxVersion = $phpMaxVersion;
    }

    public function getPhpVersionOK(): int
    {
        return $this->phpVersionOK;
    }

    /**
     * @param self::PHP_VERSION_* $phpVersionOK
     */
    public function setPhpVersionOK(int $phpVersionOK): void
    {
        $this->phpVersionOK = $phpVersionOK;
    }

    public function isPhpVersionTooLow(): bool
    {
        return $this->getPhpVersionOK() === self::PHP_VERSION_LOW;
    }

    public function isPhpVersionTooHigh(): bool
    {
        return $this->getPhpVersionOK() === self::PHP_VERSION_HIGH;
    }
}
