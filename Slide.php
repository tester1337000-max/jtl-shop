<?php

declare(strict_types=1);

namespace JTL;

use JTL\DB\DbInterface;
use stdClass;

/**
 * Class Slide
 * @package JTL
 */
class Slide
{
    use MagicCompatibilityTrait;

    private int $id = 0;

    private int $sliderID = 0;

    private string $title = '';

    private string $image = '';

    private string $text = '';

    private string $thumbnail = '';

    private string $link = '';

    private int $sort = 0;

    private string $absoluteImage = '';

    private string $absoluteThumbnail = '';

    private DbInterface $db;

    /**
     * @var array<string, string>
     */
    private static array $mapping = [
        'kSlide'            => 'ID',
        'kSlider'           => 'SliderID',
        'cTitel'            => 'Title',
        'cBild'             => 'Image',
        'cText'             => 'Text',
        'cThumbnail'        => 'Thumbnail',
        'cLink'             => 'Link',
        'nSort'             => 'Sort',
        'cBildAbsolut'      => 'AbsoluteImage',
        'cThumbnailAbsolut' => 'AbsoluteThumbnail'
    ];

    private function __clone()
    {
    }

    private function getMapping(string $value): ?string
    {
        return self::$mapping[$value] ?? null;
    }

    public function __construct(int $id = 0, ?DbInterface $db = null)
    {
        $this->db = $db ?? Shop::Container()->getDB();
        if ($id > 0) {
            $this->load($id);
        }
    }

    public function load(int $id = 0): bool
    {
        if ($id > 0 || $this->id > 0) {
            if ($id === 0) {
                $id = $this->id;
            }

            $slide = $this->db->select('tslide', 'kSlide', $id);
            if ($slide !== null) {
                $this->set($slide);

                return true;
            }
        }

        return false;
    }

    public function map(stdClass $data): self
    {
        foreach (\get_object_vars($data) as $field => $value) {
            if (($mapping = $this->getMapping($field)) !== null) {
                $method = 'set' . $mapping;
                $this->$method($value);
            }
        }
        $this->setAbsoluteImagePaths();

        return $this;
    }

    public function set(stdClass $data): self
    {
        foreach (\get_object_vars($data) as $field => $value) {
            if (($mapping = $this->getMapping($field)) !== null) {
                $method = 'set' . $mapping;
                $this->$method($value);
            }
        }

        return $this;
    }

    private function setAbsoluteImagePaths(): self
    {
        $basePath                = Shop::getImageBaseURL();
        $this->absoluteImage     = \str_starts_with($this->image, 'http://')
        || \str_starts_with($this->image, 'https://')
            ? $this->image
            : $basePath . $this->image;
        $this->absoluteThumbnail = \str_starts_with($this->thumbnail, 'http:')
        || \str_starts_with($this->thumbnail, 'https:')
            ? $this->thumbnail
            : $basePath . $this->thumbnail;

        return $this;
    }

    public function save(): bool
    {
        if (!empty($this->image)) {
            if (\str_starts_with($this->image, 'Bilder/')) {
                $this->setThumbnail(\PFAD_MEDIAFILES . 'Bilder/.tmb/' . \basename($this->getThumbnail()));
            } else {
                $this->setThumbnail(\STORAGE_OPC . '.tmb/' . \basename($this->getThumbnail()));
            }
            $shopURL = Shop::getURL();
            $path    = \parse_url($shopURL . '/', \PHP_URL_PATH) ?: 'invalid';
            if (\str_starts_with($this->image, $shopURL)) {
                $this->image = \ltrim(\substr($this->image, \mb_strlen($shopURL)), '/');
            } elseif (\str_starts_with($this->image, $path)) {
                $this->image = \ltrim(\substr($this->image, \mb_strlen($path)), '/');
            }
        }

        return $this->id === 0
            ? $this->append()
            : $this->update() > 0;
    }

    private function update(): int
    {
        $slide = new stdClass();
        if (!empty($this->getThumbnail())) {
            $slide->cThumbnail = $this->getThumbnail();
        }
        $slide->kSlider = $this->getSliderID();
        $slide->cTitel  = $this->getTitle();
        $slide->cBild   = $this->getImage();
        $slide->nSort   = $this->getSort();
        $slide->cLink   = $this->getLink();
        $slide->cText   = $this->getText();

        return $this->db->update('tslide', 'kSlide', $this->getID(), $slide);
    }

    private function append(): bool
    {
        if (empty($this->image)) {
            return false;
        }
        $slide = new stdClass();
        foreach (self::$mapping as $type => $methodName) {
            $method       = 'get' . $methodName;
            $slide->$type = $this->$method();
        }
        unset($slide->cBildAbsolut, $slide->cThumbnailAbsolut, $slide->kSlide);
        if ($this->sort === 0) {
            $sort         = $this->db->getSingleObject(
                'SELECT nSort
                    FROM tslide
                    WHERE kSlider = :sliderID
                    ORDER BY nSort DESC LIMIT 1',
                ['sliderID' => $this->sliderID]
            );
            $slide->nSort = ($sort === null || (int)$sort->nSort === 0) ? 1 : ($sort->nSort + 1);
        }
        $id = $this->db->insert('tslide', $slide);
        if ($id > 0) {
            $this->id = $id;

            return true;
        }

        return false;
    }

    public function delete(): bool
    {
        return $this->id > 0 && $this->db->delete('tslide', 'kSlide', $this->id) > 0;
    }

    public function getID(): int
    {
        return $this->id;
    }

    public function setID(int|string $id): void
    {
        $this->id = (int)$id;
    }

    public function getSliderID(): int
    {
        return $this->sliderID;
    }

    public function setSliderID(int|string $sliderID): void
    {
        $this->sliderID = (int)$sliderID;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getImage(): string
    {
        return $this->image;
    }

    public function setImage(string $image): void
    {
        $this->image = $image;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): void
    {
        $this->text = $text;
    }

    public function getThumbnail(): string
    {
        return $this->thumbnail;
    }

    public function setThumbnail(string $thumbnail): void
    {
        $this->thumbnail = $thumbnail;
    }

    public function getLink(): string
    {
        return $this->link;
    }

    public function setLink(string $link): void
    {
        $this->link = $link;
    }

    public function getSort(): int
    {
        return $this->sort;
    }

    public function setSort(int|string $sort): void
    {
        $this->sort = (int)$sort;
    }

    public function getAbsoluteImage(): string
    {
        return $this->absoluteImage;
    }

    public function setAbsoluteImage(string $absoluteImage): void
    {
        $this->absoluteImage = $absoluteImage;
    }

    public function getAbsoluteThumbnail(): string
    {
        return $this->absoluteThumbnail;
    }

    public function setAbsoluteThumbnail(string $absoluteThumbnail): void
    {
        $this->absoluteThumbnail = $absoluteThumbnail;
    }
}
