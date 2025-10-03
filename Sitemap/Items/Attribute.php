<?php

declare(strict_types=1);

namespace JTL\Sitemap\Items;

use JTL\Helpers\URL;
use JTL\Media\Image;
use JTL\Media\Image\CharacteristicValue as CharacteristicValueImage;

/**
 * Class Attribute
 * @package JTL\Sitemap\Items
 */
final class Attribute extends AbstractItem
{
    /**
     * @inheritdoc
     */
    public function generateImage(): void
    {
        if ($this->config['sitemap']['sitemap_images_attributes'] !== 'Y') {
            return;
        }
        /** @var \stdClass|null $data */
        $data = $this->data ?? null;
        if ($data === null || empty($data->image)) {
            return;
        }
        $image = CharacteristicValueImage::getThumb(
            Image::TYPE_CHARACTERISTIC_VALUE,
            (int)$data->kMerkmalWert,
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
                \URLART_SEITE,
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
        $data->val   = $data->cWert ?? null;
        $data->image = $data->cBildpfad ?? null;
        $this->setData($data);
        $this->setPrimaryKeyID($data->kMerkmalWert);
        $this->setLanguageData($languages, $data->langID);
        $this->setLocation($this->baseURL . $data->cSeo);
        $this->generateImage();
        $this->setChangeFreq(\FREQ_WEEKLY);
        $this->setPriority(\PRIO_NORMAL);
        $this->setLastModificationTime(null);
    }
}
