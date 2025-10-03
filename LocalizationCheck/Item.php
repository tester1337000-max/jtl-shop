<?php

declare(strict_types=1);

namespace JTL\Backend\LocalizationCheck;

use stdClass;

/**
 * Class Item
 * @package JTL\Backend\LocalizationCheck
 */
class Item
{
    private int $langID;

    private int $id;

    private string $name;

    private ?string $additional;

    public function __construct(stdClass $data)
    {
        $this->langID     = (int)$data->langID;
        $this->id         = (int)$data->id;
        $this->name       = $data->name;
        $this->additional = $data->additional ?? null;
        if (($data->productName ?? null) !== null) {
            $this->name .= \sprintf(' (%s: %s)', \__('product'), $data->productName);
        }
    }

    public function getLanguageID(): int
    {
        return $this->langID;
    }

    public function setLanguageID(int $langID): void
    {
        $this->langID = $langID;
    }

    public function getID(): int
    {
        return $this->id;
    }

    public function setID(int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getAdditional(): ?string
    {
        return $this->additional;
    }

    public function setAdditional(?string $additional): void
    {
        $this->additional = $additional;
    }
}
