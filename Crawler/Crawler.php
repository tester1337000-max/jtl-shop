<?php

declare(strict_types=1);

namespace JTL\Crawler;

use JTL\MagicCompatibilityTrait;

/**
 * Class Crawler
 * @package JTL\Crawler
 */
class Crawler
{
    use MagicCompatibilityTrait;

    /**
     * @var array<string, string>
     */
    protected static array $mapping = [
        'kBesucherBot'  => 'ID',
        'cName'         => 'Name',
        'cUserAgent'    => 'UserAgent',
        'cBeschreibung' => 'Description',
        'cLink'         => 'Link',
    ];

    private int $id = 0;

    private ?string $name = null;

    private string $useragent = '';

    private string $description = '';

    private ?string $link = null;

    /**
     * @param \stdClass[] $crawlers
     */
    public function map(array $crawlers): self
    {
        foreach ($crawlers as $crawler) {
            $this->setID((int)$crawler->kBesucherBot);
            $this->setDescription($crawler->cBeschreibung);
            $this->setUserAgent($crawler->cUserAgent);
            $this->setName($crawler->cName);
            $this->setLink($crawler->cLink);
        }

        return $this;
    }

    public function getID(): int
    {
        return $this->id;
    }

    public function setID(int $id): void
    {
        $this->id = $id;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getUserAgent(): string
    {
        return $this->useragent;
    }

    public function setUserAgent(string $useragent): void
    {
        $this->useragent = $useragent;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): void
    {
        $this->link = $link;
    }
}
