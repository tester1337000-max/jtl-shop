<?php

declare(strict_types=1);

namespace JTL\REST\Models;

use DateTime;
use Exception;
use JTL\DB\DbInterface;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;
use JTL\Model\ModelHelper;
use JTL\Shop;

/**
 * Class CustomerModel
 * @OA\Schema(
 *     title="Customer model",
 *     description="Customer model",
 * )
 * @package JTL\REST\Models
 * @property int      $kKunde
 * @property int      $id
 * @method int getKKunde()
 * @method void setKKunde(int $value)
 * @method int getId()
 * @method void setId(int $value)
 * @property int      $kKundengruppe
 * @property int      $customerGroupID
 * @method int getKKundengruppe()
 * @method void setKKundengruppe(int $value)
 * @method int getCustomerGroupID()
 * @method void setCustomerGroupID(int $value)
 * @property int      $kSprache
 * @property int      $languageID
 * @method int getKSprache()
 * @method void setKSprache(int $value)
 * @method int getLanguageID()
 * @method void setLanguageID(int $value)
 * @property string   $cKundenNr
 * @property string   $customerNO
 * @method string getCKundenNr()
 * @method void setCKundenNr(string $value)
 * @property string   $cPasswort
 * @property string   $password
 * @method string getCPasswort()
 * @method void setCPasswort(string $value)
 * @method string getPassword()
 * @method void setPassword(string $value)
 * @property string   $cAnrede
 * @property string   $salutation
 * @method string getCAnrede()
 * @method void setCAnrede(string $value)
 * @method string getSalutation()
 * @method void setSalutation(string $value)
 * @property string   $cTitel
 * @property string   $titel
 * @method string getCTitel()
 * @method void setCTitel(string $value)
 * @method string getTitle()
 * @method void setTitle(string $value)
 * @property string   $cVorname
 * @property string   $firstname
 * @method string getCVorname()
 * @method void setCVorname(string $value)
 * @method string getFirstname()
 * @method void setFirstname(string $value)
 * @property string   $cNachname
 * @property string   $surname
 * @method string getCNachname()
 * @method void setCNachname(string $value)
 * @method string getSurname()
 * @method void setSurname(string $value)
 * @property string   $cFirma
 * @property string   $company
 * @method string getCFirma()
 * @method void setCFirma(string $value)
 * @method string getCompany()
 * @method void setCompany(string $value)
 * @property string   $cZusatz
 * @property string   $additional
 * @method string getCZusatz()
 * @method void setCZusatz(string $value)
 * @method string getAdditional()
 * @method void setAdditional(string $value)
 * @property string   $cStrasse
 * @property string   $street
 * @method string getCStrasse()
 * @method void setCStrasse(string $value)
 * @method string getStreet()
 * @method void setStreet(string $value)
 * @method string getStreetNO()
 * @method void setStreetNO(string $value)
 * @property string   $cHausnummer
 * @property string   $streetNO
 * @method string getCHausnummer()
 * @method void setCHausnummer(string $value)
 * @property string   $cAdressZusatz
 * @property string   $additionalAddress
 * @method string getCAdressZusatz()
 * @method void setCAdressZusatz(string $value)
 * @method string getAdditionalAddress()
 * @method void setAdditionalAddress(string $value)
 * @property string   $cPLZ
 * @property string   $zip
 * @method string getCPLZ()
 * @method void setCPLZ(string $value)
 * @method string getZip()
 * @method void setZip(string $value)
 * @property string   $cOrt
 * @property string   $city
 * @method string getCOrt()
 * @method void setCOrt(string $value)
 * @method string getCity()
 * @method void setCity(string $value)
 * @property string   $cBundesland
 * @property string   $state
 * @method string getCBundesland()
 * @method void setCBundesland(string $value)
 * @method string getState()
 * @method void setState(string $value)
 * @property string   $country
 * @method string getCLand()
 * @method void setCLand(string $value)
 * @method string getCountry()
 * @method void setCounty(string $value)
 * @property string   $cTel
 * @property string   $tel
 * @method string getCTel()
 * @method void setCTel(string $value)
 * @method string getTel()
 * @method void setTel(string $value)
 * @property string   $cMobil
 * @property string   $mobile
 * @method string getCMobil()
 * @method void setCMobil(string $value)
 * @method string getMobile()
 * @method void setMobile(string $value)
 * @property string   $cFax
 * @property string   $fax
 * @method string getCFax()
 * @method void setCFax(string $value)
 * @method string getFax()
 * @method void setFax(string $value)
 * @property string   $cMail
 * @property string   $mail
 * @method string getCMail()
 * @method void setCMail(string $value)
 * @method string getMail()
 * @method void setMail(string $value)
 * @property string   $cUSTID
 * @property string   $ustidnr
 * @method string getCUSTID()
 * @method void setCUSTID(string $value)
 * @method string getUstidnr()
 * @method void setUstidnr(string $value)
 * @property string   $cWWW
 * @property string   $www
 * @method string getCWWW()
 * @method void setCWWW(string $value)
 * @method string getWww()
 * @method void setWww(string $value)
 * @property string   $cSperre
 * @property string   $locked
 * @method string getCSperre()
 * @method void setCSperre(string $value)
 * @method string getLocked()
 * @method void setLocked(string $value)
 * @property float    $fGuthaben
 * @property float    $balance
 * @method float getFGuthaben()
 * @method void setFGuthaben(float $value)
 * @method float getBalance()
 * @method void setBalance(float $value)
 * @property string   $cNewsletter
 * @property string   $newsletter
 * @method string getCNewsletter()
 * @method void setCNewsletter(string $value)
 * @method string getNewsletter()
 * @method void setNewsletter(string $value)
 * @property DateTime $dGeburtstag
 * @property DateTime $birthday
 * @method DateTime getDGeburtstag()
 * @method void setDGeburtstag(DateTime $value)
 * @method DateTime getBirthday()
 * @method void setBirthday(DateTime $value)
 * @property float    $fRabatt
 * @property float    $discount
 * @method float getFRabatt()
 * @method void setFRabatt(float $value)
 * @method float getDiscount()
 * @method void setDiscount(float $value)
 * @property string   $cHerkunft
 * @property string   $origin
 * @method string getCHerkunft()
 * @method void setCHerkunft(string $value)
 * @method string getOrigin()
 * @method void setOrigin(string $value)
 * @property DateTime $dErstellt
 * @property DateTime $created
 * @method DateTime getDErstellt()
 * @method void setDErstellt(DateTime $value)
 * @method DateTime getCreated()
 * @method void setCreated(DateTime $value)
 * @property DateTime $dVeraendert
 * @property DateTime $modified
 * @method DateTime getDVeraendert()
 * @method void setDVeraendert(DateTime $value)
 * @method DateTime getModified()
 * @method void setModified(DateTime $value)
 * @property string   $cAktiv
 * @property string   $active
 * @method string getCAktiv()
 * @method void setCAktiv(string $value)
 * @method string getActive()
 * @method void setActive(string $value)
 * @property string   $cAbgeholt
 * @property string   $fetched
 * @method string getCAbgeholt()
 * @method void setCAbgeholt(string $value)
 * @method string getFetched()
 * @method void setFetched(string $value)
 * @property int      $nRegistriert
 * @property int      $registered
 * @method int getNRegistriert()
 * @method void setNRegistriert(int $value)
 * @method int getRegistered()
 * @method void setRegistered(int $value)
 * @property int      $nLoginversuche
 * @property int      $loginAttempts
 * @method int getNLoginversuche()
 * @method void setNLoginversuche(int $value)
 * @method int getLoginAttempts()
 * @method void setLoginAttempts(int $value)
 */
final class CustomerModel extends DataModel
{
    /**
     * @OA\Property(
     *   property="id",
     *   type="integer",
     *   example=99,
     *   description="The primary key"
     * )
     * @OA\Property(
     *   property="customerGroupID",
     *   type="integer",
     *   example=1,
     *   description="The customer group id"
     * )
     * @OA\Property(
     *   property="languageID",
     *   type="integer",
     *   example=1,
     *   description="The language id"
     * )
     * @OA\Property(
     *   property="customerNO",
     *   type="string",
     *   example="K123",
     *   description="The customer number"
     * )
     * @OA\Property(
     *   property="salutation",
     *   type="string",
     *   example="m",
     *   description="Salutation (m/w)"
     * )
     * @OA\Property(
     *   property="title",
     *   type="string",
     *   example="Dr.",
     *   description="Title"
     * )
     * @OA\Property(
     *   property="firstname",
     *   type="string",
     *   example="Rainer",
     *   description="Firstname"
     * )
     * @OA\Property(
     *   property="surname",
     *   type="string",
     *   example="Zufall",
     *   description="Lastname"
     * )
     * @OA\Property(
     *   property="company",
     *   type="string",
     *   example="Example Co. Ltd.",
     *   description="Company"
     * )
     * @OA\Property(
     *   property="additional",
     *   type="string",
     *   example="",
     *   description="Additional company data"
     * )
     * @OA\Property(
     *   property="street",
     *   type="string",
     *   example="Example Street",
     *   description="Street name"
     * )
     * @OA\Property(
     *   property="streetNO",
     *   type="string",
     *   example="123",
     *   description="Street number"
     * )
     * @OA\Property(
     *   property="additionalAddress",
     *   type="string",
     *   example="c/o Claire Grube",
     *   description="Additional address data"
     * )
     * @OA\Property(
     *   property="zip",
     *   type="string",
     *   example="41836",
     *   description="Zip code"
     * )
     * @OA\Property(
     *   property="city",
     *   type="string",
     *   example="Hückelhoven",
     *   description="City"
     * )
     * @OA\Property(
     *   property="state",
     *   type="string",
     *   example="Nordrhein-Westfalen",
     *   description="State"
     * )
     * @OA\Property(
     *   property="country",
     *   type="string",
     *   example="Deutschland",
     *   description="Country"
     * )
     * @OA\Property(
     *   property="tel",
     *   type="string",
     *   example="+49 2433 8056801",
     *   description="Telephone number"
     * )
     * @OA\Property(
     *   property="mobile",
     *   type="string",
     *   example="+49 2433 8056801",
     *   description="Mobile number"
     * )
     * @OA\Property(
     *   property="fax",
     *   type="string",
     *   example="+49 2433 970433",
     *   description="Fax number"
     * )
     * @OA\Property(
     *   property="mail",
     *   type="string",
     *   example="info@jtl-software.com",
     *   description="Email address"
     * )
     * @OA\Property(
     *   property="ustidnr",
     *   type="string",
     *   example="DE257864472",
     *   description="Tax ID"
     * )
     * @OA\Property(
     *   property="www",
     *   type="string",
     *   example="www.jtl-software.com",
     *   description="Homepage"
     * )
     * @OA\Property(
     *   property="locked",
     *   type="string",
     *   example="N",
     *   description="Is locked? (Y/N)"
     * )
     * @OA\Property(
     *   property="balance",
     *   type="number",
     *   format="float",
     *   example="0",
     *   description="Account balance"
     * )
     * @OA\Property(
     *   property="newsletter",
     *   type="string",
     *   example="N",
     *   description="Accepts newsletter? (Y/N)"
     * )
     * @OA\Property(
     *   property="birthday",
     *   type="string",
     *   example="1984-09-01",
     *   description="Birthday"
     * )
     * @OA\Property(
     *   property="origin",
     *   type="string",
     *   example="",
     *   description=""
     * )
     * @OA\Property(
     *   property="created",
     *   example="2022-09-22",
     *   format="datetime",
     *   description="Date created",
     *   type="string"
     * )
     * @OA\Property(
     *   property="modified",
     *   example="2022-09-22 12:13:14",
     *   format="datetime",
     *   description="Date modified",
     *   type="string"
     * )
     * @OA\Property(
     *   property="active",
     *   type="string",
     *   example="Y",
     *   description="Customer is active? (Y/N)"
     * )
     * @OA\Property(
     *   property="fetched",
     *   type="string",
     *   example="Y",
     *   description="Fetched by Wawi? (Y/N)"
     * )
     * @OA\Property(
     *   property="registered",
     *   type="string",
     *   example="Y",
     *   description="Registered customer? (Y/N)"
     * )
     * @OA\Property(
     *   property="loginAttempts",
     *   type="integer",
     *   example=0,
     *   description="Failed login attempts"
     * )
     *
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tkunde';
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
        $this->registerGetter('dGeburtstag', static function ($value, $default) {
            return ModelHelper::fromStrToDate($value, $default);
        });
        $this->registerSetter('dGeburtstag', static function ($value) {
            return ModelHelper::fromDateToStr($value);
        });
        $this->registerGetter('dErstellt', static function ($value, $default) {
            return ModelHelper::fromStrToDate($value, $default);
        });
        $this->registerSetter('dErstellt', static function ($value) {
            return ModelHelper::fromDateToStr($value);
        });
        $this->registerGetter('dVeraendert', static function ($value, $default) {
            return ModelHelper::fromStrToDateTime($value, $default);
        });
        $this->registerSetter('dVeraendert', static function ($value) {
            return ModelHelper::fromDateTimeToStr($value);
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
        $attributes                      = [];
        $attributes['id']                = DataAttribute::create('kKunde', 'int', null, false, true);
        $attributes['customerGroupID']   = DataAttribute::create('kKundengruppe', 'int', self::cast('0', 'int'), false);
        $attributes['languageID']        = DataAttribute::create('kSprache', 'int', self::cast('0', 'int'), false);
        $attributes['customerNO']        = DataAttribute::create('cKundenNr', 'varchar');
        $attributes['password']          = DataAttribute::create('cPasswort', 'varchar');
        $attributes['salutation']        = DataAttribute::create(
            'cAnrede',
            'varchar',
            self::cast('', 'varchar'),
            false
        );
        $attributes['title']             = DataAttribute::create('cTitel', 'varchar');
        $attributes['firstname']         = DataAttribute::create(
            'cVorname',
            'varchar',
            self::cast('', 'varchar'),
            false
        );
        $attributes['surname']           = DataAttribute::create(
            'cNachname',
            'varchar',
            self::cast('', 'varchar'),
            false
        );
        $attributes['company']           = DataAttribute::create('cFirma', 'varchar');
        $attributes['additional']        = DataAttribute::create('cZusatz', 'varchar');
        $attributes['street']            = DataAttribute::create(
            'cStrasse',
            'varchar',
            self::cast('', 'varchar'),
            false
        );
        $attributes['streetNO']          = DataAttribute::create('cHausnummer', 'varchar', null, false);
        $attributes['additionalAddress'] = DataAttribute::create('cAdressZusatz', 'varchar');
        $attributes['zip']               = DataAttribute::create('cPLZ', 'varchar', self::cast('', 'varchar'), false);
        $attributes['city']              = DataAttribute::create('cOrt', 'varchar', self::cast('', 'varchar'), false);
        $attributes['state']             = DataAttribute::create(
            'cBundesland',
            'varchar',
            self::cast('', 'varchar'),
            false
        );
        $attributes['country']           = DataAttribute::create('cLand', 'varchar', null, false);
        $attributes['tel']               = DataAttribute::create('cTel', 'varchar');
        $attributes['mobile']            = DataAttribute::create('cMobil', 'varchar');
        $attributes['fax']               = DataAttribute::create('cFax', 'varchar');
        $attributes['mail']              = DataAttribute::create('cMail', 'varchar', self::cast('', 'varchar'), false);
        $attributes['ustidnr']           = DataAttribute::create('cUSTID', 'varchar');
        $attributes['www']               = DataAttribute::create('cWWW', 'varchar');
        $attributes['locked']            = DataAttribute::create(
            'cSperre',
            'varchar',
            self::cast('N', 'varchar'),
            false
        );
        $attributes['balance']           = DataAttribute::create(
            'fGuthaben',
            'double',
            self::cast('0.00', 'double'),
            false
        );
        $attributes['newsletter']        = DataAttribute::create('cNewsletter', 'char', self::cast('', 'char'), false);
        $attributes['birthday']          = DataAttribute::create('dGeburtstag', 'date');
        $attributes['discount']          = DataAttribute::create(
            'fRabatt',
            'double',
            self::cast('0.00', 'double'),
            false
        );
        $attributes['origin']            = DataAttribute::create(
            'cHerkunft',
            'varchar',
            self::cast('', 'varchar'),
            false
        );
        $attributes['created']           = DataAttribute::create('dErstellt', 'date');
        $attributes['modified']          = DataAttribute::create('dVeraendert', 'datetime', 'now()', false);
        $attributes['active']            = DataAttribute::create('cAktiv', 'char', self::cast('Y', 'char'), false);
        $attributes['fetched']           = DataAttribute::create('cAbgeholt', 'char', self::cast('N', 'char'), false);
        $attributes['registered']        = DataAttribute::create('nRegistriert', 'tinyint', null, false);
        $attributes['loginAttempts']     = DataAttribute::create(
            'nLoginversuche',
            'int',
            self::cast('0', 'int'),
            false
        );

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    public static function create(
        object|array $attributes,
        DbInterface $db,
        $option = self::NONE
    ): static {
        $cryptoService = Shop::Container()->getCryptoService();

        $instance = self::newInstance($db);
        $instance->fill($attributes);
        $instance->setSurname(\trim($cryptoService->encryptXTEA($instance->surname ?? '')));
        $instance->setCompany(\trim($cryptoService->encryptXTEA($instance->company ?? '')));
        $instance->setAdditional(\trim($cryptoService->encryptXTEA($instance->additional ?? '')));
        $instance->setStreet(\trim($cryptoService->encryptXTEA($instance->street ?? '')));
        // there has to be a password set. so use the emailadress to fake one.
        $instance->setPassword(\trim($cryptoService->encryptXTEA($instance->mail ?? '')));
        $instance->createNew($option);
        $instance->updateChildModels();

        return $instance;
    }
}
