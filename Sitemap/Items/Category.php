<?php

declare(strict_types=1);

namespace JTL\Sitemap\Items;

use JTL\Helpers\URL;
use JTL\Media\Image;
use JTL\Media\Image\Category as CategoryImage;

/**
 * Class Category
 * @package JTL\Sitemap\Items
 */
final class Category extends AbstractItem
{
    /**
     * @inheritdoc
     */
    public function generateImage(): void
    {
        if ($this->config['sitemap']['sitemap_images_categories'] !== 'Y') {
            return;
        }
        /** @var \stdClass|null $data */
        $data = $this->data ?? null;
        if ($data === null || empty($data->image)) {
            return;
        }
        $data->currentImagePath = $data->image;
        $image                  = CategoryImage::getThumb(
            Image::TYPE_CATEGORY,
            (int)$data->kKategorie,
            $data,
            Image::SIZE_LG
        );
        if (\mb_strlen($image) > 0) {
            $this->setImage($this->baseImageURL . $image);
        }
    }

    /**
     * @inheritdoc
     */
    public function generateLocation(): void
    {
        $this->setLocation(
            URL::buildURL(
                $this->data,
                \URLART_KATEGORIE,
                true,
                $this->baseURL,
                $this->languageCode639
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function generateData(mixed $data, array $languages): void
    {
        $this->setData($data);
        $this->setPrimaryKeyID((int)$data->kKategorie);
        $this->setLanguageData($languages, (int)$data->langID);
        $this->generateImage();
        $this->generateLocation();
        $this->setChangeFreq(\FREQ_WEEKLY);
        $this->setPriority(\PRIO_NORMAL);
        $this->setLastModificationTime((new \DateTimeImmutable($data->dlm))->format('c'));
    }
}
