<?php

declare(strict_types=1);

namespace JTL\Consent;

use JTL\Model\DataModelInterface;
use JTL\Shop;

/**
 * Class Item
 * @package JTL\Consent
 */
class Item implements ItemInterface
{
    private int $id = 0;

    private int $pluginID = 0;

    private string $itemID = '';

    /**
     * @var string[]
     */
    private array $name = [];

    /**
     * @var string[]
     */
    private array $description = [];

    /**
     * @var string[]
     */
    private array $purpose = [];

    /**
     * @var string[]
     */
    private array $company = [];

    /**
     * @var string[]
     */
    private array $privacyPolicy = [];

    private int $currentLanguageID;

    private bool $active = false;

    public function __construct(?int $currentLanguageID = null)
    {
        $this->currentLanguageID = $currentLanguageID ?? Shop::getLanguageID();
    }

    /**
     * @param ConsentModel $model
     */
    public function loadFromModel(DataModelInterface $model): self
    {
        $this->setID($model->getId());
        $this->setItemID($model->getItemID());
        $this->setCompany($model->getCompany());
        $this->setPluginID($model->getPluginID());
        $this->setActive($model->getActive() === 1);
        foreach ($model->getLocalization() as $localization) {
            /** @var ConsentLocalizationModel $localization */
            $langID = $localization->getLanguageID();
            $this->setName($localization->getName(), $langID);
            $this->setPrivacyPolicy($localization->getPrivacyPolicy(), $langID);
            $this->setDescription($localization->getDescription(), $langID);
            $this->setPurpose($localization->getPurpose(), $langID);
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getID(): int
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     */
    public function setID(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @inheritdoc
     */
    public function getName(?int $idx = null): string
    {
        return $this->name[$idx ?? $this->currentLanguageID] ?? '';
    }

    /**
     * @inheritdoc
     */
    public function setName(string $name, ?int $idx = null): void
    {
        $this->name[$idx ?? $this->currentLanguageID] = $name;
    }

    /**
     * @inheritdoc
     */
    public function getDescription(?int $idx = null): string
    {
        return $this->description[$idx ?? $this->currentLanguageID] ?? '';
    }

    /**
     * @inheritdoc
     */
    public function setDescription(string $description, ?int $idx = null): void
    {
        $this->description[$idx ?? $this->currentLanguageID] = $description;
    }

    /**
     * @inheritdoc
     */
    public function getPurpose(?int $idx = null): string
    {
        return $this->purpose[$idx ?? $this->currentLanguageID] ?? '';
    }

    /**
     * @inheritdoc
     */
    public function setPurpose(string $purpose, ?int $idx = null): void
    {
        $this->purpose[$idx ?? $this->currentLanguageID] = $purpose;
    }

    /**
     * @inheritdoc
     */
    public function getCompany(?int $idx = null): string
    {
        return $this->company[$idx ?? $this->currentLanguageID] ?? '';
    }

    /**
     * @inheritdoc
     */
    public function setCompany(string $company, ?int $idx = null): void
    {
        $this->company[$idx ?? $this->currentLanguageID] = $company;
    }

    /**
     * @inheritdoc
     */
    public function getPrivacyPolicy(?int $idx = null): string
    {
        return $this->privacyPolicy[$idx ?? $this->currentLanguageID] ?? '';
    }

    /**
     * @inheritdoc
     */
    public function setPrivacyPolicy(string $tos, ?int $idx = null): void
    {
        $this->privacyPolicy[$idx ?? $this->currentLanguageID] = $tos;
    }

    /**
     * @inheritdoc
     */
    public function hasMoreInfo(): bool
    {
        return !empty($this->getPurpose()) || !empty($this->getCompany()) || !empty($this->getPrivacyPolicy());
    }

    /**
     * @inheritdoc
     */
    public function getCurrentLanguageID(): int
    {
        return $this->currentLanguageID;
    }

    /**
     * @inheritdoc
     */
    public function setCurrentLanguageID(int $currentLanguageID): void
    {
        $this->currentLanguageID = $currentLanguageID;
    }

    public function getItemID(): string
    {
        return $this->itemID;
    }

    public function setItemID(string $itemID): void
    {
        $this->itemID = $itemID;
    }

    public function getPluginID(): int
    {
        return $this->pluginID;
    }

    public function setPluginID(int $pluginID): void
    {
        $this->pluginID = $pluginID;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }
}
