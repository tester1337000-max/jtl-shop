<?php

declare(strict_types=1);

namespace JTL\Language;

use Exception;
use JTL\Helpers\Text;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;
use JTL\Shop;
use Locale;

/**
 * Class LanguageModel
 *
 * @package JTL\Language
 * @property int    $kSprache
 * @property int    $id
 * @method int getId()
 * @method void setId(int $id)
 * @property int    $active
 * @method int getActive()
 * @method void setActive(int $active)
 * @property string $cNameEnglisch
 * @property string $nameEN
 * @method string getNameEN()
 * @method void setNameEN(string $name)
 * @property string $cNameDeutsch
 * @property string $nameDE
 * @method string getNameDE()
 * @method void setNameDE(string $name)
 * @property string $cStandard
 * @property string $default
 * @method string getDefault()
 * @method void setDefault(string $default)
 * @property string $cISO
 * @property string $iso
 * @method string getIso()
 * @method void setIso(string $iso)
 * @property string $cShopStandard
 * @property string $shopDefault
 * @method string getShopDefault()
 * @method void setShopDefault(string $default)
 * @property string $iso639
 * @method string getIso639()
 * @method void setIso639(string $iso)
 * @property string $displayLanguage
 * @method string getDisplayLanguage()
 * @method void setDisplayLanguage(string $lang)
 * @property string $localizedName
 * @method string getLocalizedName()
 * @method void setLocalizedName(string $name)
 * @property string $url
 * @method string getUrl()
 * @method void setUrl(string $url)
 * @property string $urlFull
 */
final class LanguageModel extends DataModel
{
    public function getCode(): string
    {
        return $this->iso;
    }

    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tsprache';
    }

    public function isShopDefault(): bool
    {
        return $this->shopDefault === 'Y';
    }

    public function isDefault(): bool
    {
        return $this->default === 'Y';
    }

    /**
     * @throws Exception
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
        $this->registerSetter('url', function ($value) {
            $this->urlFull = $value;

            return $value;
        });
    }

    /**
     * @inheritdoc
     */
    public function onInstanciation(): void
    {
        if ($this->iso === null) {
            return;
        }
        $this->iso639          = Text::convertISO2ISO639($this->iso);
        $this->displayLanguage = Locale::getDisplayLanguage($this->iso639, $this->iso639);
        if (isset($_SESSION['AdminAccount']->language)) {
            $this->localizedName = Locale::getDisplayLanguage(
                $this->iso639,
                $_SESSION['AdminAccount']->language
            );
        } else {
            $this->localizedName = Locale::getDisplayLanguage(
                $this->iso639,
                Text::convertISO2ISO639(Shop::getLanguageCode())
            );
        }
    }

    /**
     * @return DataAttribute[]
     */
    public function getAttributes(): array
    {
        static $attributes = null;

        if ($attributes === null) {
            $attributes                = [];
            $attributes['id']          = DataAttribute::create(
                'kSprache',
                'tinyint',
                self::cast('0', 'tinyint'),
                false,
                true
            );
            $attributes['nameEN']      = DataAttribute::create('cNameEnglisch', 'varchar');
            $attributes['nameDE']      = DataAttribute::create('cNameDeutsch', 'varchar');
            $attributes['default']     = DataAttribute::create('cStandard', 'char', self::cast('N', 'char'));
            $attributes['iso']         = DataAttribute::create('cISO', 'varchar', null, false);
            $attributes['shopDefault'] = DataAttribute::create('cShopStandard', 'char', self::cast('N', 'char'));
            $attributes['active']      = DataAttribute::create('active', 'tinyint', self::cast('1', 'tinyint'));

            $iso = new DataAttribute();
            $iso->setName('cISO639')
                ->setDataType('varchar')
                ->setDefault('')
                ->setNullable(false)
                ->setDynamic(true);

            $displayLang = new DataAttribute();
            $displayLang->setName('displayLanguage')
                ->setDataType('varchar')
                ->setDefault('')
                ->setNullable(false)
                ->setDynamic(true);

            $localizedName = new DataAttribute();
            $localizedName->setName('localizedName')
                ->setDataType('varchar')
                ->setDefault('')
                ->setNullable(false)
                ->setDynamic(true);

            $url = new DataAttribute();
            $url->setName('cURL')
                ->setDataType('varchar')
                ->setDefault('')
                ->setNullable(false)
                ->setDynamic(true);
            $attributes['url'] = $url;

            $urlFull = new DataAttribute();
            $urlFull->setName('cURLFull')
                ->setDataType('varchar')
                ->setDefault('')
                ->setNullable(false)
                ->setDynamic(true);

            $attributes['iso639']          = $iso;
            $attributes['displayLanguage'] = $displayLang;
            $attributes['localizedName']   = $localizedName;
            $attributes['urlFull']         = $urlFull;
        }

        return $attributes;
    }
}
