<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class ProductImageModel
 * @OA\Schema(
 *     title="Product image model",
 *     description="Product image model",
 * )
 * @property int    $kArtikelPict
 * @property int    $id
 * @property int    $kMainArtikelBild
 * @property int    $mainImageID
 * @property int    $kArtikel
 * @property int    $productID
 * @property int    $kBild
 * @property int    $imageID
 * @property string $cPfad
 * @property string $path
 * @property int    $nNr
 * @property int    $imageNo
 * @method string getPath()
 * @method void setPath(string $value)
 * @method int getProductID()
 * @method void setProductID(int $value)
 * @method int getId()
 * @method void setId(int $value)
 * @method int getImageID()
 * @method void setImageID(int $value)
 * @method int getImageNo()
 * @method void setImageNo(int $value)
 */
final class ProductImageModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   type="integer",
     *   example=99,
     *   description="The primary key"
     * )
     * @OA\Property(
     *   property="mainImageID",
     *   type="integer",
     *   example=0,
     *   description="The main image ID"
     * )
     * @OA\Property(
     *   property="productID",
     *   type="integer",
     *   example=99,
     *   description="The product's ID"
     * )
     * @OA\Property(
     *   property="imageID",
     *   type="integer",
     *   example=0,
     *   description="The image ID"
     * )
     * @OA\Property(
     *   property="path",
     *   type="string",
     *   example="exampleproduct.jpg",
     *   description="The image path"
     * )
     * @OA\Property(
     *   property="imageNo",
     *   type="integer",
     *   example=1,
     *   description="The image number"
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tartikelpict';
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
        $attributes                = [];
        $attributes['id']          = DataAttribute::create('kArtikelPict', 'int', self::cast('0', 'int'), false, true);
        $attributes['mainImageID'] = DataAttribute::create('kMainArtikelBild', 'int', self::cast('0', 'int'), false);
        $attributes['productID']   = DataAttribute::create('kArtikel', 'int', self::cast('0', 'int'), false);
        $attributes['imageID']     = DataAttribute::create('kBild', 'int', self::cast('0', 'int'), false);
        $attributes['path']        = DataAttribute::create('cPfad', 'varchar');
        $attributes['imageNo']     = DataAttribute::create('nNr', 'tinyint');

        return $attributes;
    }

    public function getNewID(): int
    {
        $max = $this->getDB()->getSingleInt(
            'SELECT MAX(kArtikelPict) AS newID FROM ' . $this->getTableName(),
            'newID'
        );

        return $max + 1;
    }

    public function getNewImageID(): int
    {
        $max = $this->getDB()->getSingleInt(
            'SELECT MAX(kBild) AS newID FROM ' . $this->getTableName(),
            'newID'
        );

        return $max + 1;
    }
}
