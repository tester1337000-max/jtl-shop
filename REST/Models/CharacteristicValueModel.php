<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use Illuminate\Support\Collection;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;
use JTL\Model\DataModelInterface;

/**
 * Class CharacteristicValueModel
 * @OA\Schema(
 *     title="Characteristic model",
 *     description="Characteristic model",
 * )
 * @property int    $kMerkmalWert
 * @property int    $id
 * @property int    $kMerkmal
 * @property int    $characteristicID
 * @property int    $nSort
 * @property int    $sort
 * @property string $cBildpfad
 * @property string $imagePath
 *
 * @method int getId()
 * @method int getCharacteristicID()
 * @method int getSort()
 * @method string getImagePath()
 */
final class CharacteristicValueModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   type="integer",
     *   example=25,
     *   description="The characteristic value ID"
     * )
     * @OA\Property(
     *   property="characteristicID",
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
     *   property="imagePath",
     *   type="string",
     *   example="example.jpg",
     *   description="The image file"
     * )
     * @OA\Property(
     *   property="localization",
     *   type="array",
     *   description="List of CharacteristicValueLocalizationModel objects",
     *   @OA\Items(ref="#/components/schemas/CharacteristicValueLocalizationModel")
     * )
     * @property Collection|CharacteristicValueLocalizationModel[] $localization
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tmerkmalwert';
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
                if (!isset($data['characteristicValueID'])) {
                    $data['characteristicValueID'] = $model->id;
                }
                try {
                    $loc = CharacteristicValueLocalizationModel::loadByAttributes(
                        $data,
                        $this->getDB(),
                        self::ON_NOTEXISTS_NEW
                    );
                } catch (Exception) {
                    continue;
                }
                $existing = $res->first(static function ($e) use ($loc): bool {
                    return $e->characteristicValueID === $loc->characteristicValueID
                        && $e->languageID === $loc->languageID;
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
        $this->registerSetter('image', function ($value, $model) {
            if ($value === null) {
                return null;
            }
            if (\is_a($value, Collection::class)) {
                return $value;
            }
            if (!\is_array($value)) {
                $value = [$value];
            }
            $res = $model->image ?? new Collection();
            foreach ($value as $data) {
                $data = (array)$data;
                if (!isset($data['characteristicValueID'])) {
                    $data['characteristicValueID'] = $model->id;
                }
                try {
                    $item = CharacteristicValueImageModel::loadByAttributes(
                        $data,
                        $this->getDB(),
                        self::ON_NOTEXISTS_NEW
                    );
                } catch (Exception) {
                    continue;
                }
                /** @var DataModelInterface|null $existing */
                $existing = $res->first(static function ($e) use ($item): bool {
                    return $e->characteristicValueID === $item->id;
                });
                if ($existing === null) {
                    $res->push($item);
                } else {
                    foreach ($item->getAttributes() as $attribute => $v) {
                        $existing->setAttribValue($attribute, $item->getAttribValue($attribute));
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
        $attributes                     = [];
        $attributes['id']               = DataAttribute::create('kMerkmalWert', 'int', null, false, true);
        $attributes['characteristicID'] = DataAttribute::create('kMerkmal', 'int');
        $attributes['sort']             = DataAttribute::create('nSort', 'int');
        $attributes['imagePath']        = DataAttribute::create('cBildpfad', 'varchar', '', false);

        $attributes['localization'] = DataAttribute::create(
            'localization',
            CharacteristicValueLocalizationModel::class,
            null,
            true,
            false,
            'kMerkmalWert'
        );

        $attributes['image'] = DataAttribute::create(
            'image',
            CharacteristicValueImageModel::class,
            null,
            true,
            false,
            'kMerkmalWert'
        );

        return $attributes;
    }
}
