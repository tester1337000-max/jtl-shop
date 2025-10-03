<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class CategoryVisibilityModel
 * @OA\Schema(
 *     title="Category visibility model",
 *     description="Category visibility model",
 * )
 * @property int $categoryID
 * @property int $kKategorie
 * @property int $customerGroupID
 * @property int $kKundengruppe
 */
final class CategoryVisibilityModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="categoryID",
     *   type="integer",
     *   example=3,
     *   description="The category ID"
     * )
     * @OA\Property(
     *   property="customerGroupID",
     *   type="integer",
     *   example=1,
     *   description="The customer group ID"
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tkategoriesichtbarkeit';
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
        $attributes                    = [];
        $attributes['categoryID']      = DataAttribute::create(
            'kKategorie',
            'int',
            self::cast('0', 'int'),
            false,
            true
        );
        $attributes['customerGroupID'] = DataAttribute::create(
            'kKundengruppe',
            'int',
            self::cast('0', 'int'),
            false,
            true
        );

        return $attributes;
    }
}
