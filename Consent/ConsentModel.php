<?php

declare(strict_types=1);

namespace JTL\Consent;

use Exception;
use Illuminate\Support\Collection;
use JTL\Language\LanguageHelper;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;
use JTL\Plugin\Admin\InputType;

/**
 * Class ConsentModel
 *
 * @package JTL\Consent
 * @property int    $id
 * @property string $itemID
 * @method int getId()
 * @method void setId(int $id)
 * @method string getItemID()
 * @method void setItemID(string $value)
 * @property string $company
 * @method string getCompany()
 * @method void setCompany(string $value)
 * @property int    $pluginID
 * @method int getPluginID()
 * @method void setPluginID(int $value)
 * @property string $templateID
 * @method string getTemplateID()
 * @method void setTemplateID(string $value)
 * @property int    $active
 * @method int getActive()
 * @method void setActive(int $value)
 * @method Collection<int, ConsentLocalizationModel> getLocalization()
 * @method void setLocalization(Collection $value)
 */
final class ConsentModel extends DataModel
{
    /**
     * @inheritdoc
     */
    public function getTableName(): string
    {
        return 'tconsent';
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
                if (!isset($data['consentID'])) {
                    $data['consentID'] = $model->id;
                }
                try {
                    $loc = ConsentLocalizationModel::loadByAttributes(
                        $data,
                        $this->getDB(),
                        ConsentLocalizationModel::ON_NOTEXISTS_NEW
                    );
                } catch (Exception) {
                    continue;
                }
                /** @var ConsentModel $existing */
                $existing = $res->first(static function ($e) use ($loc): bool {
                    return $e->consentID === $loc->consentID && $e->languageID === $loc->languageID;
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
    public function onInstanciation(): void
    {
        $loc   = $this->getLocalization();
        $count = $loc->count();
        if ($count === 0 || ($first = $loc->first()) === null) {
            parent::onInstanciation();
            return;
        }
        $all                 = LanguageHelper::getInstance($this->getDB())->gibInstallierteSprachen();
        $langIDs             = \array_column($all, 'id');
        $existingLanguageIDs = $loc->map(static fn(ConsentLocalizationModel $e): int => $e->getLanguageID());
        /** @var ConsentLocalizationModel $default */
        $default = clone $first;
        foreach ($all as $languageModel) {
            $langID = $languageModel->getId();
            if (!$existingLanguageIDs->containsStrict($langID)) {
                $default->setLanguageID($langID);
                $default->setID(0);
                $loc->add($default);
            }
        }
        $this->setLocalization(
            $loc->filter(fn(ConsentLocalizationModel $e): bool => \in_array($e->getLanguageID(), $langIDs, true))
        );

        parent::onInstanciation();
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
        $attributes = [];
        $id         = DataAttribute::create('id', 'int', null, false, true);
        $id->getInputConfig()->setHidden(true);
        $attributes['id'] = $id;
        $itemID           = DataAttribute::create('itemID', 'varchar', null, false);
        $itemID->getInputConfig()->setModifyable(false);
        $attributes['itemID'] = $itemID;
        $templateID           = DataAttribute::create('templateID', 'varchar', null, false);
        $templateID->getInputConfig()->setModifyable(false);
        $attributes['templateID'] = $templateID;
        $attributes['company']    = DataAttribute::create('company', 'varchar', null, false);
        $pluginID                 = DataAttribute::create('pluginID', 'int', self::cast('0', 'int'), false);
        $pluginID->getInputConfig()->setModifyable(false);
        $pluginID->getInputConfig()->setInputType(InputType::NUMBER);
        $attributes['pluginID'] = $pluginID;
        $active                 = DataAttribute::create('active', 'tinyint', self::cast('1', 'tinyint'), false);
        $active->getInputConfig()->setAllowedValues([
            0 => 'inactive',
            1 => 'active'
        ]);
        $active->getInputConfig()->setInputType(InputType::SELECT);
        $attributes['active'] = $active;

        $attributes['localization'] = DataAttribute::create(
            'localization',
            ConsentLocalizationModel::class,
            null,
            true,
            false,
            'id',
            'consentID'
        );

        return $attributes;
    }
}
