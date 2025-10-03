<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use Illuminate\Support\Collection;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;
use JTL\Model\DataModelInterface;

/**
 * Class CharacteristicModel
 * @OA\Schema(
 *     title="Characteristic model",
 *     description="Characteristic model",
 * )
 * @property int                                                                                $kMerkmal
 * @property int                                                                                $id
 * @property int                                                                                $nSort
 * @property int                                                                                $sort
 * @property string                                                                             $cName
 * @property string                                                                             $name
 * @property string                                                                             $cBildpfad
 * @property string                                                                             $image
 * @property string                                                                             $cTyp
 * @property string                                                                             $type
 * @property int                                                                                $nMehrfachauswahl
 * @property int                                                                                $isMulti
 * @property Collection<int, CharacteristicValueModel>|CharacteristicValueModel[]               $value
 * @property Collection<int, CharacteristicLocalizationModel>|CharacteristicLocalizationModel[] $localization
 */
final class CharacteristicModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   type="integer",
     *   example=7,
     *   description="The characteristic ID"
     * )
     * @OA\Property(
     *   property="sort",
     *   type="integer",
     *   example=0,
     *   description="The sort number"
     * )
     * @OA\Property(
     *   property="name",
     *   type="string",
     *   example="Farbe",
     *   description="The characteristic's name"
     * )
     * @OA\Property(
     *   property="image",
     *   type="string",
     *   example="example.jpg",
     *   description="The image name"
     * )
     * @OA\Property(
     *   property="type",
     *   type="string",
     *   example="",
     *   description="The type (not used)"
     * )
     * @OA\Property(
     *   property="isMulti",
     *   type="integer",
     *   example=0,
     *   description="Set to 1 if multiple selections should be allowed"
     * )
     * @OA\Property(
     *   property="value",
     *   type="array",
     *   description="List of CharacteristicValueModel objects",
     *   @OA\Items(ref="#/components/schemas/CharacteristicValueModel")
     * )
     * @OA\Property(
     *   property="localization",
     *   type="array",
     *   description="List of CharacteristicLocalizationModel objects",
     *   @OA\Items(ref="#/components/schemas/CharacteristicLocalizationModel")
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tmerkmal';
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
    protected function onRegisterHandlers(): void
    {
        parent::onRegisterHandlers();
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
                if (!isset($data['characteristicID'])) {
                    $data['characteristicID'] = $model->id;
                }
                try {
                    $loc = CharacteristicLocalizationModel::loadByAttributes(
                        $data,
                        $this->getDB(),
                        self::ON_NOTEXISTS_NEW
                    );
                } catch (Exception) {
                    continue;
                }
                $existing = $res->first(static function ($e) use ($loc): bool {
                    return $e->characteristicID === $loc->characteristicID && $e->languageID === $loc->languageID;
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
        $this->registerSetter('value', function ($value, $model) {
            if ($value === null) {
                return null;
            }
            if (\is_a($value, Collection::class)) {
                return $value;
            }
            if (!\is_array($value)) {
                $value = [$value];
            }
            $res = $model->value ?? new Collection();
            foreach ($value as $data) {
                $data = (array)$data;
                if (!isset($data['characteristicID'])) {
                    $data['characteristicID'] = $model->id;
                }
                try {
                    $loc = CharacteristicValueModel::loadByAttributes(
                        $data,
                        $this->getDB(),
                        self::ON_NOTEXISTS_NEW
                    );
                } catch (Exception) {
                    continue;
                }
                $existing = $res->first(static function ($e) use ($loc): bool {
                    return $e->characteristicID === $loc->characteristicID;
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
     * @throws Exception
     */
    public function getAttributes(): array
    {
        static $attributes = null;

        if ($attributes !== null) {
            return $attributes;
        }
        $attributes            = [];
        $attributes['id']      = DataAttribute::create('kMerkmal', 'int', null, false, true);
        $attributes['sort']    = DataAttribute::create('nSort', 'int');
        $attributes['name']    = DataAttribute::create('cName', 'varchar');
        $attributes['image']   = DataAttribute::create('cBildpfad', 'varchar', '', false);
        $attributes['type']    = DataAttribute::create('cTyp', 'varchar', null, false);
        $attributes['isMulti'] = DataAttribute::create(
            'nMehrfachauswahl',
            'tinyint',
            self::cast('0', 'tinyint'),
            false
        );

        $attributes['value']        = DataAttribute::create(
            'value',
            CharacteristicValueModel::class,
            null,
            true,
            false,
            'kMerkmal'
        );
        $attributes['localization'] = DataAttribute::create(
            'localization',
            CharacteristicLocalizationModel::class,
            null,
            true,
            false,
            'kMerkmal'
        );

        return $attributes;
    }
}
