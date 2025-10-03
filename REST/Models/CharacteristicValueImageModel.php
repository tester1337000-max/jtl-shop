<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class CharacteristicValueImageModel
 * @OA\Schema(
 *     title="Characteristic value image model",
 *     description="Characteristic value image model",
 * )
 * @package JTL\REST\Models
 * @property int    $kMerkmalWert
 * @property int    $id
 * @property string $cBildpfad
 * @property string $path
 * @method string getPath()
 * @method void setPath(string $path)
 * @method int getId()
 * @method void setId(int $id)
 */
final class CharacteristicValueImageModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   type="integer",
     *   example=25,
     *   description="The characteristic value ID"
     * )
     * @OA\Property(
     *   property="path",
     *   type="string",
     *   example="blau.jpg",
     *   description="The image file name"
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tmerkmalwertbild';
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
        if ($attributes === null) {
            $attributes         = [];
            $attributes['id']   = DataAttribute::create('kMerkmalWert', 'int', null, false);
            $attributes['path'] = DataAttribute::create('cBildpfad', 'varchar', null, false);
        }

        return $attributes;
    }
}
