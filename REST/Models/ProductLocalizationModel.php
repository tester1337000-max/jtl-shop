<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class ProductLocalizationModel
 * @OA\Schema(
 *     title="Product localization model",
 *     description="Product localization model",
 * )
 * @property int    $kArtikel
 * @property int    $productID
 * @property int    $kSprache
 * @property int    $languageID
 * @property string $cSeo
 * @property string $slug
 * @property string $cName
 * @property string $name
 * @property string $cBeschreibung
 * @property string $description
 * @property string $cKurzBeschreibung
 * @property string $shortDescription
 * @method int getLanguageID()
 * @method int getProductID()
 */
final class ProductLocalizationModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="productID",
     *   type="integer",
     *   example=99,
     *   description="The product id"
     * )
     * @OA\Property(
     *   property="languageID",
     *   type="integer",
     *   example=1,
     *   description="The language ID"
     * )
     * @OA\Property(
     *   property="slug",
     *   type="string",
     *   example="example-product",
     *   description="The URL slug"
     * )
     * @OA\Property(
     *   property="name",
     *   type="string",
     *   example="Example product",
     *   description="The product's localized name"
     * )
     * @OA\Property(
     *   property="description",
     *   type="string",
     *   example="This is an example product",
     *   description="The product's localized description"
     * )
     * @OA\Property(
     *   property="shortDescription",
     *   type="string",
     *   example="This is an example product",
     *   description="The product's localized short description"
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tartikelsprache';
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
        $attributes                     = [];
        $attributes['productID']        = DataAttribute::create('kArtikel', 'int', self::cast('0', 'int'), false, true);
        $attributes['languageID']       = DataAttribute::create(
            'kSprache',
            'tinyint',
            self::cast('0', 'tinyint'),
            false,
            true
        );
        $attributes['slug']             = DataAttribute::create('cSeo', 'varchar', self::cast('', 'varchar'), false);
        $attributes['name']             = DataAttribute::create('cName', 'varchar');
        $attributes['description']      = DataAttribute::create('cBeschreibung', 'mediumtext');
        $attributes['shortDescription'] = DataAttribute::create('cKurzBeschreibung', 'mediumtext');

        return $attributes;
    }
}
