<?php

declare(strict_types=1);

namespace JTL\Boxes\Renderer;

/**
 * Class ContainerRenderer
 * @package JTL\Boxes\Renderer
 */
class ContainerRenderer extends DefaultRenderer
{
    /**
     * @inheritdoc
     */
    public function render(int $pageType = 0, int $pageID = 0): string
    {
        $html        = '';
        $boxRenderer = new DefaultRenderer($this->smarty);
        foreach ($this->getBox()->getChildren() as $child) {
            $boxRenderer->setBox($child);
            $rendererClass = $child->getRenderer();
            if (\get_class($boxRenderer) !== $rendererClass) {
                $boxRenderer = new $rendererClass($this->smarty);
            }
            $html .= \trim($boxRenderer->render($pageType, $pageID));
        }
        $this->getBox()->setHTML($html);

        return parent::render($pageType, $pageID);
    }
}
