<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Backend\NotificationEntry;
use JTL\Link\LinkInterface;
use JTL\Router\Route;
use JTL\Shop;

class SpecialLinks extends AbstractStatusCheck
{
    protected int $messageType = NotificationEntry::TYPE_DANGER;

    public function isOK(): bool
    {
        $group = Shop::Container()->getLinkService()->getAllLinkGroups()->getLinkgroupByTemplate('specialpages');
        if ($group === null) {
            return true;
        }

        return $group->getLinks()->filter(
            static fn(LinkInterface $lnk): bool => $lnk->hasDuplicateSpecialLink()
        )->isEmpty();
    }

    public function getURL(): ?string
    {
        return $this->adminURL . Route::LINKS;
    }

    public function getTitle(): string
    {
        return \__('duplicateSpecialLinkTitle');
    }

    public function generateMessage(): void
    {
        $this->addNotification(\__('duplicateSpecialLinkDesc'));
    }
}
