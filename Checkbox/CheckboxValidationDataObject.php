<?php

declare(strict_types=1);

namespace JTL\Checkbox;

use JTL\DataObjects\AbstractDataObject;

/**
 * Class CheckboxValidationDataObject
 * @package JTL\Checkbox
 */
class CheckboxValidationDataObject extends AbstractDataObject
{
    protected int $customerGroupId = 0;

    protected int $location = 0;

    protected bool $active = false;

    protected bool $logging = false;

    protected bool $language = false;

    protected bool $special = false;

    protected bool $hasDownloads = false;

    /**
     * @var array<string, string>
     */
    private static array $mapping = [
        'customerGroupId' => 'customerGroupId',
        'kKundengruppe'   => 'customerGroupId',
        'location'        => 'location',
        'language'        => 'language',
        'active'          => 'active',
        'logging'         => 'logging',
        'special'         => 'special',
    ];

    /**
     * @var array<string, string>
     */
    private array $columnMapping = [];

    /**
     * @inheritdoc
     */
    public function getMapping(): array
    {
        return \array_merge(self::$mapping, $this->columnMapping);
    }

    /**
     * @inheritdoc
     */
    public function getReverseMapping(): array
    {
        return \array_flip(self::$mapping);
    }

    /**
     * @return array<string, string>
     */
    public function getColumnMapping(): array
    {
        return \array_flip($this->columnMapping);
    }

    public function getCustomerGroupId(): int
    {
        return $this->customerGroupId;
    }

    public function setCustomerGroupId(int $customerGroupId): CheckboxValidationDataObject
    {
        $this->customerGroupId = $customerGroupId;

        return $this;
    }

    public function getLocation(): int
    {
        return $this->location;
    }

    public function setLocation(int $location): CheckboxValidationDataObject
    {
        $this->location = $location;

        return $this;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool|int|string $active): CheckboxValidationDataObject
    {
        $this->active = $this->checkAndReturnBoolValue($active);

        return $this;
    }

    public function getLogging(): bool
    {
        return $this->logging;
    }

    public function setLogging(bool|int|string $logging): CheckboxValidationDataObject
    {
        $this->logging = $this->checkAndReturnBoolValue($logging);

        return $this;
    }

    public function getSpecial(): bool
    {
        return $this->special;
    }

    public function setSpecial(bool|int|string $special): CheckboxValidationDataObject
    {
        $this->special = $this->checkAndReturnBoolValue($special);

        return $this;
    }

    public function getHasDownloads(): bool
    {
        return $this->hasDownloads;
    }

    public function setHasDownloads(bool|int|string $hasDownloads): CheckboxValidationDataObject
    {
        $this->hasDownloads = $this->checkAndReturnBoolValue($hasDownloads);

        return $this;
    }

    public function getLanguage(): bool
    {
        return $this->language;
    }

    public function setLanguage(bool|int|string $language): CheckboxValidationDataObject
    {
        $this->language = $this->checkAndReturnBoolValue($language);

        return $this;
    }
}
