<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class CustomerGroupLocalizationModel
 * @OA\Schema(
 *     title="Customer group localization model",
 *     description="Customer group localization model",
 * )
 * @package JTL\REST\Models
 * @property int    $kKundengruppe
 * @property int    $customerGroupID
 * @method int getKKundengruppe()
 * @method void setKKundengruppe(int $value)
 * @property int    $kSprache
 * @property int    $languageID
 * @method int getKSprache()
 * @method void setKSprache(int $value)
 * @property string $cName
 * @property string $name
 * @method string getCName()
 * @method void setCName(string $value)
 */
final class CustomerGroupLocalizationModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="customerGroupID",
     *   type="integer",
     *   example=1,
     *   description="The customer group ID"
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
     *   example="Händler",
     *   description="The customer group's localized name"
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tkundengruppensprache';
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
        $attributes['customerGroupID'] = DataAttribute::create(
            'kKundengruppe',
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
