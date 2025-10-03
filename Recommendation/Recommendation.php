<?php

declare(strict_types=1);

namespace JTL\Recommendation;

use JTL\Helpers\Text;
use JTL\License\Struct\Link;
use Parsedown;
use stdClass;

/**
 * Class Recommendation
 * @package JTL\Recommendation
 */
class Recommendation
{
    private string $id;

    private string $previewImage;

    private string $title;

    private string $description = '';

    /**
     * @var string[]
     */
    private array $images;

    private string $teaser = '';

    /**
     * @var string[]
     */
    private array $benefits;

    private string $setupDescription = '';

    private Manufacturer $manufacturer;

    private string $url;

    /**
     * @var Link[]
     */
    private array $links = [];

    public Parsedown $parseDown;

    public function __construct(stdClass $recommendation)
    {
        $this->parseDown = new Parsedown();

        $this->setId($recommendation->id);
        $this->setDescription($recommendation->description);
        $this->setTitle($recommendation->name);
        $this->setPreviewImage($recommendation->preview_url);
        $this->setBenefits($recommendation->benefits);
        $this->setSetupDescription($recommendation->installation_description);
        $this->setImages($recommendation->images);
        $this->setTeaser($recommendation->teaser);
        $this->setManufacturer(new Manufacturer($recommendation->seller));
        $this->setUrl($recommendation->url);
        $this->setLinks($recommendation->links);
    }

    public function parseDown(string $text): string
    {
        return $this->setLinkTargets(\html_entity_decode($this->parseDown->text(Text::convertUTF8($text))));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getPreviewImage(): string
    {
        return $this->previewImage;
    }

    public function setPreviewImage(string $previewImage): void
    {
        $this->previewImage = $previewImage;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $this->parseDown($description);
    }

    /**
     * @return string[]
     */
    public function getImages(): array
    {
        return $this->images;
    }

    /**
     * @param string[] $images
     */
    public function setImages(array $images): void
    {
        $this->images = $images;
    }

    public function getTeaser(): string
    {
        return $this->teaser;
    }

    public function setTeaser(string $teaser): void
    {
        $this->teaser = $this->parseDown($teaser);
    }

    /**
     * @return string[]
     */
    public function getBenefits(): array
    {
        return $this->benefits;
    }

    /**
     * @param string[] $benefits
     */
    public function setBenefits(array $benefits): void
    {
        $this->benefits = $benefits;
    }

    public function getSetupDescription(): string
    {
        return $this->setupDescription;
    }

    public function setSetupDescription(string $setupDescription): void
    {
        $this->setupDescription = $this->parseDown($setupDescription);
    }

    public function getManufacturer(): Manufacturer
    {
        return $this->manufacturer;
    }

    public function setManufacturer(Manufacturer $manufacturer): void
    {
        $this->manufacturer = $manufacturer;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    /**
     * @return Link[]
     */
    public function getLinks(): array
    {
        return $this->links;
    }

    /**
     * @param stdClass[] $links
     */
    public function setLinks(array $links): void
    {
        foreach ($links as $link) {
            $this->links[] = new Link($link);
        }
    }

    public function setLinkTargets(string $text): string
    {
        return \str_replace('<a ', '<a target="_blank" ', $text);
    }
}
