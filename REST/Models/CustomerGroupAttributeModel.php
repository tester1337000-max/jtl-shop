<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class CustomerGroupAttributeModel
 * @OA\Schema(
 *     title="Customer group attribute model",
 *     description="Customer group attribute model",
 * )
 * @package JTL\REST\Models
 * @property int    $kKundengruppenAttribut
 * @method int getKKundengruppenAttribut()
 * @method void setKKundengruppenAttribut(int $value)
 * @property int    $kKundengruppe
 * @method int getKKundengruppe()
 * @method void setKKundengruppe(int $value)
 * @property string $cName
 * @method string getCName()
 * @method void setCName(string $value)
 * @property string $cWert
 * @method string getCWert()
 * @method void setCWert(string $value)
 */
final class CustomerGroupAttributeModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   type="integer",
     *   example=1,
     *   description="The primary key"
     * )
     * @OA\Property(
     *   property="customerGroupID",
     *   type="integer",
     *   example=1,
     *   description="The customer group ID"
     * )
     * @OA\Property(
     *   property="name",
     *   type="string",
     *   example="example_attribute",
     *   description="The attribute name"
     * )
     * @OA\Property(
     *   property="value",
     *   type="string",
     *   example="example_value",
     *   description="The attribute value"
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tkundengruppenattribut';
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
        $attributes['id']              = DataAttribute::create(
            'kKundengruppenAttribut',
            'int',
            self::cast('0', 'int'),
            false,
            true
        );
        $attributes['customerGroupID'] = DataAttribute::create('kKundengruppe', 'int');
        $attributes['name']            = DataAttribute::create('cName', 'varchar');
        $attributes['value']           = DataAttribute::create('cWert', 'varchar');

        return $attributes;
    }
}
