<?php

declare(strict_types=1);

namespace JTL\Checkbox\CheckboxLanguage;

use JTL\DataObjects\AbstractDomainObject;
use JTL\DataObjects\DataTableObjectInterface;

/**
 * Class CheckboxLanguageDomainObject
 * @package JTL\Checkbox\CheckboxLanguage
 */
class CheckboxLanguageDomainObject extends AbstractDomainObject implements DataTableObjectInterface
{
    private string $primaryKey;

    public function __construct(
        protected readonly int $checkboxID,
        protected readonly int $checkboxLanguageID,
        protected readonly int $languageID,
        private readonly string $iso,
        protected readonly string $text,
        protected readonly string $description,
        array $modifiedKeys = []
    ) {
        $this->primaryKey = 'kCheckBoxSprache';

        parent::__construct($modifiedKeys);
    }

    /**
     * @return array<string, string>
     */
    private function getMappingArray(): array
    {
        return [
            'checkboxLanguageID' => 'checkboxLanguageID',
            'checkboxID'         => 'checkboxID',
            'languageID'         => 'languageID',
            'text'               => 'text',
            'description'        => 'description',
            'ISO'                => 'ISO,'
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getColumnMappingArray(): array
    {
        return [
            'kCheckBoxSprache' => 'checkboxLanguageID',
            'kCheckBox'        => 'checkboxID',
            'kSprache'         => 'languageID',
            'cText'            => 'text',
            'cBeschreibung'    => 'description',
            'modifiedKeys'     => 'modifiedKeys',
        ];
    }

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
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
     * @inheritdoc
     */
    public function getColumnMapping(): array
    {
        return \array_flip($this->getColumnMappingArray());
    }

    /**
     * @inheritdoc
     */
    public function getID(): int
    {
        return $this->{$this->getPrimaryKey()};
    }

    public function getCheckboxLanguageID(): int
    {
        return $this->checkboxLanguageID;
    }

    public function getCheckboxID(): int
    {
        return $this->checkboxID;
    }

    public function getLanguageID(): int
    {
        return $this->languageID;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getIso(): string
    {
        return $this->iso;
    }
}
