<?php

declare(strict_types=1);

namespace JTL\Cart;

use JTL\Helpers\GeneralObject;
use JTL\Shop;

/**
 * Class CartItemProperty
 * @package JTL\Cart
 */
class CartItemProperty
{
    public ?int $kWarenkorbPosEigenschaft = null;

    public ?int $kWarenkorbPos = null;

    public ?int $kEigenschaft = null;

    public ?int $kEigenschaftWert = null;

    /**
     * @var numeric-string|float|null
     */
    public string|null|float $fAufpreis = null;

    /**
     * @var numeric-string|float|null
     */
    public string|null|float $fGewichtsdifferenz = null;

    /**
     * @var string|array<string, string>|null
     */
    public string|array|null $cEigenschaftName = null;

    /**
     * @var string|array<string, string>|null
     */
    public string|array|null $cEigenschaftWertName = null;

    public ?string $cFreifeldWert = null;

    public ?string $cAufpreisLocalized = null;

    public ?string $cTyp = null;

    public function __construct(int $id = 0)
    {
        if ($id > 0) {
            $this->loadFromDB($id);
        }
    }

    public function gibEigenschaftName(): string
    {
        $obj = Shop::Container()->getDB()->select('teigenschaft', 'kEigenschaft', (int)$this->kEigenschaft);

        return $obj->cName ?? '';
    }

    public function gibEigenschaftWertName(): string
    {
        $obj = Shop::Container()->getDB()->select('teigenschaftwert', 'kEigenschaftWert', (int)$this->kEigenschaftWert);

        return $obj->cName ?? '';
    }

    public function loadFromDB(int $kWarenkorbPosEigenschaft): self
    {
        $obj = Shop::Container()->getDB()->select(
            'twarenkorbposeigenschaft',
            'kWarenkorbPosEigenschaft',
            $kWarenkorbPosEigenschaft
        );
        if ($obj !== null) {
            $this->kWarenkorbPosEigenschaft = (int)$obj->kWarenkorbPosEigenschaft;
            $this->kWarenkorbPos            = (int)$obj->kWarenkorbPos;
            $this->kEigenschaft             = (int)$obj->kEigenschaft;
            $this->kEigenschaftWert         = (int)$obj->kEigenschaftWert;
            $this->cEigenschaftName         = $obj->cEigenschaftName;
            $this->cEigenschaftWertName     = $obj->cEigenschaftWertName;
            $this->cFreifeldWert            = $obj->cFreifeldWert;
            $this->fAufpreis                = $obj->fAufpreis;
        }

        return $this;
    }

    public function insertInDB(): self
    {
        $obj = GeneralObject::copyMembers($this);
        unset($obj->kWarenkorbPosEigenschaft, $obj->cAufpreisLocalized, $obj->fGewichtsdifferenz, $obj->cTyp);
        // sql strict mode
        if ($obj->fAufpreis === null || $obj->fAufpreis === '') {
            $obj->fAufpreis = 0;
        }
        $this->kWarenkorbPosEigenschaft = Shop::Container()->getDB()->insert('twarenkorbposeigenschaft', $obj);

        return $this;
    }

    public function updateInDB(): int
    {
        $obj = GeneralObject::copyMembers($this);

        return Shop::Container()->getDB()->update(
            'twarenkorbposeigenschaft',
            'kWarenkorbPosEigenschaft',
            $obj->kWarenkorbPosEigenschaft,
            $obj
        );
    }
}
