<?php

declare(strict_types=1);

namespace JTL\Media\Image;

/**
 * Class Dummy
 * @package JTL\Media\Image
 */
class Dummy extends AbstractImage
{
    /**
     * @inheritdoc
     */
    public function handle(string $request): void
    {
    }
}
