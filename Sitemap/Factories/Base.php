<?php

declare(strict_types=1);

namespace JTL\Sitemap\Factories;

use Generator;
use JTL\Sitemap\Items\Base as BaseItem;
use stdClass;

/**
 * Class Base
 * @package JTL\Sitemap\Factories
 */
final class Base extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function getCollection(array $languages, array $customerGroups): Generator
    {
        $item           = new BaseItem($this->config, $this->baseURL, $this->baseImageURL);
        $data           = new stdClass();
        $data->langID   = $_SESSION['kSprache'];
        $data->langCode = $_SESSION['cISOSprache'];
        $item->generateData($data, $languages);

        yield $item;
    }
}
