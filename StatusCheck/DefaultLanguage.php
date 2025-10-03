<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Backend\NotificationEntry;
use JTL\Language\LanguageHelper;
use JTL\Language\LanguageModel;

use function Functional\some;

class DefaultLanguage extends AbstractStatusCheck
{
    protected bool $includeInServiceReport = true;

    protected int $messageType = NotificationEntry::TYPE_DANGER;

    public function isOK(): bool
    {
        $defaultID = LanguageHelper::getDefaultLanguage()->getId();

        return some(
            LanguageHelper::getInstance()->getInstalled(),
            static fn(LanguageModel $lang): bool => $lang->getId() === $defaultID
        );
    }

    public function getTitle(): string
    {
        return \__('defaultLangNotInstalledTitle');
    }

    public function generateMessage(): void
    {
        $this->addNotification(
            \sprintf(
                \__('defaultLangNotInstalledMessage'),
                LanguageHelper::getDefaultLanguage()->getNameDE()
            )
        );
    }
}
