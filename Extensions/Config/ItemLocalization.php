<?php

declare(strict_types=1);

namespace JTL\Extensions\Config;

use JTL\Language\LanguageHelper;
use JTL\Nice;
use JTL\Shop;

/**
 * Class ItemLocalization
 * @package JTL\Extensions\Config
 */
class ItemLocalization
{
    protected ?int $kKonfigitem = null;

    protected ?int $kSprache = null;

    protected string $cName = '';

    protected string $cBeschreibung = '';

    public function __construct(int $itemID = 0, int $languageID = 0)
    {
        if ($itemID > 0 && $languageID > 0) {
            $this->loadFromDB($itemID, $languageID);
        }
    }

    public static function checkLicense(): bool
    {
        return Nice::getInstance()->checkErweiterung(\SHOP_ERWEITERUNG_KONFIGURATOR);
    }

    private function loadFromDB(int $itemID = 0, int $languageID = 0): void
    {
        if (!self::checkLicense()) {
            return;
        }
        $item            = Shop::Container()->getDB()->select(
            'tkonfigitemsprache',
            'kKonfigitem',
            $itemID,
            'kSprache',
            $languageID
        );
        $defaultLanguage = LanguageHelper::getDefaultLanguage();
        if ($item !== null && empty($item->cName)) {
            $localized   = Shop::Container()->getDB()->select(
                'tkonfigitemsprache',
                'kKonfigitem',
                $itemID,
                'kSprache',
                $defaultLanguage->getId(),
                null,
                null,
                false,
                'cName'
            );
            $item->cName = $localized->cName ?? '';
        }
        if ($item !== null && empty($item->cBeschreibung)) {
            $localized           = Shop::Container()->getDB()->select(
                'tkonfigitemsprache',
                'kKonfigitem',
                $itemID,
                'kSprache',
                $defaultLanguage->getId(),
                null,
                null,
                false,
                'cBeschreibung'
            );
            $item->cBeschreibung = $localized->cBeschreibung ?? '';
        }

        if (isset($item->kKonfigitem, $item->kSprache) && $item->kKonfigitem > 0 && $item->kSprache > 0) {
            $this->cName         = $item->cName;
            $this->cBeschreibung = $item->cBeschreibung;
            $this->kKonfigitem   = (int)$item->kKonfigitem;
            $this->kSprache      = (int)$item->kSprache;
        }
    }

    public function setKonfigitem(int $kKonfigitem): self
    {
        $this->kKonfigitem = $kKonfigitem;

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

    public function setBeschreibung(string $cBeschreibung): self
    {
        $this->cBeschreibung = $cBeschreibung;

        return $this;
    }

    public function getKonfigitem(): int
    {
        return $this->kKonfigitem ?? 0;
    }

    public function getSprache(): int
    {
        return $this->kSprache ?? 0;
    }

    public function getName(): string
    {
        return $this->cName;
    }

    public function getBeschreibung(): string
    {
        return $this->cBeschreibung;
    }
}
