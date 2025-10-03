<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class ProductPropertyCombinationValueModel
 * @OA\Schema(
 *     title="Product property combination value model",
 *     description="Product property combination value model",
 * )
 * @package JTL\REST\Models
 * @property int $kEigenschaftKombi
 * @method int getKEigenschaftKombi()
 * @method void setKEigenschaftKombi(int $value)
 * @property int $kEigenschaft
 * @method int getKEigenschaft()
 * @method void setKEigenschaft(int $value)
 * @property int $kEigenschaftWert
 * @method int getKEigenschaftWert()
 * @method void setKEigenschaftWert(int $value)
 */
final class ProductPropertyCombinationValueModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   type="integer",
     *   example=1,
     *   description="The primary key"
     * )
     * @OA\Property(
     *   property="propertyID",
     *   type="integer",
     *   example=1,
     *   description=""
     * )
     * @OA\Property(
     *   property="propertyValueID",
     *   type="integer",
     *   example=1,
     *   description=""
     * )
     * @OA\Property(
     *   property="values",
     *   type="array",
     *   description="List of ProductPropertyValueModel objects",
     *   @OA\Items(ref="#/components/schemas/ProductPropertyValueModel")
     * )
     * @OA\Property(
     *   property="image",
     *   type="array",
     *   description="List of ProductPropertyValueImage objects",
     *   @OA\Items(ref="#/components/schemas/ProductPropertyValueImage")
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'teigenschaftkombiwert';
    }

    /**
     * Setting of keyname is not supported!
     * Call will always throw an Exception with code ERR_DATABASE!
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
        $attributes                    = [];
        $attributes['id']              = DataAttribute::create('kEigenschaftKombi', 'int', null, false, true);
        $attributes['propertyID']      = DataAttribute::create('kEigenschaft', 'int', null, false, true);
        $attributes['propertyValueID'] = DataAttribute::create('kEigenschaftWert', 'int', null, false, true);

        $attributes['values'] = DataAttribute::create(
            'values',
            ProductPropertyValueModel::class,
            null,
            true,
            false,
            'kEigenschaftWert'
        );
        $attributes['image']  = DataAttribute::create(
            'image',
            ProductPropertyValueImage::class,
            null,
            true,
            false,
            'kEigenschaftWert'
        );

        return $attributes;
    }
}
