<?php

declare(strict_types=1);

namespace JTL\Filter;

use JTL\Cache\JTLCacheInterface;
use JTL\Media\Image;

/**
 * Class CharacteristicValueOption
 * @package JTL\Filter
 */
class CharacteristicValueOption extends Option
{
    public function generateCachableData(JTLCacheInterface $cache, int $languageID): void
    {
        $this->setImageType(Image::TYPE_CHARACTERISTIC_VALUE);
        $cacheID = 'fltr_cvo_' . $languageID . '_' . $this->getID();
        if (($data = $cache->get($cacheID)) !== false) {
            $this->images          = $data['images'];
            $this->imageDimensions = $data['imageDimensions'];
            return;
        }
        $this->generateAllImageSizes();
        $this->generateAllImageDimensions();

        $cache->set($cacheID, [
            'images'          => $this->getImages(),
            'imageDimensions' => $this->imageDimensions
        ], [\CACHING_GROUP_FILTER]);
    }
}
