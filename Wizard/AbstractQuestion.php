<?php

declare(strict_types=1);

namespace JTL\Backend\Wizard;

use JsonSerializable;
use JTL\Backend\Wizard\Steps\ErrorCode;
use JTL\DB\DbInterface;
use JTL\Session\Backend;
use JTL\Update\MigrationTableTrait;
use JTL\Update\MigrationTrait;
use stdClass;

/**
 * Class AbstractQuestion
 * @package JTL\Backend\Wizard
 */
abstract class AbstractQuestion implements JsonSerializable, QuestionInterface
{
    use MigrationTableTrait;
    use MigrationTrait;

    protected int $id = 0;

    protected ?string $text = null;

    protected ?string $description = null;

    protected ?string $subheading = null;

    protected ?string $subheadingDescription = null;

    protected ?string $summaryText = null;

    protected ?string $label = null;

    protected int $type = 0;

    protected mixed $value;

    protected ?int $dependency = null;

    /**
     * @var callable
     */
    protected $onSave;

    /**
     * @var SelectOption[]
     */
    protected array $options = [];

    protected bool $multiSelect = false;

    protected bool $required = true;

    protected bool $fullWidth = false;

    /**
     * @var callable
     */
    protected $validation;

    protected ?string $scope = null;

    public function __construct(DbInterface $db)
    {
        $this->setDB($db);
        $this->setValidation();
    }

    /**
     * @inheritdoc
     */
    public function answerFromPost(array $post): mixed
    {
        $data = $post['question-' . $this->getID()] ?? null;
        if ($this->getType() === QuestionType::BOOL) {
            $value = $data === 'on';
        } else {
            $value = $data ?? '';
        }
        $this->setValue($value, false);

        return $value;
    }

    /**
     * @inheritdoc
     */
    public function updateConfig(string $configName, mixed $value): int
    {
        return $this->db->update('teinstellungen', 'cName', $configName, (object)['cWert' => $value]);
    }

    /**
     * @inheritdoc
     */
    public function save(): int
    {
        if (($validationError = $this->validate()) !== ErrorCode::OK) {
            return $validationError;
        }
        $cb = $this->getOnSave();
        if (\is_callable($cb)) {
            $cb($this);
        }

        return ErrorCode::OK;
    }

    /**
     * @inheritdoc
     */
    public function loadAnswer(array $data): void
    {
        $value = $data[$this->getID()] ?? null;
        if ($value !== null) {
            $this->setValue($value);
        }
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
    public function getText(): ?string
    {
        return $this->text;
    }

    /**
     * @inheritdoc
     */
    public function setText(string $text): void
    {
        $this->text = $text;
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @inheritdoc
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * @inheritdoc
     */
    public function getSubheading(): ?string
    {
        return $this->subheading;
    }

    /**
     * @inheritdoc
     */
    public function setSubheading(string $subheading): void
    {
        $this->subheading = $subheading;
    }

    /**
     * @inheritdoc
     */
    public function getSubheadingDescription(): ?string
    {
        return $this->subheadingDescription;
    }

    /**
     * @inheritdoc
     */
    public function setSubheadingDescription(string $subheadingDescription): void
    {
        $this->subheadingDescription = $subheadingDescription;
    }

    /**
     * @inheritdoc
     */
    public function getSummaryText(): ?string
    {
        return $this->summaryText;
    }

    /**
     * @inheritdoc
     */
    public function setSummaryText(string $summaryText): void
    {
        $this->summaryText = $summaryText;
    }

    /**
     * @inheritdoc
     */
    public function getLabel(): ?string
    {
        return $this->label;
    }

    /**
     * @inheritdoc
     */
    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    /**
     * @inheritdoc
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * @inheritdoc
     */
    public function setType(int $type): void
    {
        $this->type = $type;
    }

    /**
     * @inheritdoc
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * @inheritdoc
     */
    public function setValue(mixed $value, bool $sessionFirst = true): void
    {
        /** @var array<string, mixed>|null $wizard */
        $wizard = Backend::get('wizard');
        $idx    = 'question-' . $this->getID();
        if ($sessionFirst && isset($wizard[$idx])) {
            $this->value = $wizard[$idx];
        } else {
            $this->value = $value;
        }
    }

    /**
     * @inheritdoc
     */
    public function getDependency(): ?int
    {
        return $this->dependency;
    }

    /**
     * @inheritdoc
     */
    public function setDependency(int $dependency): void
    {
        $this->dependency = $dependency;
    }

    /**
     * @inheritdoc
     */
    public function getOnSave(): ?callable
    {
        return $this->onSave;
    }

    /**
     * @inheritdoc
     */
    public function setOnSave(callable $onSave): void
    {
        $this->onSave = $onSave;
    }

    /**
     * @inheritdoc
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @inheritdoc
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    /**
     * @inheritdoc
     */
    public function addOption(SelectOption $option): void
    {
        $this->options[] = $option;
    }

    /**
     * @inheritdoc
     */
    public function isMultiSelect(): bool
    {
        return $this->multiSelect;
    }

    /**
     * @inheritdoc
     */
    public function setIsMultiSelect(bool $multi): void
    {
        $this->multiSelect = $multi;
    }

    /**
     * @inheritdoc
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * @inheritdoc
     */
    public function setIsRequired(bool $required): void
    {
        $this->required = $required;
    }

    /**
     * @inheritdoc
     */
    public function isFullWidth(): bool
    {
        return $this->fullWidth;
    }

    /**
     * @inheritdoc
     */
    public function setIsFullWidth(bool $fullWidth): void
    {
        $this->fullWidth = $fullWidth;
    }

    /**
     * @inheritdoc
     */
    public function setValidation(?callable $validation = null): void
    {
        $this->validation = $validation ?? static function (QuestionInterface $question): int {
            return (new QuestionValidation($question))->getValidationError();
        };
    }

    /**
     * @inheritdoc
     */
    public function getValidation(): callable
    {
        return $this->validation;
    }

    /**
     * @inheritdoc
     */
    public function validate(): int
    {
        return $this->getValidation()($this);
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize(): stdClass
    {
        $data = new stdClass();
        foreach (\get_object_vars($this) as $k => $v) {
            $data->$k = $v;
        }

        return $data;
    }

    public function getScope(): ?string
    {
        return $this->scope;
    }

    public function setScope(string $scope): void
    {
        $this->scope = $scope;
    }
}
