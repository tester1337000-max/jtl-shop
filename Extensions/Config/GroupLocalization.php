<?php

declare(strict_types=1);

namespace JTL\Extensions\Config;

use JsonSerializable;
use JTL\Helpers\Text;
use JTL\Shop;

/**
 * Class GroupLocalization
 * @package JTL\Extensions\Config
 */
class GroupLocalization implements JsonSerializable
{
    protected ?int $kKonfiggruppe = null;

    protected ?int $kSprache = null;

    protected ?string $cName = null;

    protected ?string $cBeschreibung = null;

    public function __construct(int $groupID = 0, int $languageID = 0)
    {
        if ($groupID > 0 && $languageID > 0) {
            $this->loadFromDB($groupID, $languageID);
        }
    }

    /**
     * @return array<string, string|null>
     */
    public function jsonSerialize(): array
    {
        return Text::utf8_convert_recursive([
            'cName'         => $this->cName,
            'cBeschreibung' => $this->cBeschreibung
        ]);
    }

    private function loadFromDB(int $groupID = 0, int $languageID = 0): void
    {
        $item = Shop::Container()->getDB()->select(
            'tkonfiggruppesprache',
            'kKonfiggruppe',
            $groupID,
            'kSprache',
            $languageID
        );
        if ($item !== null && $item->kKonfiggruppe > 0 && $item->kSprache > 0) {
            $this->kSprache      = (int)$item->kSprache;
            $this->kKonfiggruppe = (int)$item->kKonfiggruppe;
            $this->cName         = $item->cName;
            $this->cBeschreibung = $item->cBeschreibung;
        }
    }

    public function setKonfiggruppe(int $groupID): self
    {
        $this->kKonfiggruppe = $groupID;

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

    public function setBeschreibung(string $description): self
    {
        $this->cBeschreibung = $description;

        return $this;
    }

    public function getKonfiggruppe(): int
    {
        return $this->kKonfiggruppe ?? 0;
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

    public function hatBeschreibung(): bool
    {
        return \mb_strlen($this->cBeschreibung ?? '') > 0;
    }
}
