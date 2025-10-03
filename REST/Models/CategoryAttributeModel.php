<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use Illuminate\Support\Collection;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class CategoryAttributeModel
 * @OA\Schema(
 *     title="Category attribute model",
 *     description="Category attribute model"
 * )
 * @property int    $kKategorieAttribut
 * @property int    $id
 * @property int    $kKategorie
 * @property int    $categoryID
 * @property string $cName
 * @property string $name
 * @property string $cWert
 * @property string $value
 * @property int    $nSort
 * @property int    $sort
 * @property int    $bIstFunktionsAttribut
 * @property int    $function
 *
 * @property Collection<int, CategoryAttributeLocalizationModel>|CategoryAttributeLocalizationModel[] $localization
 */
final class CategoryAttributeModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   type="integer",
     *   example="1",
     *   description="The primary key"
     * )
     * @OA\Property(
     *   property="categoryID",
     *   type="integer",
     *   example="1",
     *   description="The category ID"
     * )
     * @OA\Property(
     *   property="name",
     *   type="string",
     *   example="show_on_frontpage",
     *   description="The attribute's name"
     * )
     * @OA\Property(
     *   property="value",
     *   type="string",
     *   example="1",
     *   description="The attribute's value"
     * )
     * @OA\Property(
     *   property="sort",
     *   type="integer",
     *   example=0,
     *   description="The sort number"
     * )
     * @OA\Property(
     *   property="function",
     *   type="integer",
     *   example=0,
     *   description="Is functional attribute?"
     * )
     * @OA\Property(
     *   property="localization",
     *   type="array",
     *   description="List of CategoryAttributeLocalizationModel objects",
     *   @OA\Items(ref="#/components/schemas/CategoryAttributeLocalizationModel")
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tkategorieattribut';
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
        $attributes               = [];
        $attributes['id']         = DataAttribute::create(
            'kKategorieAttribut',
            'int',
            self::cast('0', 'int'),
            false,
            true
        );
        $attributes['categoryID'] = DataAttribute::create('kKategorie', 'int', self::cast('0', 'int'), false);
        $attributes['name']       = DataAttribute::create('cName', 'varchar');
        $attributes['value']      = DataAttribute::create('cWert', 'mediumtext');
        $attributes['sort']       = DataAttribute::create('nSort', 'int', self::cast('0', 'int'), false);
        $attributes['function']   = DataAttribute::create(
            'bIstFunktionsAttribut',
            'int',
            self::cast('1', 'int'),
            false
        );

        $attributes['localization'] = DataAttribute::create(
            'localization',
            CategoryAttributeLocalizationModel::class,
            null,
            true,
            false,
            'kKategorieAttribut',
            'kAttribut'
        );

        return $attributes;
    }
}
