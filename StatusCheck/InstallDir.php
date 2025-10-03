<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

class InstallDir extends AbstractStatusCheck
{
    protected bool $includeInServiceReport = true;

    public function isOK(): bool
    {
        return !\is_dir(\PFAD_ROOT . \PFAD_INSTALL);
    }

    public function getTitle(): string
    {
        return \__('hasInstallDirTitle');
    }

    public function generateMessage(): void
    {
        $this->addNotification(\__('hasInstallDirMessage'));
    }
}
