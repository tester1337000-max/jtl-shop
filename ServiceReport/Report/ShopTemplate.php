<?php

declare(strict_types=1);

namespace JTL\ServiceReport\Report;

use JTL\Shop;

class ShopTemplate implements ReportInterface
{
    /**
     * @return array{author: string, error: bool, name: ?string, parent: ?string, version: string}
     */
    public function getData(): array
    {
        $template = Shop::Container()->getTemplateService()->getActiveTemplate();

        return [
            'name'    => $template->getName(),
            'version' => $template->getVersion(),
            'author'  => $template->getAuthor(),
            'parent'  => $template->getParent(),
            'error'   => $template->hasError(),
        ];
    }
}
