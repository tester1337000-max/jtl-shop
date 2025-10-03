<?php

declare(strict_types=1);

namespace JTL\Consent;

use Exception;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;
use JTL\Plugin\Admin\InputType;

/**
 * Class ConsentLocalizationModel
 * @package JTL\Consent
 * @property int    $id
 * @property int    $languageID
 * @method int getLanguageID()
 * @method void setLanguageID(int $value)
 * @property string $privacyPolicy
 * @method string getPrivacyPolicy()
 * @method void setPrivacyPolicy(string $value)
 * @property string $name
 * @method string getName()
 * @method void setName(string $value)
 * @property string $description
 * @method string getDescription()
 * @method void setDescription(string $value)
 * @property string $purpose
 * @method string getPurpose()
 * @method void setPurpose(string $value)
 * @property int    $consentID
 * @method int getConsentID()
 * @method void setConsentID(int $value)
 */
final class ConsentLocalizationModel extends DataModel
{
    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tconsentlocalization';
    }

    /**
     * @inheritdoc
     */
    public function setKeyName($keyName): void
    {
        throw new Exception(__METHOD__ . ': setting of keyname is not supported', self::ERR_DATABASE);
    }

    public function getID(): int
    {
        return $this->id;
    }

    public function setID(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @inheritdoc
     */
    public function getAttributes(): array
    {
        static $attributes = null;
        if ($attributes === null) {
            $attributes = [];
            $id         = DataAttribute::create('id', 'int', null, false, true);
            $id->getInputConfig()->setHidden(true);
            $attributes['id'] = $id;
            $langID           = DataAttribute::create('languageID', 'int', null, false);
            $langID->getInputConfig()->setModifyable(false);
            $langID->getInputConfig()->setInputType(InputType::NUMBER);
            $attributes['languageID']    = $langID;
            $attributes['privacyPolicy'] = DataAttribute::create('privacyPolicy', 'varchar', null, false);
            $attributes['name']          = DataAttribute::create('name', 'varchar', null, false);
            $description                 = DataAttribute::create('description', 'text', null, false);
            $description->getInputConfig()->setInputType(InputType::TEXTAREA);
            $attributes['description'] = $description;
            $purpose                   = DataAttribute::create('purpose', 'text', null, false);
            $purpose->getInputConfig()->setInputType(InputType::TEXTAREA);
            $attributes['purpose'] = $purpose;
            $consentID             = DataAttribute::create('consentID', 'int', null, false);
            $consentID->getInputConfig()->setHidden(true);
            $attributes['consentID'] = $consentID;
        }

        return $attributes;
    }
}
