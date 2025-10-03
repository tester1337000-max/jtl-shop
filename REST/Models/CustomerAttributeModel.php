<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class CustomerAttributeModel
 * @OA\Schema(
 *     title="Customer attribute model",
 *     description="Customer attribute model"
 * )
 * @package JTL\REST\Models
 * @property int    $kKundenAttribut
 * @method int getKKundenAttribut()
 * @method void setKKundenAttribut(int $value)
 * @property int    $kKunde
 * @method int getKKunde()
 * @method void setKKunde(int $value)
 * @property int    $kKundenfeld
 * @method int getKKundenfeld()
 * @method void setKKundenfeld(int $value)
 * @property string $cName
 * @method string getCName()
 * @method void setCName(string $value)
 * @property string $cWert
 * @method string getCWert()
 * @method void setCWert(string $value)
 */
final class CustomerAttributeModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   type="integer",
     *   example=75,
     *   description="The primary key"
     * )
     * @OA\Property(
     *   property="customerID",
     *   type="integer",
     *   example=33,
     *   description="The customer ID"
     * )
     * @OA\Property(
     *   property="customerFieldID",
     *   type="integer",
     *   example=45,
     *   description="The customer field ID"
     * )
     * @OA\Property(
     *   property="name",
     *   type="string",
     *   example="example_field",
     *   description=""
     * )
     * @OA\Property(
     *   property="value",
     *   type="string",
     *   example="example_value",
     *   description=""
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tkundenattribut';
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
            $attributes                    = [];
            $attributes['id']              = DataAttribute::create('kKundenAttribut', 'int', null, false, true);
            $attributes['customerID']      = DataAttribute::create('kKunde', 'int');
            $attributes['customerFieldID'] = DataAttribute::create('kKundenfeld', 'int', null, false);
            $attributes['name']            = DataAttribute::create('cName', 'varchar');
            $attributes['value']           = DataAttribute::create('cWert', 'varchar');
        }

        return $attributes;
    }
}
