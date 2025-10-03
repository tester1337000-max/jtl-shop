<?php

declare(strict_types=1);

namespace JTL\Filter\States;

use JTL\Catalog\Product\MerkmalWert;
use JTL\Filter\AbstractFilter;
use JTL\Filter\FilterInterface;
use JTL\Filter\Join;
use JTL\Filter\ProductFilter;
use JTL\MagicCompatibilityTrait;

/**
 * Class BaseCharacteristic
 * @package JTL\Filter\States
 */
class BaseCharacteristic extends AbstractFilter
{
    use MagicCompatibilityTrait;

    /**
     * @var array<string, string>
     */
    public static array $mapping = [
        'kMerkmal'     => 'CharacteristicIDCompat',
        'kMerkmalWert' => 'ValueCompat',
        'cName'        => 'Name'
    ];

    public function __construct(ProductFilter $productFilter)
    {
        parent::__construct($productFilter);
        $this->setIsCustom(false)
            ->setUrlParam(\QUERY_PARAM_CHARACTERISTIC_VALUE);
    }

    /**
     * sets "kMerkmalWert"
     *
     * @inheritdoc
     */
    public function setValue($value): FilterInterface
    {
        $this->value = (int)$value;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setSeo(array $languages): FilterInterface
    {
        $currentLanguageID   = $this->getLanguageID();
        $characteristicValue = new MerkmalWert((int)$this->getValue(), $currentLanguageID);
        if ($characteristicValue->getID() === 0) {
            $this->fail();
        }
        foreach ($languages as $language) {
            $id              = $language->getId();
            $this->cSeo[$id] = \ltrim($characteristicValue->getURLPath($id) ?? '', '/');
        }
        if (($value = $characteristicValue->getValue()) !== null && \mb_strlen($value) > 0) {
            $this->setName($characteristicValue->getCharacteristicName() . ': ' . $value);
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getRoute(array $additional): ?string
    {
        $valueID             = (int)$this->getValue();
        $currentLanguageID   = $this->getLanguageID();
        $characteristicValue = new MerkmalWert($valueID, $currentLanguageID);
        $characteristicValue->createBySlug($valueID, $additional);

        return \ltrim($characteristicValue->getURLPath($currentLanguageID) ?? '', '/');
    }

    /**
     * @inheritdoc
     */
    public function getPrimaryKeyRow(): string
    {
        return 'kMerkmalWert';
    }

    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tmerkmalwert';
    }

    /**
     * @inheritdoc
     */
    public function getSQLJoin(): Join
    {
        return (new Join())
            ->setType('JOIN')
            ->setTable(
                '(SELECT DISTINCT kArtikel
                      FROM tartikelmerkmal
                      WHERE kMerkmalWert = ' . (int)$this->getValue() . '
                      ) AS tmerkmaljoin'
            )
            ->setOrigin(__CLASS__)
            ->setOn('tmerkmaljoin.kArtikel = tartikel.kArtikel');
    }
}
