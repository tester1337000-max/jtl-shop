<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use Illuminate\Support\Collection;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;
use JTL\Model\DataModelInterface;

/**
 * Class AttributeModel
 *
 * this actually also is a ProductAttributeModel - but the table name is "attribut" for some reason
 * it would be JTL\Catalog\Product\Artikel::AttributeAssoc
 * @OA\Schema(
 *     title="Attribute model",
 *     description="Attribute model",
 * )
 *
 * @property int    $id
 * @property int    $kAttribut
 * @property int    $kArtikel
 * @property int    $productID
 * @property int    $nSort
 * @property int    $sort
 * @property string $cName
 * @property string $stringValue
 * @property string $cStringWert
 * @property string $cTextWert
 * @property string $textValue
 */
final class AttributeModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   type="integer",
     *   example=99,
     *   description="The primary key"
     * )
     * @OA\Property(
     *   property="productID",
     *   type="integer",
     *   example=99,
     *   description="The product's ID"
     * )
     * @OA\Property(
     *   property="sort",
     *   type="integer",
     *   example=0,
     *   description="The sorting number"
     * )
     * @OA\Property(
     *   property="name",
     *   type="string",
     *   example="example",
     *   description="The attribute's name"
     * )
     * @OA\Property(
     *   property="stringValue",
     *   type="string",
     *   example="example",
     *   description="The string value"
     * )
     * @OA\Property(
     *   property="textValue",
     *   type="string",
     *   example="example",
     *   description="The text value"
     * )
     * @OA\Property(
     *   property="localization",
     *   type="array",
     *   description="List of AttributeLocalizationModel objects",
     *   @OA\Items(ref="#/components/schemas/AttributeLocalizationModel")
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tattribut';
    }

    /**
     * @inheritdoc
     */
    public function setKeyName($keyName): void
    {
        throw new Exception(__METHOD__ . ': setting of keyname is not supported', self::ERR_DATABASE);
    }

    protected function onRegisterHandlers(): void
    {
        $this->registerSetter('localization', function ($value, $model) {
            if ($value === null) {
                return null;
            }
            if (\is_a($value, Collection::class)) {
                return $value;
            }
            if (!\is_array($value)) {
                $value = [$value];
            }
            $res = $model->localization ?? new Collection();
            foreach ($value as $data) {
                $data = (array)$data;
                if (!isset($data['id'])) {
                    $data['id'] = $model->id;
                }
                try {
                    $loc = AttributeLocalizationModel::loadByAttributes($data, $this->getDB(), self::ON_NOTEXISTS_NEW);
                } catch (Exception) {
                    continue;
                }
                $existing = $res->first(static function ($e) use ($loc): bool {
                    return $e->id === $loc->id && $e->languageID === $loc->languageID;
                });
                /** @var DataModelInterface|null $existing */
                if ($existing === null) {
                    $res->push($loc);
                } else {
                    foreach ($loc->getAttributes() as $attribute => $v) {
                        $existing->setAttribValue($attribute, $loc->getAttribValue($attribute));
                    }
                }
            }

            return $res;
        });
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
        $attributes['productID']   = DataAttribute::create('kArtikel', 'int');
        $attributes['sort']        = DataAttribute::create('nSort', 'int', null, false);
        $attributes['name']        = DataAttribute::create('cName', 'varchar');
        $attributes['stringValue'] = DataAttribute::create('cStringWert', 'varchar', self::cast('', 'varchar'), false);
        $attributes['textValue']   = DataAttribute::create('cTextWert', 'mediumtext', null, false);

        $attributes['localization'] = DataAttribute::create(
            'localization',
            AttributeLocalizationModel::class,
            null,
            true,
            false,
            'kAttribut'
        );

        return $attributes;
    }
}
