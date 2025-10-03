<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class ProductCharacteristicModel
 * @OA\Schema(
 *     title="Product characteristic model",
 *     description="Product characteristic model",
 * )
 * @property int $kMerkmal
 * @property int $id
 * @property int $kMerkmalWert
 * @property int $valueID
 * @property int $kArtikel
 * @property int $productID
 */
final class ProductCharacteristicModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   type="integer",
     *   example=7,
     *   description="The characteristic ID"
     * )
     * @OA\Property(
     *   property="valueID",
     *   type="integer",
     *   example=25,
     *   description="The characteristic value ID"
     * )
     * @OA\Property(
     *   property="productID",
     *   type="integer",
     *   example=99,
     *   description="The product ID"
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tartikelmerkmal';
    }

    /**
     * @inheritdoc
     */
    public function setKeyName($keyName): void
    {
        throw new Exception(__METHOD__ . ': setting of keyname is not supported', self::ERR_DATABASE);
    }

    /**
     * @inheritdoc
     */
    public function getAttributes(): array
    {
        static $attributes = null;

        if ($attributes !== null) {
            return $attributes;
        }
        $attributes              = [];
        $attributes['id']        = DataAttribute::create('kMerkmal', 'int');
        $attributes['valueID']   = DataAttribute::create('kMerkmalWert', 'int');
        $attributes['productID'] = DataAttribute::create('kArtikel', 'int');

//        $attributes['characteristics'] = DataAttribute::create(
//            'characteristics',
//            CharacteristicModel::class,
//            null,
//            true,
//            false,
//            'kMerkmal'
//        );

        return $attributes;
    }
}
