<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class CartItemAttributeModel
 *
 * @OA\Schema(
 *     title="Cart item attribute model",
 *     description="Cart item attribute model",
 * )
 * @package JTL\REST\Models
 * @property int    $kWarenkorbPosEigenschaft
 * @method int getKWarenkorbPosEigenschaft()
 * @method void setKWarenkorbPosEigenschaft(int $value)
 * @property int    $kWarenkorbPos
 * @method int getKWarenkorbPos()
 * @method void setKWarenkorbPos(int $value)
 * @property int    $kEigenschaft
 * @method int getKEigenschaft()
 * @method void setKEigenschaft(int $value)
 * @property int    $kEigenschaftWert
 * @method int getKEigenschaftWert()
 * @method void setKEigenschaftWert(int $value)
 * @property string $cEigenschaftName
 * @method string getCEigenschaftName()
 * @method void setCEigenschaftName(string $value)
 * @property string $cEigenschaftWertName
 * @method string getCEigenschaftWertName()
 * @method void setCEigenschaftWertName(string $value)
 * @property string $cFreifeldWert
 * @method string getCFreifeldWert()
 * @method void setCFreifeldWert(string $value)
 * @property float  $fAufpreis
 * @method float getFAufpreis()
 * @method void setFAufpreis(float $value)
 */
final class CartItemAttributeModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   title="id",
     *   format="int64",
     *   type="integer",
     *   example=22,
     *   description="The attribute's id"
     * )
     * @OA\Property(
     *   property="cartItemID",
     *   type="integer",
     *   example=2,
     *   description="The associated cart item's id"
     * )
     * @OA\Property(
     *   property="attributeID",
     *   type="integer",
     *   example=227,
     *   description="The attribute id"
     * )
     * @OA\Property(
     *   property="attributeValueID",
     *   type="integer",
     *   example=1638,
     *   description="The attribute value id"
     * )
     * @OA\Property(
     *   property="attributeName",
     *   type="string",
     *   example="Farbe",
     *   description="The item's name"
     * )
     * @OA\Property(
     *   property="attributeValueName",
     *   type="string",
     *   example="gelb",
     *   description="The attribute value's name"
     * )
     * @OA\Property(
     *   property="freeTextValue",
     *   type="string",
     *   example="gelb",
     *   description="Free text value"
     * )
     * @OA\Property(
     *   property="surcharge",
     *   type="number",
     *   format="float",
     *   example="0",
     *   description="Surcharge"
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'twarenkorbposeigenschaft';
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
        $attributes                       = [];
        $attributes['id']                 = DataAttribute::create(
            'kWarenkorbPosEigenschaft',
            'int',
            null,
            false,
            true
        );
        $attributes['cartItemID']         = DataAttribute::create(
            'kWarenkorbPos',
            'int',
            self::cast('0', 'int'),
            false
        );
        $attributes['attributeID']        = DataAttribute::create(
            'kEigenschaft',
            'int',
            self::cast('0', 'int'),
            false
        );
        $attributes['attributeValueID']   = DataAttribute::create(
            'kEigenschaftWert',
            'int',
            self::cast('0', 'int'),
            false
        );
        $attributes['attributeName']      = DataAttribute::create(
            'cEigenschaftName',
            'varchar',
            self::cast('', 'varchar'),
            false
        );
        $attributes['attributeValueName'] = DataAttribute::create(
            'cEigenschaftWertName',
            'varchar',
            self::cast('', 'varchar'),
            false
        );
        $attributes['freeTextValue']      = DataAttribute::create(
            'cFreifeldWert',
            'varchar',
            self::cast('', 'varchar'),
            false
        );
        $attributes['surcharge']          = DataAttribute::create('fAufpreis', 'double', self::cast('0', 'double'));

        return $attributes;
    }
}
