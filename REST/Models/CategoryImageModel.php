<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class CategoryImageModel
 * @OA\Schema(
 *     title="Category image model",
 *     description="Category image model"
 * )
 * @property int    $kKategoriePict
 * @property int    $id
 * @property int    $kKategorie
 * @property int    $categoryID
 * @property string $cPfad
 * @property string $path
 * @property string $cType
 * @property string $type
 * @method string getPath()
 * @method void setPath(string $path)
 * @method int getId()
 * @method void setId(int $id)
 * @method int getCategoryID()
 * @method void setCategoryID(int $categoryID)
 */
final class CategoryImageModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   type="integer",
     *   example=1,
     *   description="The primary key"
     * )
     * @OA\Property(
     *   property="categoryID",
     *   type="integer",
     *   example=1,
     *   description="The category ID"
     * )
     * @OA\Property(
     *   property="path",
     *   type="string",
     *   example="testimage.jpg",
     *   description="The file name"
     * )
     * @OA\Property(
     *   property="type",
     *   type="string",
     *   example="",
     *   description="The type (unused)"
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tkategoriepict';
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

        if ($attributes === null) {
            $attributes               = [];
            $attributes['id']         = DataAttribute::create('kKategoriePict', 'int', null, false, true);
            $attributes['categoryID'] = DataAttribute::create('kKategorie', 'int');
            $attributes['path']       = DataAttribute::create('cPfad', 'varchar');
            $attributes['type']       = DataAttribute::create('cType', 'char');
        }

        return $attributes;
    }

    public function getNewID(): int
    {
        $max = $this->getDB()->getSingleInt(
            'SELECT MAX(kKategoriePict) AS newID FROM ' . $this->getTableName(),
            'newID'
        );

        return $max + 1;
    }
}
