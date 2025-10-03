<?php

declare(strict_types=1);

namespace JTL\Extensions\Download;

use JTL\Nice;
use JTL\Shop;

/**
 * Class Localization
 * @package JTL\Extensions\Download
 */
class Localization
{
    protected ?int $kDownload = null;

    protected ?int $kSprache = null;

    protected ?string $cName = null;

    protected ?string $cBeschreibung = null;

    public function __construct(int $downloadID = 0, int $languageID = 0)
    {
        if ($downloadID > 0 && $languageID > 0) {
            $this->loadFromDB($downloadID, $languageID);
        }
    }

    public static function checkLicense(): bool
    {
        return Nice::getInstance()->checkErweiterung(\SHOP_ERWEITERUNG_DOWNLOADS);
    }

    private function loadFromDB(int $downloadID, int $languageID): void
    {
        $localized = Shop::Container()->getDB()->select(
            'tdownloadsprache',
            'kDownload',
            $downloadID,
            'kSprache',
            $languageID
        );
        if ($localized !== null && $localized->kDownload > 0) {
            $this->kSprache      = (int)$localized->kSprache;
            $this->kDownload     = (int)$localized->kDownload;
            $this->cName         = $localized->cName;
            $this->cBeschreibung = $localized->cBeschreibung;
        }
    }

    public function setDownload(int $downloadID): self
    {
        $this->kDownload = $downloadID;

        return $this;
    }

    public function setSprache(int $languageID): self
    {
        $this->kSprache = $languageID;

        return $this;
    }

    public function setName(string $name): self
    {
        $this->cName = $name;

        return $this;
    }

    public function setBeschreibung(string $decription): self
    {
        $this->cBeschreibung = $decription;

        return $this;
    }

    public function getDownload(): int
    {
        return $this->kDownload ?? 0;
    }

    public function getSprache(): int
    {
        return $this->kSprache ?? 0;
    }

    public function getName(): ?string
    {
        return $this->cName;
    }

    public function getBeschreibung(): ?string
    {
        return $this->cBeschreibung;
    }
}
