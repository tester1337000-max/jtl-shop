<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;

/**
 * Class ShippingMethodLocalizationModel
 *
 * @OA\Schema(
 *      title="ShippingMethodLocalizationModel",
 *      description="ShippingMethodLocalizationModel",
 *  )
 * @package JTL\REST\Models
 * @property int    $id
 * @property int    $kVersandart
 * @method int getKVersandart()
 * @method void setKVersandart(int $value)
 * @property string $code
 * @property string $cISOSprache
 * @method string getCISOSprache()
 * @method void setCISOSprache(string $value)
 * @property string $cName
 * @property string $name
 * @method string getCName()
 * @method void setCName(string $value)
 * @property string $deliveryTime
 * @property string $cLieferdauer
 * @method string getCLieferdauer()
 * @method void setCLieferdauer(string $value)
 * @property string $cHinweistext
 * @property string $notice
 * @method string getCHinweistext()
 * @method void setCHinweistext(string $value)
 * @property string $cHinweistextShop
 * @property string $noticeShop
 * @method string getCHinweistextShop()
 * @method void setCHinweistextShop(string $value)
 */
final class ShippingMethodLocalizationModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   type="integer",
     *   example=33,
     *   description="The item's id"
     * ),
     * @OA\Property(
     *   property="code",
     *   type="string",
     *   example="eng",
     *   description="isoCode of the language"
     * ),
     * @OA\Property(
     *   property="name",
     *   type="string",
     *   example="DHL",
     *   description="Name of Shipping Method"
     * ),
     * @OA\Property(
     *   property="deliveryTime",
     *   type="string",
     *   example="2-3 Working days",
     *   description="The delivery time"
     * ),
     * @OA\Property(
     *   property="notice",
     *   type="string",
     *   example="Only for dealers",
     *   description="A notice shown to the admin"
     * ),
     * @OA\Property(
     *   property="noticeShop",
     *   type="string",
     *   example="Especially for you",
     *   description="A notice shown to the customer in the shop"
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tversandartsprache';
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
     * @throws Exception
     */
    public function getAttributes(): array
    {
        $attributes                 = [];
        $attributes['id']           = DataAttribute::create('kVersandart', 'int', self::cast('0', 'int'), false, true);
        $attributes['code']         = DataAttribute::create('cISOSprache', 'varchar', null, false, true);
        $attributes['name']         = DataAttribute::create('cName', 'varchar');
        $attributes['deliveryTime'] = DataAttribute::create('cLieferdauer', 'varchar');
        $attributes['notice']       = DataAttribute::create('cHinweistext', 'mediumtext');
        $attributes['noticeShop']   = DataAttribute::create('cHinweistextShop', 'mediumtext');

        return $attributes;
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function save(?array $partial = null, bool $updateChildModels = true): bool
    {
        $noPrimaryKey = false;
        $keyValue     = null;
        $keyName      = null;
        $members      = $this->getSqlObject();
        $allKeyNames  = [];
        try {
            $allKeyNames = $this->getAllKeyNames(true);
            $keyName     = $this->getKeyName(true);
            $keyValue    = $this->getKey();
            if (\count($allKeyNames) === 1 && empty($members->$keyName)) {
                unset($members->$keyName);
            }
        } catch (Exception $e) {
            if ($e->getCode() === self::ERR_NO_PRIMARY_KEY) {
                $noPrimaryKey = true;
            } else {
                throw $e;
            }
        }
        $members = $this->getMembersToSave($members, $partial);
        if (!$this->loaded || $noPrimaryKey || $keyValue === null || $keyValue === 0) {
            $pkValue = $this->getDB()->insert($this->getTableName(), $members);
            if ((empty($keyValue) || $noPrimaryKey) && !empty($pkValue)) {
                try {
                    $this->setKey($pkValue);
                } catch (Exception) {
                }
                if ($updateChildModels) {
                    $this->updateChildModels();
                }

                return true;
            }
            if ($updateChildModels) {
                $this->updateChildModels();
            }

            return false;
        }
        // hack to allow updating tables like "tkategoriesprache" where no single primary key is present
        if (\count($allKeyNames) > 1) {
            $keyValue = [];
            $keyName  = [];
            foreach ($allKeyNames as $name) {
                $keyName[]  = $name;
                $keyValue[] = $this->getAttribValue($name);
            }
        }
        $res = $this->getDB()->update($this->getTableName(), $keyName, $keyValue, $members) >= 0;
        if ($updateChildModels) {
            $this->updateChildModels();
        }

        return $res;
    }
}
