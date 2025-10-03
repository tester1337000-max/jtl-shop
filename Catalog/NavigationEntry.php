<?php

declare(strict_types=1);

namespace JTL\Catalog;

use JTL\MagicCompatibilityTrait;

/**
 * Class NavigationEntry
 * @package JTL\Catalog
 */
class NavigationEntry
{
    use MagicCompatibilityTrait;

    /**
     * @var array<string, string>
     */
    protected static array $mapping = [
        'id'       => 'ID',
        'name'     => 'Name',
        'url'      => 'URL',
        'urlFull'  => 'URLFull',
        'hasChild' => 'HasChild',
    ];

    private int $id = 0;

    private string $name;

    private string $url;

    private string $urlFull;

    private bool $hasChild = false;

    public function getID(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setID(int $id): void
    {
        $this->id = $id;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setURL(string $url): void
    {
        $this->url = $url;
    }

    public function getURLFull(): string
    {
        return $this->urlFull;
    }

    public function setURLFull(string $url): void
    {
        $this->urlFull = $url;
    }

    public function getHasChild(): bool
    {
        return $this->hasChild;
    }

    public function setHasChild(bool $hasChild): void
    {
        $this->hasChild = $hasChild;
    }
}
