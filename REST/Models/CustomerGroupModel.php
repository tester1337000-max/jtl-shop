<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use Illuminate\Support\Collection;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;
use JTL\Model\DataModelInterface;

/**
 * Class CustomerGroupModel
 * @OA\Schema(
 *     title="Customer group model",
 *     description="Customer group model",
 * )
 * @package JTL\REST\Models
 * @property int    $kKundengruppe
 * @method int getKKundengruppe()
 * @method void setKKundengruppe(int $value)
 * @property string $cName
 * @method string getCName()
 * @method void setCName(string $value)
 * @property float  $fRabatt
 * @method float getFRabatt()
 * @method void setFRabatt(float $value)
 * @property string $cStandard
 * @method string getCStandard()
 * @method void setCStandard(string $value)
 * @property string $cShopLogin
 * @method string getCShopLogin()
 * @method void setCShopLogin(string $value)
 * @property int    $nNettoPreise
 * @method int getNNettoPreise()
 * @method void setNNettoPreise(int $value)
 */
final class CustomerGroupModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   type="integer",
     *   example=1,
     *   description="The customer group ID"
     * )
     * @OA\Property(
     *   property="name",
     *   type="string",
     *   example="Händler",
     *   description="The customer group name"
     * )
     * @OA\Property(
     *   property="discount",
     *   type="number",
     *   format="float",
     *   example=0,
     *   description="The customer group discount"
     * )
     * @OA\Property(
     *   property="default",
     *   type="string",
     *   example="N",
     *   description="Is this the default group?"
     * )
     * @OA\Property(
     *   property="shopLogin",
     *   type="string",
     *   example="N",
     *   description="???"
     * )
     * @OA\Property(
     *   property="net",
     *   type="integer",
     *   example=0,
     *   description="Show net prices?"
     * )
     * @OA\Property(
     *   property="localization",
     *   type="array",
     *   description="List of CustomerGroupLocalizationModel objects",
     *   @OA\Items(ref="#/components/schemas/CustomerGroupLocalizationModel")
     * )
     * @OA\Property(
     *   property="attributes",
     *   type="array",
     *   description="List of CustomerGroupAttributeModel objects",
     *   @OA\Items(ref="#/components/schemas/CustomerGroupAttributeModel")
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tkundengruppe';
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
                if (!isset($data['customerGroupID'])) {
                    $data['customerGroupID'] = $model->id;
                }
                try {
                    $loc = CustomerGroupLocalizationModel::loadByAttributes(
                        $data,
                        $this->getDB(),
                        CustomerGroupLocalizationModel::ON_NOTEXISTS_NEW
                    );
                } catch (Exception) {
                    continue;
                }
                /** @var DataModelInterface|null $existing */
                $existing = $res->first(static function ($e) use ($loc): bool {
                    return $e->customerGroupID === $loc->customerGroupID && $e->languageID === $loc->languageID;
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

    /**
     * @inheritdoc
     */
    public function getAttributes(): array
    {
        static $attributes = null;
        if ($attributes !== null) {
            return $attributes;
        }
        $attributes              = [];
        $attributes['id']        = DataAttribute::create('kKundengruppe', 'int', self::cast('0', 'int'), false, true);
        $attributes['name']      = DataAttribute::create('cName', 'varchar');
        $attributes['discount']  = DataAttribute::create('fRabatt', 'double');
        $attributes['default']   = DataAttribute::create('cStandard', 'char', self::cast('N', 'char'));
        $attributes['shopLogin'] = DataAttribute::create('cShopLogin', 'char', self::cast('N', 'char'), false);
        $attributes['net']       = DataAttribute::create('nNettoPreise', 'tinyint', self::cast('0', 'tinyint'), false);

        $attributes['localization'] = DataAttribute::create(
            'localization',
            CustomerGroupLocalizationModel::class,
            null,
            true,
            false,
            'kKundengruppe'
        );
        $attributes['attributes']   = DataAttribute::create(
            'attributes',
            CustomerGroupAttributeModel::class,
            null,
            true,
            false,
            'kKundengruppe'
        );

        return $attributes;
    }
}
