<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class CharacteristicLocalizationModel
 * @OA\Schema(
 *     title="Characteristic localization model",
 *     description="Characteristic localization model",
 * )
 * @property int    $kMerkmal
 * @property int    $characteristicID
 * @property int    $kSprache
 * @property int    $languageID
 * @property string $cName
 * @property string $name
 */
final class CharacteristicLocalizationModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="characteristicID",
     *   type="integer",
     *   example=7,
     *   description="The characteristic ID"
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
     *   example="Farbe",
     *   description="The characteristic's name"
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tmerkmalsprache';
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
        $attributes['characteristicID'] = DataAttribute::create('kMerkmal', 'int', self::cast('0', 'int'), false, true);
        $attributes['languageID']       = DataAttribute::create('kSprache', 'int', self::cast('0', 'int'), false, true);
        $attributes['name']             = DataAttribute::create('cName', 'varchar');

        return $attributes;
    }
}
