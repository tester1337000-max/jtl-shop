<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Shop;

class TemplateVersion extends AbstractStatusCheck
{
    protected bool $includeInServiceReport = true;

    public function isOK(): bool
    {
        try {
            $template = Shop::Container()->getTemplateService()->getActiveTemplate();
        } catch (\Exception) {
            return false;
        }
        return $template->getVersion() === \APPLICATION_VERSION;
    }

    public function getTitle(): string
    {
        return \__('hasStandardTemplateIssueTitle');
    }

    public function generateMessage(): void
    {
    }
}
