<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class ProductVisibilityModel
 * @OA\Schema(
 *     title="Product visibility model",
 *     description="Product visibility model. Product IDs listed here are NOT visible to given customer group ID",
 * )
 * @property int $kArtikel
 * @property int $productID
 * @property int $kKundengruppe
 * @property int $customerGroupID
 */
final class ProductVisibilityModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="productID",
     *   type="integer",
     *   example=99,
     *   description="The product's ID"
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
        return 'tartikelsichtbarkeit';
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
        $attributes['productID']       = DataAttribute::create(
            'kArtikel',
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
