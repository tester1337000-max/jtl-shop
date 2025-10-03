<?php

declare(strict_types=1);

namespace JTL\Sitemap\Items;

/**
 * Class Base
 * @package JTL\Sitemap\Items
 */
final class Base extends AbstractItem
{
    /**
     * @inheritdoc
     */
    public function generateData(mixed $data, array $languages): void
    {
        $this->setData($data);
        $this->setPrimaryKeyID(0);
        $this->setLanguageData($languages, (int)$data->langID);
        $this->setLocation($this->baseURL);
        $this->setChangeFreq(\FREQ_ALWAYS);
        $this->setPriority(\PRIO_VERYHIGH);
        $this->setLastModificationTime(null);
    }
}
