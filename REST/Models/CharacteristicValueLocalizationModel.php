<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class CharacteristicValueLocalizationModel
 * @OA\Schema(
 *     title="Characteristic value localization model",
 *     description="Characteristic value localization model",
 * )
 * @property int    $kMerkmalWert
 * @property int    $characteristicValueID
 * @property int    $kSprache
 * @property int    $languageID
 * @property string $cWert
 * @property string $value
 * @property string $cSeo
 * @property string $slug
 * @property string $cMetaTitle
 * @property string $metaTitle
 * @property string $cMetaKeywords
 * @property string $metaKeywords
 * @property string $cMetaDescription
 * @property string $metaDescription
 * @property string $cBeschreibung
 * @property string $description
 */
final class CharacteristicValueLocalizationModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="characteristicValueID",
     *   type="integer",
     *   example=25,
     *   description="The characteristic value ID"
     * )
     * @OA\Property(
     *   property="languageID",
     *   type="integer",
     *   example=1,
     *   description="The language ID"
     * )
     * @OA\Property(
     *   property="value",
     *   type="string",
     *   example="Gelb",
     *   description="The value"
     * )
     * @OA\Property(
     *   property="slug",
     *   type="string",
     *   example="gelb",
     *   description="The URL slug"
     * )
     * @OA\Property(
     *   property="metaTitle",
     *   type="string",
     *   example="Example title for example characteristic",
     *   description="The meta description"
     * )
     * @OA\Property(
     *   property="metaKeywords",
     *   type="string",
     *   example="example,keywords,for,this,characteristic,value",
     *   description="The meta keywords"
     * )
     * @OA\Property(
     *   property="metaDescription",
     *   type="string",
     *   example="Example category meta description",
     *   description="The meta description"
     * )
     * @OA\Property(
     *   property="description",
     *   type="string",
     *   example="Example description",
     *   description="The description"
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tmerkmalwertsprache';
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
        $attributes                          = [];
        $attributes['characteristicValueID'] = DataAttribute::create(
            'kMerkmalWert',
            'int',
            self::cast('0', 'int'),
            false,
            true
        );
        $attributes['languageID']            = DataAttribute::create(
            'kSprache',
            'int',
            self::cast('0', 'int'),
            false,
            true
        );
        $attributes['value']                 = DataAttribute::create('cWert', 'varchar');
        $attributes['slug']                  = DataAttribute::create('cSeo', 'varchar', '', false);
        $attributes['metaTitle']             = DataAttribute::create('cMetaTitle', 'varchar', '', false);
        $attributes['metaKeywords']          = DataAttribute::create('cMetaKeywords', 'varchar', '', false);
        $attributes['metaDescription']       = DataAttribute::create('cMetaDescription', 'mediumtext', '', false);
        $attributes['description']           = DataAttribute::create('cBeschreibung', 'mediumtext', '', false);

        return $attributes;
    }
}
