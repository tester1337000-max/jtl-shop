<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class CartModel
 *
 * @OA\Schema(
 *     title="Cart model",
 *     description="Cart model",
 * )
 *
 * @package JTL\REST\Models
 * @property int $id
 * @property int $kWarenkorb
 * @method int getId()
 * @method void setId(int $value)
 * @property int $customerID
 * @property int $kKunde
 * @method int getCustomerId()
 * @method void setCustomerId(int $value)
 * @property int $deliveryAddressID
 * @property int $kLieferadresse
 * @method int getDeliveryAddressId()
 * @method void setDeliveryAddressId(int $value)
 * @property int $paymentInfoID
 * @property int $kZahlungsInfo
 * @method int getPaymentInfoId()
 * @method void setPaymentInfoId(int $value)
 */
final class CartModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   title="id",
     *   format="int64",
     *   type="integer",
     *   example=2,
     *   description="The cart's id"
     * )
     * @OA\Property(
     *   property="deliveryAddressID",
     *   type="integer",
     *   example=1,
     *   description="The delivery address id"
     * )
     * @OA\Property(
     *   property="customerID",
     *   type="integer",
     *   example=3,
     *   description="The customers's id"
     * )
     * @OA\Property(
     *   property="paymentInfoID",
     *   type="integer",
     *   example=0,
     *   description="The payment method's id"
     * )
     * @OA\Property(
     *   property="items",
     *   type="array",
     *   description="List of CartItemModel objects",
     *   @OA\Items(ref="#/components/schemas/CartItemModel")
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'twarenkorb';
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
        static $attributes = null;
        if ($attributes !== null) {
            return $attributes;
        }
        $attributes                      = [];
        $attributes['id']                = DataAttribute::create('kWarenkorb', 'int', null, false, true);
        $attributes['customerID']        = DataAttribute::create('kKunde', 'int', self::cast('0', 'int'), false);
        $attributes['deliveryAddressID'] = DataAttribute::create(
            'kLieferadresse',
            'int',
            self::cast('0', 'int'),
            false
        );
        $attributes['paymentInfoID']     = DataAttribute::create('kZahlungsInfo', 'int', self::cast('0', 'int'));

        $attributes['items'] = DataAttribute::create('items', CartItemModel::class, null, true, false, 'kWarenkorb');

        return $attributes;
    }
}
