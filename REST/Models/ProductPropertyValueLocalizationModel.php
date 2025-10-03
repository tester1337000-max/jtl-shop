<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class ProductPropertyValueLocalizationModel
 * @OA\Schema(
 *     title="Product property value localization model",
 *     description="Product property value localization model",
 * )
 * @package JTL\REST\Models
 * @property int    $kEigenschaftWert
 * @method int getKEigenschaftWert()
 * @method void setKEigenschaftWert(int $value)
 * @property int    $kSprache
 * @method int getKSprache()
 * @method void setKSprache(int $value)
 * @property string $cName
 * @method string getCName()
 * @method void setCName(string $value)
 */
final class ProductPropertyValueLocalizationModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="propertyValueID",
     *   type="integer",
     *   example=1,
     *   description="The property value ID"
     * )
     * @OA\Property(
     *   property="languageID",
     *   type="integer",
     *   example=1,
     *   description="The language ID"
     * )
     * @OA\Property(
     *   property="name",
     *   type="string",
     *   example="",
     *   description="The name"
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'teigenschaftwertsprache';
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
        $attributes['propertyValueID'] = DataAttribute::create(
            'kEigenschaftWert',
            'int',
            self::cast('0', 'int'),
            false,
            true
        );
        $attributes['languageID']      = DataAttribute::create('kSprache', 'int', self::cast('0', 'int'), false, true);
        $attributes['name']            = DataAttribute::create('cName', 'varchar');

        return $attributes;
    }
}
