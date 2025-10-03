<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class TaxRateModel
 * @OA\Schema(
 *     title="Tax rate model",
 *     description="Tax rate model",
 * )
 * @property int   $kSteuersatz
 * @property int   $id
 * @property int   $kSteuerzone
 * @property int   $zoneID
 * @property int   $kSteuerklasse
 * @property int   $taxClassID
 * @property float $fSteuersatz
 * @property float $rate
 * @property int   $nPrio
 * @property int   $priority
 */
final class TaxRateModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   type="integer",
     *   example=1,
     *   description="The rate ID"
     * )
     * @OA\Property(
     *   property="zoneID",
     *   type="integer",
     *   example=1,
     *   description="The zone ID"
     * )
     * @OA\Property(
     *   property="taxClassID",
     *   type="integer",
     *   example=1,
     *   description="The tax class ID"
     * )
     * @OA\Property(
     *   property="rate",
     *   type="number",
     *   format="float",
     *   example=7.0,
     *   description="The tax rate in percent"
     * )
     * @OA\Property(
     *   property="priority",
     *   type="integer",
     *   example=1,
     *   description="The priority"
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tsteuersatz';
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
        $attributes               = [];
        $attributes['id']         = DataAttribute::create('kSteuersatz', 'int', self::cast('0', 'int'), false, true);
        $attributes['zoneID']     = DataAttribute::create('kSteuerzone', 'int');
        $attributes['taxClassID'] = DataAttribute::create('kSteuerklasse', 'int');
        $attributes['rate']       = DataAttribute::create('fSteuersatz', 'double');
        $attributes['priority']   = DataAttribute::create('nPrio', 'tinyint');

        return $attributes;
    }
}
