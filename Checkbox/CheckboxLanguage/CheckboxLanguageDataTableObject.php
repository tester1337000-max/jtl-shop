<?php

declare(strict_types=1);

namespace JTL\Checkbox\CheckboxLanguage;

use JTL\DataObjects\AbstractDataObject;
use JTL\DataObjects\DataTableObjectInterface;

/**
 * Class CheckboxLanguageDataTableObject
 * @package JTL\Checkbox\CheckboxLanguage
 */
class CheckboxLanguageDataTableObject extends AbstractDataObject implements DataTableObjectInterface
{
    private string $primaryKey = 'kCheckBoxSprache';

    protected int $checkboxLanguageID = 0;

    protected int $checkboxID = 0;

    protected int $languageID = 0;

    protected string $text = '';

    protected string $description = '';

    /**
     * @var array<string, string>
     */
    private static array $mapping = [
        'checkboxLanguageID' => 'checkboxLanguageID',
        'checkboxID'         => 'checkboxID',
        'languageID'         => 'languageID',
        'text'               => 'text',
        'description'        => 'description',
    ];

    /**
     * @var array<string, string>
     */
    private static array $columnMapping = [
        'kCheckBoxSprache' => 'checkboxLanguageID',
        'kCheckBox'        => 'checkboxID',
        'kSprache'         => 'languageID',
        'cText'            => 'text',
        'cBeschreibung'    => 'description',
    ];

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    /**
     * @inheritdoc
     */
    public function getMapping(): array
    {
        return \array_merge(self::$mapping, self::$columnMapping);
    }

    /**
     * @inheritdoc
     */
    public function getReverseMapping(): array
    {
        return \array_flip(self::$mapping);
    }

    /**
     * @inheritdoc
     */
    public function getColumnMapping(): array
    {
        return \array_flip(self::$columnMapping);
    }

    /**
     * @inheritdoc
     */
    public function getID(): mixed
    {
        return $this->{$this->getPrimaryKey()};
    }

    public function getCheckboxLanguageID(): int
    {
        return $this->checkboxLanguageID;
    }

    public function setCheckboxLanguageID(int|string $checkboxLanguageID): CheckboxLanguageDataTableObject
    {
        $this->checkboxLanguageID = (int)$checkboxLanguageID;

        return $this;
    }

    public function getCheckboxID(): int
    {
        return $this->checkboxID;
    }

    public function setCheckboxID(int|string $checkboxID): CheckboxLanguageDataTableObject
    {
        $this->checkboxID = (int)$checkboxID;

        return $this;
    }

    public function getLanguageID(): int
    {
        return $this->languageID;
    }

    public function setLanguageID(int|string $languageID): CheckboxLanguageDataTableObject
    {
        $this->languageID = (int)$languageID;

        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): CheckboxLanguageDataTableObject
    {
        $this->text = $text;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): CheckboxLanguageDataTableObject
    {
        $this->description = $description;

        return $this;
    }
}
