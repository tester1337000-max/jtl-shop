<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Backend\NotificationEntry;
use JTL\Router\Route;
use JTL\Shop;

class MobileTemplate extends AbstractStatusCheck
{
    protected int $messageType = NotificationEntry::TYPE_INFO;

    public function isOK(?string &$hash = null): bool
    {
        try {
            $template = Shop::Container()->getTemplateService()->getActiveTemplate();
        } catch (\Exception) {
            return true;
        }
        if ($template->isResponsive()) {
            $mobileTpl = $this->db->select('ttemplate', 'eTyp', 'mobil');
            if ($mobileTpl !== null) {
                $xmlFile = \PFAD_ROOT . \PFAD_TEMPLATES . $mobileTpl->cTemplate . '/' . \TEMPLATE_XML;
                if (\file_exists($xmlFile)) {
                    return false;
                }
                $this->db->delete('ttemplate', 'eTyp', 'mobil');
            }
        }

        return true;
    }

    public function getURL(): ?string
    {
        return $this->adminURL . Route::TEMPLATE;
    }

    public function getTitle(): string
    {
        return \__('hasMobileTemplateIssueTitle');
    }

    public function generateMessage(): void
    {
        $this->addNotification(\__('hasMobileTemplateIssueMessage'));
    }
}
