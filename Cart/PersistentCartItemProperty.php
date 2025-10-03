<?php

declare(strict_types=1);

namespace JTL\Cart;

use JTL\Helpers\GeneralObject;
use JTL\Shop;

/**
 * Class PersistentCartItemProperty
 * @package JTL\Cart
 */
class PersistentCartItemProperty
{
    public int $kWarenkorbPersPosEigenschaft;

    public int $kWarenkorbPersPos;

    public int $kEigenschaft;

    public int $kEigenschaftWert;

    public ?string $cFreifeldWert;

    public ?string $cEigenschaftName;

    public ?string $cEigenschaftWertName;

    public function __construct(
        int $propertyID,
        int $propertyValueID,
        ?string $freeText,
        ?string $propertyName,
        ?string $propertyValueName,
        int $kWarenkorbPersPos
    ) {
        $this->kWarenkorbPersPos    = $kWarenkorbPersPos;
        $this->kEigenschaft         = $propertyID;
        $this->kEigenschaftWert     = $propertyValueID;
        $this->cFreifeldWert        = $freeText;
        $this->cEigenschaftName     = $propertyName;
        $this->cEigenschaftWertName = $propertyValueName;
    }

    public function schreibeDB(): self
    {
        $obj = GeneralObject::copyMembers($this);
        unset($obj->kWarenkorbPersPosEigenschaft);
        $this->kWarenkorbPersPosEigenschaft = Shop::Container()->getDB()->insert('twarenkorbpersposeigenschaft', $obj);

        return $this;
    }
}
