<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Router\Route;
use JTL\Shop;

class Template extends AbstractStatusCheck
{
    protected bool $includeInServiceReport = true;

    public function isOK(?string &$hash = null): bool
    {
        return $this->db->select('ttemplate', 'eTyp', 'standard') !== null
            && Shop::Container()->getTemplateService()->getActiveTemplate()->getTemplate() !== null;
    }

    public function getURL(): ?string
    {
        return $this->adminURL . Route::TEMPLATE;
    }

    public function getTitle(): string
    {
        return \__('hasStandardTemplateIssueTitle');
    }

    public function generateMessage(): void
    {
        $this->addNotification(\__('hasStandardTemplateIssueMessage'));
    }
}
