<?php

declare(strict_types=1);

namespace JTL\Boxes\Items;

use JTL\Boxes\Renderer\ContainerRenderer;
use JTL\Boxes\Renderer\RendererInterface;

/**
 * Class Container
 * @package JTL\Boxes\Items
 */
class Container extends AbstractBox
{
    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->addMapping('innerHTML', 'HTML');
        $this->addMapping('oContainer_arr', 'Children');
    }

    /**
     * @return class-string<RendererInterface>
     */
    public function getRenderer(): string
    {
        return ContainerRenderer::class;
    }
}
