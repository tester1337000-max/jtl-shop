<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class WarehouseModel
 * @OA\Schema(
 *     title="Warehouse model",
 *     description="Warehouse model",
 * )
 * @property int    $kWarenlager
 * @property string $cName
 * @property string $cKuerzel
 * @property string $cLagerTyp
 * @property string $cBeschreibung
 * @property string $cStrasse
 * @property string $cPLZ
 * @property string $cOrt
 * @property string $cLand
 * @property int    $nFulfillment
 * @property int    $nAktiv
 */
final class WarehouseModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   type="integer",
     *   example=55,
     *   description="The warehouse ID"
     * )
     * @OA\Property(
     *   property="name",
     *   type="string",
     *   example="Hückelhoven",
     *   description="The warehouse name"
     * )
     * @OA\Property(
     *   property="code",
     *   type="string",
     *   example="HHV",
     *   description="The warehouse code"
     * )
     * @OA\Property(
     *   property="type",
     *   type="string",
     *   example="",
     *   description="Not used"
     * )
     * @OA\Property(
     *   property="description",
     *   type="string",
     *   example="Lager in Hückelhoven",
     *   description="The warehouse description"
     * )
     * @OA\Property(
     *   property="street",
     *   type="string",
     *   example="Rheinstr. 7",
     *   description="The warehouse street and number"
     * )
     * @OA\Property(
     *   property="zip",
     *   type="string",
     *   example="41836",
     *   description="The warehouse zip code"
     * )
     * @OA\Property(
     *   property="city",
     *   type="string",
     *   example="Hückelhoven",
     *   description="The warehouse city"
     * )
     * @OA\Property(
     *   property="country",
     *   type="string",
     *   example="Deutschland",
     *   description="The warehouse country"
     * )
     * @OA\Property(
     *   property="fullfillment",
     *   type="integer",
     *   example=0,
     *   description="Is this a fullfillment warehouse?"
     * )
     * @OA\Property(
     *   property="active",
     *   type="integer",
     *   example=1,
     *   description="Is this active?"
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'twarenlager';
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
            $attributes                 = [];
            $attributes['id']           = DataAttribute::create('kWarenlager', 'int', null, false, true);
            $attributes['name']         = DataAttribute::create('cName', 'varchar');
            $attributes['code']         = DataAttribute::create('cKuerzel', 'varchar');
            $attributes['type']         = DataAttribute::create('cLagerTyp', 'varchar');
            $attributes['description']  = DataAttribute::create('cBeschreibung', 'varchar');
            $attributes['street']       = DataAttribute::create('cStrasse', 'varchar');
            $attributes['zip']          = DataAttribute::create('cPLZ', 'varchar');
            $attributes['city']         = DataAttribute::create('cOrt', 'varchar');
            $attributes['country']      = DataAttribute::create('cLand', 'varchar');
            $attributes['fullfillment'] = DataAttribute::create('nFulfillment', 'tinyint');
            $attributes['active']       = DataAttribute::create('nAktiv', 'tinyint', self::cast('0', 'tinyint'));
        }

        return $attributes;
    }
}
