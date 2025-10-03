<?php

declare(strict_types=1);

namespace JTL\Sitemap\Items;

use JTL\Helpers\URL;
use JTL\Media\Image;
use JTL\Media\Image\NewsCategory as NewsCategoryImage;

/**
 * Class NewsCategory
 * @package JTL\Sitemap\Items
 */
final class NewsCategory extends AbstractItem
{
    /**
     * @inheritdoc
     */
    public function generateImage(): void
    {
        if ($this->config['sitemap']['sitemap_images_newscategory_items'] !== 'Y') {
            return;
        }
        /** @var \stdClass|null $data */
        $data = $this->data ?? null;
        if ($data === null || empty($data->image)) {
            return;
        }
        $data->image = \str_replace(\PFAD_NEWSKATEGORIEBILDER, '', $data->image);
        $image       = NewsCategoryImage::getThumb(
            Image::TYPE_NEWSCATEGORY,
            (int)$data->kNewsKategorie,
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
                \URLART_NEWSKATEGORIE,
                false,
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
        $this->setPrimaryKeyID((int)$data->kNewsKategorie);
        $this->setLanguageData($languages, (int)$data->langID);
        $this->generateImage();
        $this->generateLocation();
        $this->setChangeFreq(\FREQ_DAILY);
        $this->setPriority(\PRIO_HIGH);
        $this->setLastModificationTime((new \DateTimeImmutable($data->dlm))->format('c'));
    }
}
