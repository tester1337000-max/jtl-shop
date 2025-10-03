<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use DateTime;
use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;
use JTL\Model\ModelHelper;

/**
 * Class OrderModel
 * @OA\Schema(
 *     title="Order model",
 *     description="Order model",
 * )
 * @package JTL\REST\Models
 * @property int      $kBestellung
 * @method int getKBestellung()
 * @method void setKBestellung(int $value)
 * @property int      $kWarenkorb
 * @method int getKWarenkorb()
 * @method void setKWarenkorb(int $value)
 * @property int      $kKunde
 * @method int getKKunde()
 * @method void setKKunde(int $value)
 * @property int      $kLieferadresse
 * @method int getKLieferadresse()
 * @method void setKLieferadresse(int $value)
 * @property int      $kRechnungsadresse
 * @method int getKRechnungsadresse()
 * @method void setKRechnungsadresse(int $value)
 * @property int      $kZahlungsart
 * @method int getKZahlungsart()
 * @method void setKZahlungsart(int $value)
 * @property int      $kVersandart
 * @method int getKVersandart()
 * @method void setKVersandart(int $value)
 * @property int      $kSprache
 * @method int getKSprache()
 * @method void setKSprache(int $value)
 * @property int      $kWaehrung
 * @method int getKWaehrung()
 * @method void setKWaehrung(int $value)
 * @property float    $fGuthaben
 * @method float getFGuthaben()
 * @method void setFGuthaben(float $value)
 * @property float    $fGesamtsumme
 * @method float getFGesamtsumme()
 * @method void setFGesamtsumme(float $value)
 * @property string   $cSession
 * @method string getCSession()
 * @method void setCSession(string $value)
 * @property string   $cVersandartName
 * @method string getCVersandartName()
 * @method void setCVersandartName(string $value)
 * @property string   $cZahlungsartName
 * @method string getCZahlungsartName()
 * @method void setCZahlungsartName(string $value)
 * @property string   $cBestellNr
 * @method string getCBestellNr()
 * @method void setCBestellNr(string $value)
 * @property string   $cVersandInfo
 * @method string getCVersandInfo()
 * @method void setCVersandInfo(string $value)
 * @property int      $nLongestMinDelivery
 * @method int getNLongestMinDelivery()
 * @method void setNLongestMinDelivery(int $value)
 * @property int      $nLongestMaxDelivery
 * @method int getNLongestMaxDelivery()
 * @method void setNLongestMaxDelivery(int $value)
 * @property DateTime $dVersandDatum
 * @method DateTime getDVersandDatum()
 * @method void setDVersandDatum(DateTime $value)
 * @property DateTime $dBezahltDatum
 * @method DateTime getDBezahltDatum()
 * @method void setDBezahltDatum(DateTime $value)
 * @property DateTime $dBewertungErinnerung
 * @method DateTime getDBewertungErinnerung()
 * @method void setDBewertungErinnerung(DateTime $value)
 * @property string   $cTracking
 * @method string getCTracking()
 * @method void setCTracking(string $value)
 * @property string   $cKommentar
 * @method string getCKommentar()
 * @method void setCKommentar(string $value)
 * @property string   $cLogistiker
 * @method string getCLogistiker()
 * @method void setCLogistiker(string $value)
 * @property string   $cTrackingURL
 * @method string getCTrackingURL()
 * @method void setCTrackingURL(string $value)
 * @property string   $cIP
 * @method string getCIP()
 * @method void setCIP(string $value)
 * @property string   $cAbgeholt
 * @method string getCAbgeholt()
 * @method void setCAbgeholt(string $value)
 * @property string   $cStatus
 * @method string getCStatus()
 * @method void setCStatus(string $value)
 * @property DateTime $dErstellt
 * @method DateTime getDErstellt()
 * @method void setDErstellt(DateTime $value)
 * @property float    $fWaehrungsFaktor
 * @method float getFWaehrungsFaktor()
 * @method void setFWaehrungsFaktor(float $value)
 * @property string   $cPUIZahlungsdaten
 * @method string getCPUIZahlungsdaten()
 * @method void setCPUIZahlungsdaten(string $value)
 */
final class OrderModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   type="integer",
     *   example=1,
     *   description="The order ID"
     * )
     * @OA\Property(
     *   property="cartID",
     *   type="integer",
     *   example=1,
     *   description="The cart ID"
     * )
     * @OA\Property(
     *   property="customerID",
     *   type="integer",
     *   example=1,
     *   description="The customer ID"
     * )
     * @OA\Property(
     *   property="deliveryAddressID",
     *   type="integer",
     *   example=1,
     *   description="The delivery address ID"
     * )
     * @OA\Property(
     *   property="billingAddressID",
     *   type="integer",
     *   example=1,
     *   description="The billing address ID"
     * )
     * @OA\Property(
     *   property="paymentMethodID",
     *   type="integer",
     *   example=1,
     *   description="The payment method ID"
     * )
     * @OA\Property(
     *   property="shippingMethodID",
     *   type="integer",
     *   example=1,
     *   description="The shipping method ID"
     * )
     * @OA\Property(
     *   property="languageID",
     *   type="integer",
     *   example=1,
     *   description="The language ID"
     * )
     * @OA\Property(
     *   property="currencyID",
     *   type="integer",
     *   example=1,
     *   description="The currency ID"
     * )
     * @OA\Property(
     *   property="paymentType",
     *   type="integer",
     *   example=0,
     *   description="???"
     * )
     * @OA\Property(
     *   property="balance",
     *   type="number",
     *   format="float",
     *   example=0,
     *   description="???"
     * )
     * @OA\Property(
     *   property="total",
     *   type="number",
     *   format="float",
     *   example=123.45,
     *   description="Total order sum"
     * )
     * @OA\Property(
     *   property="sessionID",
     *   type="string",
     *   example="rv3mjk0v3nvlhf31vitu3krlnn",
     *   description="The PHP session ID"
     * )
     * @OA\Property(
     *   property="shippingMethodName",
     *   type="string",
     *   example="DHL Paket",
     *   description="The shipping method name"
     * )
     * @OA\Property(
     *   property="paymentMethodName",
     *   type="string",
     *   example="PayPal",
     *   description="The payment method name"
     * )
     * @OA\Property(
     *   property="orderNO",
     *   type="string",
     *   example="123",
     *   description="The order number"
     * )
     * @OA\Property(
     *   property="shippingInfo",
     *   type="string",
     *   example="",
     *   description=""
     * )
     * @OA\Property(
     *   property="longestMinDelivery",
     *   type="integer",
     *   example=1,
     *   description=""
     * )
     * @OA\Property(
     *   property="longestMaxDelivery",
     *   type="integer",
     *   example=4,
     *   description=""
     * )
     * @OA\Property(
     *     property="shippingDate",
     *     example="2022-09-22",
     *     format="datetime",
     *     description="Date of shipping",
     *     title="Date of shipping",
     *     type="string"
     * )
     * @OA\Property(
     *     property="paymentDate",
     *     example="2022-09-22",
     *     format="datetime",
     *     description="Date of incoming payment",
     *     title="Date of incoming payment",
     *     type="string"
     * )
     * @OA\Property(
     *     property="reviewReminder",
     *     example="2022-09-22",
     *     format="datetime",
     *     description="Date of sent review reminder",
     *     title="Date of sent review reminder",
     *     type="string"
     * )
     * @OA\Property(
     *   property="trackingID",
     *   type="string",
     *   example="",
     *   description="The package's tracking ID"
     * )
     * @OA\Property(
     *   property="comment",
     *   type="string",
     *   example="",
     *   description="Order comment"
     * )
     * @OA\Property(
     *   property="logistics",
     *   type="string",
     *   example="DHL",
     *   description=""
     * )
     * @OA\Property(
     *   property="trackingURL",
     *   type="string",
     *   example="https://example.com?track=123abc",
     *   description="The tracking URL"
     * )
     * @OA\Property(
     *   property="ipAddress",
     *   type="string",
     *   example="127.0.0.1",
     *   description="The customer's IP address"
     * )
     * @OA\Property(
     *   property="fetched",
     *   type="string",
     *   example="N",
     *   description="Fetched by Wawi"
     * )
     * @OA\Property(
     *   property="state",
     *   type="string",
     *   example="0",
     *   description="-1, 1, 2, 3, 4, 5"
     * )
     * @OA\Property(
     *     property="created",
     *     example="2022-09-22 12:13:14",
     *     format="datetime",
     *     description="Date of creation",
     *     title="Date of creation",
     *     type="string"
     * )
     * @OA\Property(
     *   property="currencyConversionFactor",
     *   type="number",
     *   format="float",
     *   example="1",
     *   description="Currency conversion factor"
     * )
     * @OA\Property(
     *   property="puidPaymentData",
     *   type="string",
     *   example="",
     *   description="???"
     * )
     * @OA\Property(
     *   property="attributes",
     *   type="array",
     *   description="List of OrderAttributeModel objects",
     *   @OA\Items(ref="#/components/schemas/OrderAttributeModel")
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tbestellung';
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
        $this->registerGetter('dVersandDatum', static function ($value, $default) {
            return ModelHelper::fromStrToDate($value, $default);
        });
        $this->registerSetter('dVersandDatum', static function ($value) {
            return ModelHelper::fromDateToStr($value);
        });
        $this->registerGetter('dBezahltDatum', static function ($value, $default) {
            return ModelHelper::fromStrToDate($value, $default);
        });
        $this->registerSetter('dBezahltDatum', static function ($value) {
            return ModelHelper::fromDateToStr($value);
        });
        $this->registerGetter('dBewertungErinnerung', static function ($value, $default) {
            return ModelHelper::fromStrToDateTime($value, $default);
        });
        $this->registerSetter('dBewertungErinnerung', static function ($value) {
            return ModelHelper::fromDateTimeToStr($value);
        });
        $this->registerGetter('dErstellt', static function ($value, $default) {
            return ModelHelper::fromStrToDateTime($value, $default);
        });
        $this->registerSetter('dErstellt', static function ($value) {
            return ModelHelper::fromDateTimeToStr($value);
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
        $attributes                             = [];
        $attributes['id']                       = DataAttribute::create('kBestellung', 'int', null, false, true);
        $attributes['cartID']                   = DataAttribute::create(
            'kWarenkorb',
            'int',
            self::cast('0', 'int'),
            false
        );
        $attributes['customerID']               = DataAttribute::create('kKunde', 'int', self::cast('0', 'int'), false);
        $attributes['deliveryAddressID']        = DataAttribute::create(
            'kLieferadresse',
            'int',
            self::cast('0', 'int'),
            false
        );
        $attributes['billingAddressID']         = DataAttribute::create('kRechnungsadresse', 'int', null, false);
        $attributes['paymentMethodID']          = DataAttribute::create(
            'kZahlungsart',
            'int',
            self::cast('0', 'int'),
            false
        );
        $attributes['shippingMethodID']         = DataAttribute::create('kVersandart', 'int', null, false);
        $attributes['languageID']               = DataAttribute::create(
            'kSprache',
            'int',
            self::cast('0', 'int'),
            false
        );
        $attributes['currencyID']               = DataAttribute::create(
            'kWaehrung',
            'int',
            self::cast('0', 'int'),
            false
        );
        $attributes['balance']                  = DataAttribute::create(
            'fGuthaben',
            'double',
            self::cast('0.0000', 'double'),
            false
        );
        $attributes['total']                    = DataAttribute::create(
            'fGesamtsumme',
            'double',
            self::cast('0', 'double'),
            false
        );
        $attributes['sessionID']                = DataAttribute::create(
            'cSession',
            'varchar',
            self::cast('', 'varchar'),
            false
        );
        $attributes['shippingMethodName']       = DataAttribute::create(
            'cVersandartName',
            'varchar',
            self::cast('', 'varchar'),
            false
        );
        $attributes['paymentMethodName']        = DataAttribute::create(
            'cZahlungsartName',
            'varchar',
            self::cast('', 'varchar'),
            false
        );
        $attributes['orderNO']                  = DataAttribute::create(
            'cBestellNr',
            'varchar',
            self::cast('', 'varchar'),
            false
        );
        $attributes['shippingInfo']             = DataAttribute::create('cVersandInfo', 'varchar');
        $attributes['longestMinDelivery']       = DataAttribute::create(
            'nLongestMinDelivery',
            'int',
            self::cast('0', 'int'),
            false
        );
        $attributes['longestMaxDelivery']       = DataAttribute::create(
            'nLongestMaxDelivery',
            'int',
            self::cast('0', 'int'),
            false
        );
        $attributes['shippingDate']             = DataAttribute::create('dVersandDatum', 'date');
        $attributes['paymentDate']              = DataAttribute::create('dBezahltDatum', 'date');
        $attributes['reviewReminder']           = DataAttribute::create('dBewertungErinnerung', 'datetime');
        $attributes['trackingID']               = DataAttribute::create('cTracking', 'varchar');
        $attributes['comment']                  = DataAttribute::create('cKommentar', 'mediumtext');
        $attributes['logistics']                = DataAttribute::create(
            'cLogistiker',
            'varchar',
            self::cast('', 'varchar'),
            false
        );
        $attributes['trackingURL']              = DataAttribute::create(
            'cTrackingURL',
            'varchar',
            self::cast('', 'varchar'),
            false
        );
        $attributes['ipAddress']                = DataAttribute::create('cIP', 'varchar', null, false);
        $attributes['fetched']                  = DataAttribute::create('cAbgeholt', 'char', self::cast('N', 'char'));
        $attributes['state']                    = DataAttribute::create('cStatus', 'char');
        $attributes['created']                  = DataAttribute::create('dErstellt', 'datetime');
        $attributes['currencyConversionFactor'] = DataAttribute::create(
            'fWaehrungsFaktor',
            'float',
            self::cast('1', 'float'),
            false
        );
        $attributes['puidPaymentData']          = DataAttribute::create('cPUIZahlungsdaten', 'mediumtext');

        $attributes['attributes'] = DataAttribute::create(
            'attributes',
            OrderAttributeModel::class,
            null,
            true,
            false,
            'kBestellung'
        );

        return $attributes;
    }
}
