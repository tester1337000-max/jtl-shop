<?php

declare(strict_types=1);

namespace JTL\Boxes\Renderer;

use JTL\Boxes\Items\BoxInterface;
use JTL\Smarty\JTLSmarty;

/**
 * Interface RendererInterface
 * @package JTL\Boxes\Renderer
 */
interface RendererInterface
{
    public function __construct(JTLSmarty $smarty, ?BoxInterface $box = null);

    /**
     * @return BoxInterface
     */
    public function getBox(): BoxInterface;

    /**
     * @param BoxInterface $box
     */
    public function setBox(BoxInterface $box): void;

    /**
     * @param int $pageType
     * @param int $pageID
     * @return string
     */
    public function render(int $pageType = 0, int $pageID = 0): string;
}
