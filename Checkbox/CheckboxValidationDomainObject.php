<?php

declare(strict_types=1);

namespace JTL\Checkbox;

use JTL\DataObjects\AbstractDomainObject;

/**
 * Class CheckboxValidationDomainObject
 * @package JTL\Checkbox
 */
class CheckboxValidationDomainObject extends AbstractDomainObject
{
    public function __construct(
        protected readonly int $customerGroupId = 0,
        protected readonly int $location = 0,
        protected readonly bool $active = false,
        protected readonly bool $logging = false,
        protected readonly bool $language = false,
        protected readonly bool $special = false,
        protected readonly bool $hasDownloads = false,
        array $modifiedKeys = []
    ) {
        parent::__construct($modifiedKeys);
    }

    /**
     * @return array<string, string>
     */
    private function getMappingArray(): array
    {
        return [
            'customerGroupId' => 'customerGroupId',
            'kKundengruppe'   => 'customerGroupId',
            'location'        => 'location',
            'language'        => 'language',
            'active'          => 'active',
            'logging'         => 'logging',
            'special'         => 'special',
            'modifiedKeys'    => 'modifiedKeys',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getColumnMappingArray(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function getMapping(): array
    {
        return \array_merge($this->getMappingArray(), $this->getColumnMappingArray());
    }

    /**
     * @return array<string, string>
     */
    public function getReverseMapping(): array
    {
        return \array_flip($this->getMappingArray());
    }

    /**
     * @return array<string, string>
     */
    public function getColumnMapping(): array
    {
        return \array_flip($this->getColumnMappingArray());
    }

    public function getCustomerGroupId(): int
    {
        return $this->customerGroupId;
    }

    public function getLocation(): int
    {
        return $this->location;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function getLogging(): bool
    {
        return $this->logging;
    }

    public function getSpecial(): bool
    {
        return $this->special;
    }

    public function getHasDownloads(): bool
    {
        return $this->hasDownloads;
    }

    public function getLanguage(): bool
    {
        return $this->language;
    }
}
