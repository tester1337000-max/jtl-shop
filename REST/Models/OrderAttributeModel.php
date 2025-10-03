<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class OrderAttributeModel
 * @OA\Schema(
 *     title="Order attribute model",
 *     description="Order attribute model",
 * )
 * @package JTL\REST\Models
 * @property int    $kBestellattribut
 * @method int getKBestellattribut()
 * @method void setKBestellattribut(int $value)
 * @property int    $kBestellung
 * @method int getKBestellung()
 * @method void setKBestellung(int $value)
 * @property string $cName
 * @method string getCName()
 * @method void setCName(string $value)
 * @property string $cValue
 * @method string getCValue()
 * @method void setCValue(string $value)
 */
final class OrderAttributeModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   type="integer",
     *   example=1,
     *   description="The order attribute ID"
     * )
     * @OA\Property(
     *   property="orderID",
     *   type="integer",
     *   example=1,
     *   description="The order ID"
     * )
     * @OA\Property(
     *   property="name",
     *   type="string",
     *   example="example_attribute",
     *   description="The order attribute name"
     * )
     * @OA\Property(
     *   property="value",
     *   type="string",
     *   example="examplevalue",
     *   description="The order attribute value"
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tbestellattribut';
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
            $attributes            = [];
            $attributes['id']      = DataAttribute::create('kBestellattribut', 'int', null, false, true);
            $attributes['orderID'] = DataAttribute::create('kBestellung', 'int', null, false);
            $attributes['name']    = DataAttribute::create('cName', 'varchar', null, false);
            $attributes['value']   = DataAttribute::create('cValue', 'mediumtext');
        }

        return $attributes;
    }
}
