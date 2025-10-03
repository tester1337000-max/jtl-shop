<?php

declare(strict_types=1);

namespace JTL\Backend\ShippingClassWizard;

use InvalidArgumentException;
use JsonException;
use JsonSerializable;

/**
 * Class Definition
 * @package JTL\Backend\ShippingClassWizard
 */
final class Definition implements JsonSerializable
{
    private string $combinationType;

    /**
     * @var DefinitionPart[]
     */
    private array $definitionParts = [];

    private string $logic;

    private bool $inverted;

    private string $resultHash = '';

    private function __construct(
        string $combinationType = CombineTypes::ALL,
        string $logic = CombineTypes::LOGIC_OR,
        bool $inverted = false
    ) {
        $this->setCombinationType($combinationType);
        $this->setLogic($logic);
        $this->setInverted($inverted);
    }

    /**
     * @throws JsonException
     */
    public static function jsonDecode(string $jsonStr, string $resultHash): self
    {
        $data     = \json_decode($jsonStr, false, 64, \JSON_THROW_ON_ERROR);
        $instance = new self(
            $data->combinationType ?? CombineTypes::ALL,
            $data->logic ?? CombineTypes::LOGIC_OR,
            $data->inverted ?? false
        );
        foreach (($data->definitionParts ?? []) as $part) {
            $instance->addDefinitionPart(
                DefinitionPart::jsonDecode(\json_encode($part, \JSON_THROW_ON_ERROR))
            );
        }

        return $instance->setResultHash($resultHash);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function createFromForm(array $data, string $resultHash = ''): self
    {
        $instance = new self();

        $show = $data['show'] ?? 'ever';
        $cond = $data['condition'] ?? 'or';
        $excl = $data['exclusive'] ?? 'inclusive';

        if ($show === 'ever') {
            $instance->setCombinationType(CombineTypes::ALL);
            $data['combi'] = [];
        } else {
            $instance->setInverted($show === 'not')
                ->setLogic(
                    $cond === 'and'
                        ? CombineTypes::LOGIC_AND
                        : CombineTypes::LOGIC_OR
                )
                ->setCombinationType(
                    $excl === 'inclusive'
                        ? CombineTypes::COMBINE_ALL
                        : CombineTypes::COMBINE_SINGLE
                );
            if ($cond === 'xor') {
                $instance->setCombinationType(CombineTypes::EXCLUSIVE);
            }
        }

        foreach (($data['combi'] ?? []) as $combi) {
            $combi['logic'] = $cond === 'and' ? CombineTypes::LOGIC_OR : CombineTypes::LOGIC_AND;
            $instance->addDefinitionPart(DefinitionPart::createFromForm($combi));
        }

        return $instance->setResultHash($resultHash);
    }

    public static function createEmpty(string $classIds = ''): self
    {
        $instance = new self(CombineTypes::ALL, CombineTypes::LOGIC_OR);
        if ($classIds !== '' && !\str_starts_with($classIds, '-')) {
            $instance->setCombinationType(CombineTypes::EXCLUSIVE);
            $parts = \explode(' ', $classIds);
            foreach ($parts as $Ids) {
                $instance->addDefinitionPart(DefinitionPart::createFromClassIds($Ids));
            }
        }

        return $instance->setResultHash('');
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize(): object
    {
        return (object)[
            'combinationType' => $this->getCombinationType(),
            'logic'           => $this->getLogic(),
            'inverted'        => $this->isInverted(),
            'definitionParts' => $this->getDefinitionParts(),
        ];
    }

    public function mapShowing(): string
    {
        if ($this->getCombinationType() === CombineTypes::ALL) {
            return 'ever';
        }

        return $this->isInverted() ? 'not' : 'show';
    }

    public function mapCondition(): string
    {
        if ($this->getLogic() === CombineTypes::LOGIC_AND) {
            return 'and';
        }

        return $this->getCombinationType() === CombineTypes::EXCLUSIVE ? 'xor' : 'or';
    }

    public function mapExclusive(): string
    {
        return $this->getCombinationType() === CombineTypes::COMBINE_ALL ? 'inclusive' : 'exclusive';
    }

    public function getResultHash(): string
    {
        return $this->resultHash;
    }

    public function setResultHash(string $resultHash): self
    {
        $this->resultHash = $resultHash;

        return $this;
    }

    public function isEqualHash(string $resultHash): bool
    {
        return $resultHash === $this->getResultHash();
    }

    public function getCombinationType(): string
    {
        return $this->combinationType;
    }

    public function setCombinationType(string $combinationType): self
    {
        if (!\in_array($combinationType, CombineTypes::ALL_TYPES, true)) {
            throw new InvalidArgumentException('no valid combination type');
        }

        $this->combinationType = $combinationType;

        return $this;
    }

    public function getLogic(): string
    {
        return $this->logic;
    }

    public function setLogic(string $logic): self
    {
        if (!\in_array($logic, [CombineTypes::LOGIC_OR, CombineTypes::LOGIC_AND], true)) {
            throw new InvalidArgumentException(
                'logic must be "' . CombineTypes::LOGIC_OR . '" or "' . CombineTypes::LOGIC_AND . '"'
            );
        }
        $this->logic = $logic;

        return $this;
    }

    public function isInverted(): bool
    {
        return $this->inverted;
    }

    public function setInverted(bool $inverted): self
    {
        $this->inverted = $inverted;

        return $this;
    }

    /**
     * @return DefinitionPart[]
     */
    public function getDefinitionParts(): array
    {
        return $this->definitionParts;
    }

    public function addDefinitionPart(DefinitionPart $definitionPart): self
    {
        $this->definitionParts[] = $definitionPart;

        return $this;
    }

    public function hasDefinitionParts(): bool
    {
        return \count($this->getDefinitionParts()) > 0;
    }

    public function isLogicOr(): bool
    {
        return $this->getLogic() === CombineTypes::LOGIC_OR;
    }

    public function isLogicAnd(): bool
    {
        return $this->getLogic() === CombineTypes::LOGIC_AND;
    }

    /**
     * @return array<array<mixed>>
     */
    public function getAllClassDefinitions(): array
    {
        return \array_values(
            \array_map(
                static fn(DefinitionPart $item) => \array_values($item->getShippingClasses()),
                $this->getDefinitionParts()
            )
        );
    }
}
