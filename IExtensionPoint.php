<?php

declare(strict_types=1);

namespace JTL;

/**
 * Interface IExtensionPoint
 * @package JTL
 */
interface IExtensionPoint
{
    /**
     * @param int $id
     * @return self
     */
    public function init(int $id): self;
}
