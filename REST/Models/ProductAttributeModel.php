<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class ProductAttributeModel
 *
 * @OA\Schema(
 *     title="Product attribute model",
 *     description="Product attribute model"
 * )
 * @property int    $kArtikelAttribut
 * @property int    $id
 * @property int    $kArtikel
 * @property int    $productID
 * @property string $cName
 * @property string $name
 * @property string $cWert
 * @property string $value
 */
final class ProductAttributeModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   type="integer",
     *   example=1,
     *   description="The primary key"
     * )
     * @OA\Property(
     *   property="productID",
     *   type="integer",
     *   example=99,
     *   description="The product ID"
     * )
     * @OA\Property(
     *   property="name",
     *   type="string",
     *   example="warenkorbmatrix",
     *   description="The attribute name"
     * )
     * @OA\Property(
     *   property="value",
     *   type="string",
     *   example="1",
     *   description="The attribute value"
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tartikelattribut';
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
        $attributes['id']        = DataAttribute::create(
            'kArtikelAttribut',
            'int',
            self::cast('0', 'int'),
            false,
            true
        );
        $attributes['productID'] = DataAttribute::create('kArtikel', 'int', self::cast('0', 'int'), false);
        $attributes['name']      = DataAttribute::create('cName', 'varchar');
        $attributes['value']     = DataAttribute::create('cWert', 'mediumtext');

        return $attributes;
    }
}
