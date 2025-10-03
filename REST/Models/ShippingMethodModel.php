<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use Exception;
use Illuminate\Support\Collection;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;
use JTL\Model\DataModelInterface;

/**
 * Class ShippingMethodModel
 *
 * @OA\Schema(
 *     title="ShippingMethodModel",
 *     description="ShippingMethodModel",
 * )
 * @package JTL\REST\Models
 * @property int    $kVersandart
 * @method int getKVersandart()
 * @method void setKVersandart(int $value)
 * @property int    $kVersandberechnung
 * @method int getKVersandberechnung()
 * @method void setKVersandberechnung(int $value)
 * @property string $cVersandklassen
 * @method string getCVersandklassen()
 * @method void setCVersandklassen(string $value)
 * @property string $cName
 * @method string getCName()
 * @method void setCName(string $value)
 * @property string $cLaender
 * @method string getCLaender()
 * @method void setCLaender(string $value)
 * @property string $cAnzeigen
 * @method string getCAnzeigen()
 * @method void setCAnzeigen(string $value)
 * @property string $cKundengruppen
 * @method string getCKundengruppen()
 * @method void setCKundengruppen(string $value)
 * @property string $cBild
 * @method string getCBild()
 * @method void setCBild(string $value)
 * @property string $eSteuer
 * @method string getESteuer()
 * @method void setESteuer(string $value)
 * @property int    $nSort
 * @method int getNSort()
 * @method void setNSort(int $value)
 * @property int    $nMinLiefertage
 * @method int getNMinLiefertage()
 * @method void setNMinLiefertage(int $value)
 * @property int    $nMaxLiefertage
 * @method int getNMaxLiefertage()
 * @method void setNMaxLiefertage(int $value)
 * @property float  $fPreis
 * @method float getFPreis()
 * @method void setFPreis(float $value)
 * @property float  $fVersandkostenfreiAbX
 * @method float getFVersandkostenfreiAbX()
 * @method void setFVersandkostenfreiAbX(float $value)
 * @property float  $fDeckelung
 * @method float getFDeckelung()
 * @method void setFDeckelung(float $value)
 * @property string $cNurAbhaengigeVersandart
 * @method string getCNurAbhaengigeVersandart()
 * @method void setCNurAbhaengigeVersandart(string $value)
 * @property string $cSendConfirmationMail
 * @method string getCSendConfirmationMail()
 * @method void setCSendConfirmationMail(string $value)
 * @property string $cIgnoreShippingProposal
 * @method string getCIgnoreShippingProposal()
 * @method void setCIgnoreShippingProposal(string $value)
 */
final class ShippingMethodModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   type="integer",
     *   example=33,
     *   description="The item's id"
     * ),
     * @OA\Property(
     *   property="calculationID",
     *   type="integer",
     *   example=5,
     *   description="ID of shipping calculation record"
     * ),
     * @OA\Property(
     *   property="methods",
     *   type="string",
     *   example="Shipping class",
     *   description="Shipping class"
     * ),
     * @OA\Property(
     *   property="name",
     *   type="string",
     *   example="DHL",
     *   description="Name of Shipping Method"
     * ),
     * @OA\Property(
     *   property="countries",
     *   type="string",
     *   example="DE NL BE",
     *   description="Country codes of countries the rule applies to"
     * ),
     * @OA\Property(
     *   property="show",
     *   type="string",
     *   example="immer",
     *   description="When to show this method"
     * ),
     * @OA\Property(
     *   property="customerGroups",
     *   type="string",
     *   example="-1",
     *   description="Customergroups the rule applies to. -1 = All groups, 1;2 = Selection of groups"
     * ),
     * @OA\Property(
     *   property="image",
     *   type="string",
     *   example="picture.jpg",
     *   description="Image to represent rule"
     * ),
     * @OA\Property(
     *   property="tax",
     *   type="string",
     *   example="netto",
     *   description="including (brutto) or not including tax(netto)"
     * ),
     * @OA\Property(
     *   property="sort",
     *   type="integer",
     *   example=1,
     *   description="Position of rule in list"
     * ),
     * @OA\Property(
     *   property="minDeliveryDays",
     *   type="integer",
     *   example=2,
     *   description="minDeliveryDays"
     * ),
     * @OA\Property(
     *   property="maxDeliveryDays",
     *   type="integer",
     *   example=3,
     *   description="maxDeliveryDays"
     * ),
     * @OA\Property(
     *   property="price",
     *   type="number",
     *   format="double",
     *   example="0.00",
     *   description="Price"
     * ),
     * @OA\Property(
     *   property="shippingFreeFrom",
     *   type="number",
     *   format="double",
     *   example="50.00",
     *   description="shippingFreeFrom"
     * ),
     * @OA\Property(
     *   property="cap",
     *   type="number",
     *   format="double",
     *   example="200.00",
     *   description="maximum value"
     * ),
     * @OA\Property(
     *   property="depending",
     *   type="string",
     *   example="N",
     *   description="Depending from another rule?"
     * ),
     * @OA\Property(
     *   property="sendConfirmationMail",
     *   type="string",
     *   example="Y",
     *   description="Send confirmation mail?"
     * ),
     * @OA\Property(
     *   property="ignoreShippingProposal",
     *   type="string",
     *   example="Y",
     *   description="ignoreShippingProposal"
     * ),
     * @OA\Property(
     *   property="localization",
     *   type="array",
     *   description="List of ShippingMethodLocalizationModel objects",
     *   @OA\Items(ref="#/components/schemas/ShippingMethodLocalizationModel")
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tversandart';
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
                if (!isset($data['id'])) {
                    $data['id'] = $model->id;
                }
                try {
                    $loc = ShippingMethodLocalizationModel::loadByAttributes(
                        $data,
                        $this->getDB(),
                        self::ON_NOTEXISTS_NEW
                    );
                } catch (Exception) {
                    continue;
                }
                $existing = $res->first(static fn($e): bool => $e->id === $loc->id && $e->code === $loc->code);
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
        $attributes                   = [];
        $attributes['id']             = DataAttribute::create('kVersandart', 'int', null, false, true);
        $attributes['calculationID']  = DataAttribute::create('kVersandberechnung', 'int');
        $attributes['methods']        = DataAttribute::create('cVersandklassen', 'varchar');
        $attributes['name']           = DataAttribute::create('cName', 'varchar');
        $attributes['countries']      = DataAttribute::create('cLaender', 'mediumtext');
        $attributes['show']           = DataAttribute::create('cAnzeigen', 'varchar');
        $attributes['customerGroups'] = DataAttribute::create('cKundengruppen', 'varchar', null, false);
        $attributes['image']          = DataAttribute::create('cBild', 'varchar', null, false);
        $attributes['tax']            = DataAttribute::create('eSteuer', 'enum', null, false);

        $attributes['sort']                   = DataAttribute::create(
            'nSort',
            'tinyint',
            self::cast('0', 'tinyint'),
            false
        );
        $attributes['minDeliveryDays']        = DataAttribute::create(
            'nMinLiefertage',
            'tinyint',
            self::cast('2', 'tinyint')
        );
        $attributes['maxDeliveryDays']        = DataAttribute::create(
            'nMaxLiefertage',
            'tinyint',
            self::cast('3', 'tinyint')
        );
        $attributes['price']                  = DataAttribute::create(
            'fPreis',
            'double',
            self::cast('0.00', 'double'),
            false
        );
        $attributes['shippingFreeFrom']       = DataAttribute::create(
            'fVersandkostenfreiAbX',
            'double',
            self::cast('0.00', 'double'),
            false
        );
        $attributes['cap']                    = DataAttribute::create(
            'fDeckelung',
            'double',
            self::cast('0.00', 'double'),
            false
        );
        $attributes['depending']              = DataAttribute::create(
            'cNurAbhaengigeVersandart',
            'char',
            self::cast('N', 'char'),
            false
        );
        $attributes['sendConfirmationMail']   = DataAttribute::create(
            'cSendConfirmationMail',
            'char',
            self::cast('Y', 'char'),
            false
        );
        $attributes['ignoreShippingProposal'] = DataAttribute::create(
            'cIgnoreShippingProposal',
            'char',
            self::cast('N', 'char'),
            false
        );
        $attributes['localization']           = DataAttribute::create(
            'localization',
            ShippingMethodLocalizationModel::class,
            null,
            true,
            false,
            'kVersandart'
        );

        return $attributes;
    }
}
