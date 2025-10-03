<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class ProductCategoryDiscountModel
 * @OA\Schema(
 *     title="Product category discount model",
 *     description="Product category discount model"
 * )
 * @package JTL\REST\Models
 * @property int   $kArtikel
 * @property int   $productID
 * @method int getKArtikel()
 * @method void setKArtikel(int $value)
 * @property int   $kKundengruppe
 * @property int   $customerGroupID
 * @method int getKKundengruppe()
 * @method void setKKundengruppe(int $value)
 * @property int   $kKategorie
 * @property int   $categoryID
 * @method int getKKategorie()
 * @method void setKKategorie(int $value)
 * @property float $fRabatt
 * @property float $discount
 * @method float getFRabatt()
 * @method void setFRabatt(float $value)
 */
final class ProductCategoryDiscountModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="productID",
     *   type="integer",
     *   example=99,
     *   description="The product ID"
     * )
     * @OA\Property(
     *   property="customerGroupID",
     *   type="integer",
     *   example=1,
     *   description="The customer group ID"
     * )
     * @OA\Property(
     *   property="categoryID",
     *   type="integer",
     *   example=3,
     *   description="The category ID"
     * )
     * @OA\Property(
     *   property="discount",
     *   type="number",
     *   format="float",
     *   example="3.50",
     *   description="The discount"
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tartikelkategorierabatt';
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
            $attributes['productID']       = DataAttribute::create('kArtikel', 'int', null, false, true);
            $attributes['customerGroupID'] = DataAttribute::create('kKundengruppe', 'int', null, false, true);
            $attributes['categoryID']      = DataAttribute::create('kKategorie', 'int', null, false);
            $attributes['discount']        = DataAttribute::create('fRabatt', 'double', null, false);
        }

        return $attributes;
    }
}
