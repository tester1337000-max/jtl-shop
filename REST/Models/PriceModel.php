<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use Illuminate\Support\Collection;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;
use JTL\Model\DataModelInterface;

/**
 * Class PriceModel
 * @OA\Schema(
 *     title="Tax rate model",
 *     description="Tax rate model",
 * )
 * @property int $kPreis
 * @property int $id
 * @property int $kArtikel
 * @property int $productID
 * @property int $kKundengruppe
 * @property int $customerGroupID
 * @property int $kKunde
 * @property int $customerID
 */
final class PriceModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   type="integer",
     *   example=1,
     *   description="The price ID"
     * )
     * @OA\Property(
     *   property="productID",
     *   type="integer",
     *   example=1,
     *   description="The product ID"
     * )
     * @OA\Property(
     *   property="customerGroupID",
     *   type="integer",
     *   example=1,
     *   description="The customer group ID"
     * )
     * @OA\Property(
     *   property="customerID",
     *   type="integer",
     *   example=0,
     *   description="The customer ID"
     * )
     * @OA\Property(
     *   property="priority",
     *   type="integer",
     *   example=1,
     *   description="The priority"
     * )
     * @OA\Property(
     *   property="detail",
     *   type="array",
     *   description="List of PriceDetailModel objects",
     *   @OA\Items(ref="#/components/schemas/PriceDetailModel")
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tpreis';
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
    public function onRegisterHandlers(): void
    {
        parent::onRegisterHandlers();

        // update price detail IDs after creating auto increment kPreis for this model
        $this->registerSetter('kPreis', static function ($value, $model) {
            if ($value === null) {
                return null;
            }
            if ($model->kPreis === 0 && $model->detail instanceof Collection && $model->detail->count() > 0) {
                foreach ($model->detail as $price) {
                    $price->kPreis = $value;
                }
            }
            return $value;
        });

        $this->registerSetter('2detail', function ($value, $model) {
            if (\is_a($value, Collection::class)) {
                return $value;
            }
            if (!\is_array($value)) {
                $value = [$value];
            }
            $res = $model->detail ?? new Collection();
            if (isset($value['netPrice'])) {
                return $this->updateSingleDetailItem($res, $value, $model);
            }
            foreach ($value as $data) {
                $data = (array)$data;
                if (!isset($data['kPreis'])) {
                    $data['kPreis'] = $model->kPreis;
                }
                $res = $this->updateSingleDetailItem($res, $value, $model);
            }

            return $res;
        });

        $this->registerSetter('detail', function ($value, $model) {
            if (\is_a($value, Collection::class)) {
                return $value;
            }
            if (!\is_array($value)) {
                $value = [$value];
            }
            $res = $model->detail ?? new Collection();
            foreach (\array_filter($value) as $data) {
                $data = (array)$data;
                if (!isset($data['priceID'])) {
                    $data['priceID'] = $model->id;
                }
                try {
                    $price = PriceDetailModel::loadByAttributes($data, $this->getDB(), self::ON_NOTEXISTS_NEW);
                } catch (Exception) {
                    continue;
                }
                /** @var DataModelInterface|null $existing */
                $existing = $res->first(static function ($e) use ($price): bool {
                    return $e->kPreis > 0
                        && $price->kPreis > 0
                        && $e->kPreis === $price->kPreis
                        && $e->kPreisDetail === $price->kPreisDetail;
                });
                if ($existing === null) {
                    $res->push($price);
                } else {
                    foreach ($price->getAttributes() as $attribute => $v) {
                        $existing->setAttribValue($attribute, $price->getAttribValue($attribute));
                    }
                }
            }

            return $res;
        });
    }

    /**
     * @param Collection<int, PriceDetailModel> $collection
     * @param array<string, mixed>              $value
     * @param PriceModel                        $model
     * @return Collection<int, PriceDetailModel>
     * @throws Exception
     */
    public function updateSingleDetailItem(Collection $collection, array $value, PriceModel $model): Collection
    {
        if (!isset($value['kPreis'])) {
            $value['kPreis'] = $model->kPreis ?? 0;
        }
        $detail = PriceDetailModel::loadByAttributes(
            $value,
            $this->getDB(),
            ProductLocalizationModel::ON_NOTEXISTS_NEW
        );
        /** @var DataModelInterface|null $existing */
        $existing = $collection->first(static fn($e): bool => $e->id === $detail->id && $e->kPreis === $detail->kPreis);
        if ($existing === null) {
            $collection->push($detail);
        } else {
            foreach ($detail->getAttributes() as $attribute => $v) {
                if (\array_key_exists($attribute, $value)) {
                    $existing->setAttribValue($attribute, $detail->getAttribValue($attribute));
                }
            }
        }

        return $collection;
    }

    public function getKeyName(bool $realName = false): string
    {
        return $realName ? 'kPreis' : 'id';
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
        $attributes['id']              = DataAttribute::create('kPreis', 'int', null, false, true);
        $attributes['productID']       = DataAttribute::create('kArtikel', 'int', null, false);
        $attributes['customerGroupID'] = DataAttribute::create('kKundengruppe', 'int', null, false);
        $attributes['customerID']      = DataAttribute::create('kKunde', 'int');

        $attributes['detail'] = DataAttribute::create(
            'detail',
            PriceDetailModel::class,
            null,
            true,
            false,
            'kPreis'
        );

        return $attributes;
    }
}
