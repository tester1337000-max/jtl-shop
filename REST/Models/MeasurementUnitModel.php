<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use Illuminate\Support\Collection;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;
use JTL\Model\DataModelInterface;

/**
 * Class MeasurementUnitModel
 *
 * @OA\Schema(
 *     title="MeasurementUnitModel",
 *     description="MeasurementUnitModel",
 * )
 * @package JTL\REST\Models
 * @property int    $kMassEinheit
 * @method int getKMassEinheit()
 * @method void setKMassEinheit(int $value)
 * @property string $cCode
 * @method string getCCode()
 * @method void setCCode(string $value)
 */
final class MeasurementUnitModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   type="integer",
     *   example=33,
     *   description="The unit ID"
     * )
     * @OA\Property(
     *   property="code",
     *   type="string",
     *   example="Example unit-code",
     *   description="The unit code"
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tmasseinheit';
    }

    /**
     * Setting of keyname is not supported!
     * Call will always throw an Exception with code ERR_DATABASE!
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
        $attributes                 = [];
        $attributes['id']           = DataAttribute::create('kMassEinheit', 'int', null, false, true);
        $attributes['code']         = DataAttribute::create('cCode', 'varchar', null, false);
        $attributes['localization'] = DataAttribute::create(
            'localization',
            MeasurementUnitLocalizationModel::class,
            null,
            true,
            false,
            'kMassEinheit'
        );

        return $attributes;
    }

    protected function onRegisterHandlers(): void
    {
        $this->registerSetter('localization', function ($value, $model) {
            if (\is_a($value, Collection::class)) {
                return $value;
            }
            if (!\is_array($value)) {
                $value = [$value];
            }
            $res = $model->localization ?? new Collection();
            foreach (\array_filter($value) as $data) {
                $data = (array)$data;
                if (!isset($data['unitID'])) {
                    $data['unitID'] = $model->id;
                }
                try {
                    $loc = MeasurementUnitLocalizationModel::loadByAttributes(
                        $data,
                        $this->getDB(),
                        MeasurementUnitLocalizationModel::ON_NOTEXISTS_NEW
                    );
                } catch (Exception) {
                    continue;
                }
                /** @var DataModelInterface|null $existing */
                $existing = $res->first(static function ($e) use ($loc): bool {
                    return $e->unitID === $loc->unitID && $e->languageID === $loc->languageID;
                });
                if ($existing === null) {
                    $res->push($loc);
                } else {
                    foreach ($loc->getAttributes() as $attribute => $v) {
                        if (\array_key_exists($attribute, $data)) {
                            $existing->setAttribValue($attribute, $loc->getAttribValue($attribute));
                        }
                    }
                }
            }

            return $res;
        });
    }
}
