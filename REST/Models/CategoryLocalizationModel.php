<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class CategoryLocalizationModel
 * @OA\Schema(
 *     title="Category localization model",
 *     description="Category localization model",
 * )
 * @property int    $kKategorie
 * @property int    $categoryID
 * @property int    $kSprache
 * @property int    $languageID
 * @property string $cSeo
 * @property string $slug
 * @property string $cName
 * @property string $name
 * @property string $cBeschreibung
 * @property string $description
 * @property string $cMetaDescription
 * @property string $metaDescription
 * @property string $cMetaKeywords
 * @property string $metaKeywords
 * @property string $cTitleTag
 * @property string $metaTitle
 * @method int getId()
 * @method int getLanguageID()
 * @method string getSlug()
 */
final class CategoryLocalizationModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="categoryID",
     *   type="integer",
     *   example=1,
     *   description="The category ID"
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
     *   example="example-category",
     *   description="The URL slug"
     * )
     * @OA\Property(
     *   property="name",
     *   type="string",
     *   example="Example Category",
     *   description="The category's name"
     * )
     * @OA\Property(
     *   property="description",
     *   type="string",
     *   example="This is an example category",
     *   description="The description"
     * )
     * @OA\Property(
     *   property="metaDescription",
     *   type="string",
     *   example="Example category meta description",
     *   description="The meta description"
     * )
     * @OA\Property(
     *   property="metaKeywords",
     *   type="string",
     *   example="example,keywords,for,this,category",
     *   description="The meta keywords"
     * )
     * @OA\Property(
     *   property="metaTitle",
     *   type="string",
     *   example="Example title for example category",
     *   description="The meta description"
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tkategoriesprache';
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
        $attributes['languageID']      = DataAttribute::create(
            'kSprache',
            'tinyint',
            self::cast('0', 'tinyint'),
            false,
            true
        );
        $attributes['slug']            = DataAttribute::create('cSeo', 'varchar', self::cast('', 'varchar'), false);
        $attributes['name']            = DataAttribute::create('cName', 'varchar');
        $attributes['description']     = DataAttribute::create('cBeschreibung', 'mediumtext');
        $attributes['metaDescription'] = DataAttribute::create('cMetaDescription', 'varchar');
        $attributes['metaKeywords']    = DataAttribute::create('cMetaKeywords', 'varchar');
        $attributes['metaTitle']       = DataAttribute::create('cTitleTag', 'varchar');

        return $attributes;
    }
}
