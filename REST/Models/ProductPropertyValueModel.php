<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class ProductPropertyValueModel
 * @OA\Schema(
 *     title="Product property value model",
 *     description="Product property value model",
 * )
 * @package JTL\REST\Models
 * @property int    $kEigenschaftWert
 * @method int getKEigenschaftWert()
 * @method void setKEigenschaftWert(int $value)
 * @property int    $kEigenschaft
 * @method int getKEigenschaft()
 * @method void setKEigenschaft(int $value)
 * @property string $cName
 * @method string getCName()
 * @method void setCName(string $value)
 * @property float  $fAufpreisNetto
 * @method float getFAufpreisNetto()
 * @method void setFAufpreisNetto(float $value)
 * @property float  $fGewichtDiff
 * @method float getFGewichtDiff()
 * @method void setFGewichtDiff(float $value)
 * @property string $cArtNr
 * @method string getCArtNr()
 * @method void setCArtNr(string $value)
 * @property int    $nSort
 * @method int getNSort()
 * @method void setNSort(int $value)
 * @property float  $fLagerbestand
 * @method float getFLagerbestand()
 * @method void setFLagerbestand(float $value)
 * @property float  $fPackeinheit
 * @method float getFPackeinheit()
 * @method void setFPackeinheit(float $value)
 */
final class ProductPropertyValueModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="propertyValueID",
     *   type="integer",
     *   example=1,
     *   description="The property value ID"
     * )
     * @OA\Property(
     *   property="propertyID",
     *   type="integer",
     *   example=1,
     *   description="The property ID"
     * )
     * @OA\Property(
     *   property="name",
     *   type="string",
     *   example="Example property",
     *   description="The property name"
     * )
     * @OA\Property(
     *   property="surchargeNet",
     *   type="number",
     *   format="float",
     *   example="1.234",
     *   description="The surcharge"
     * )
     * @OA\Property(
     *   property="weightDiff",
     *   type="number",
     *   format="float",
     *   example="-1.234",
     *   description="The weight difference"
     * )
     * @OA\Property(
     *   property="sku",
     *   type="string",
     *   example="123-example",
     *   description="The SKU"
     * )
     * @OA\Property(
     *   property="sort",
     *   type="integer",
     *   example=0,
     *   description="The sort number"
     * )
     * @OA\Property(
     *   property="stock",
     *   type="number",
     *   format="float",
     *   example="0",
     *   description="The current stock"
     * )
     * @OA\Property(
     *   property="packagingUnit",
     *   type="number",
     *   format="float",
     *   example="0",
     *   description="The packaging unit"
     * )
     * @OA\Property(
     *   property="localization",
     *   type="array",
     *   description="List of ProductPropertyValueLocalizationModel objects",
     *   @OA\Items(ref="#/components/schemas/ProductPropertyValueLocalizationModel")
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'teigenschaftwert';
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
        $attributes['propertyValueID'] = DataAttribute::create(
            'kEigenschaftWert',
            'int',
            self::cast('0', 'int'),
            false,
            true
        );
        $attributes['propertyID']      = DataAttribute::create('kEigenschaft', 'int');
        $attributes['name']            = DataAttribute::create('cName', 'varchar');
        $attributes['surchargeNet']    = DataAttribute::create(
            'fAufpreisNetto',
            'double',
            self::cast('0.0000', 'double'),
            false
        );
        $attributes['weightDiff']      = DataAttribute::create('fGewichtDiff', 'double');
        $attributes['sku']             = DataAttribute::create('cArtNr', 'varchar');
        $attributes['sort']            = DataAttribute::create('nSort', 'int', self::cast('0', 'int'));
        $attributes['stock']           = DataAttribute::create('fLagerbestand', 'double');
        $attributes['packagingUnit']   = DataAttribute::create(
            'fPackeinheit',
            'double',
            self::cast('1.0000', 'double')
        );

        $attributes['localization'] = DataAttribute::create(
            'localization',
            ProductPropertyValueLocalizationModel::class,
            null,
            true,
            false,
            'kEigenschaftWert'
        );

        return $attributes;
    }
}
