<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class AttributeLocalizationModel
 * @OA\Schema(
 *     title="Attribute localization model",
 *     description="Attribute localization model",
 * )
 *
 * @property int    $id
 * @property int    $languageID
 * @property string $name
 * @property string $stringValue
 * @property string $textvalue
 */
final class AttributeLocalizationModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   type="integer",
     *   example=99,
     *   description="The primary key"
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
     *   example="example attribute",
     *   description="The localized attribute's name"
     * )
     * @OA\Property(
     *   property="stringValue",
     *   type="string",
     *   example="example",
     *   description="The localized string value"
     * )
     * @OA\Property(
     *   property="textValue",
     *   type="string",
     *   example="example",
     *   description="The localized text value"
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tattributsprache';
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
        $attributes                = [];
        $attributes['id']          = DataAttribute::create('kAttribut', 'int', self::cast('0', 'int'), false, true);
        $attributes['languageID']  = DataAttribute::create(
            'kSprache',
            'tinyint',
            self::cast('0', 'tinyint'),
            false,
            true
        );
        $attributes['name']        = DataAttribute::create('cName', 'varchar');
        $attributes['stringValue'] = DataAttribute::create('cStringWert', 'varchar', self::cast('', 'varchar'), false);
        $attributes['textValue']   = DataAttribute::create('cTextWert', 'mediumtext', null, false);

        return $attributes;
    }
}
