<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class TaxClassModel
 * @OA\Schema(
 *     title="Tax class model",
 *     description="Tax class model",
 * )
 * @property int    $kSteuerklasse
 * @property int    $id
 * @property string $cName
 * @property string $name
 * @property string $cStandard
 * @property string $isDefault
 */
final class TaxClassModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   type="integer",
     *   example=1,
     *   description="The tax class ID"
     * )
     * @OA\Property(
     *   property="name",
     *   type="string",
     *   example="ermäßigter Steuersatz",
     *   description="The class name"
     * )
     * @OA\Property(
     *   property="isDefault",
     *   type="string",
     *   example="N",
     *   description=""
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tsteuerklasse';
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
        $attributes              = [];
        $attributes['id']        = DataAttribute::create('kSteuerklasse', 'int', self::cast('0', 'int'), false, true);
        $attributes['name']      = DataAttribute::create('cName', 'varchar');
        $attributes['isDefault'] = DataAttribute::create('cStandard', 'yesno', self::cast('N', 'yesno'));

        return $attributes;
    }
}
