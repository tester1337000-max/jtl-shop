<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class ManufacturerLocalizationModel
 * @OA\Schema(
 *     title="Manufacturer localization model",
 *     description="Manufacturer localization model"
 * )
 * @property int    $manufacturerID
 * @property int    $kHersteller
 * @property int    $languageID
 * @property int    $kSprache
 * @property string $metaTitle
 * @property string $cMetaTitle
 * @property string $metaKeywords
 * @property string $cMetaKeywords
 * @property string $metaDescription
 * @property string $cMetaDescription
 * @property string $description
 * @property string $cBeschreibung
 * @method int getLanguageID()
 * @method int getManufacturerID()
 */
final class ManufacturerLocalizationModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   title="id",
     *   type="integer",
     *   example=33,
     *   description="The manufcaturer ID"
     * )
     * @OA\Property(
     *   property="languageID",
     *   title="languageID",
     *   type="integer",
     *   example=1,
     *   description="The language ID"
     * )
     * @OA\Property(
     *   property="metaTitle",
     *   title="metaTitle",
     *   type="string",
     *   example="Example title for example manufacturer",
     *   description="The meta description"
     * )
     * @OA\Property(
     *   property="metaKeywords",
     *   title="metaKeywords",
     *   type="string",
     *   example="example,keywords,for,this,manufacturer",
     *   description="The meta keywords"
     * )
     * @OA\Property(
     *   property="metaDescription",
     *   title="metaDescription",
     *   type="string",
     *   example="Example manufacturer meta description",
     *   description="The meta description"
     * )
     * @OA\Property(
     *   property="description",
     *   title="description",
     *   type="string",
     *   example="Example manufacturer description",
     *   description="The description"
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'therstellersprache';
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
        $attributes['manufacturerID']  = DataAttribute::create('kHersteller', 'int', null, false, true);
        $attributes['languageID']      = DataAttribute::create('kSprache', 'int', null, false, true);
        $attributes['metaTitle']       = DataAttribute::create(
            'cMetaTitle',
            'mediumtext',
            self::cast('', 'varchar'),
            false
        );
        $attributes['metaKeywords']    = DataAttribute::create(
            'cMetaKeywords',
            'mediumtext',
            self::cast('', 'varchar'),
            false
        );
        $attributes['metaDescription'] = DataAttribute::create(
            'cMetaDescription',
            'mediumtext',
            self::cast('', 'varchar'),
            false
        );
        $attributes['description']     = DataAttribute::create(
            'cBeschreibung',
            'mediumtext',
            self::cast('', 'varchar'),
            false
        );

        return $attributes;
    }
}
