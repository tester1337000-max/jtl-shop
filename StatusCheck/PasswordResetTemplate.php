<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Router\Route;

class PasswordResetTemplate extends AbstractStatusCheck
{
    public function isOK(): bool
    {
        $translations = $this->db->getObjects(
            "SELECT lang.cContentText, lang.cContentHtml
                FROM temailvorlagesprache lang
                JOIN temailvorlage
                ON lang.kEmailvorlage = temailvorlage.kEmailvorlage
                WHERE temailvorlage.cName = 'Passwort vergessen'"
        );
        $old          = '{$neues_passwort}';
        foreach ($translations as $t) {
            if (\str_contains($t->cContentHtml, $old) || \str_contains($t->cContentText, $old)) {
                return false;
            }
        }

        return true;
    }

    public function getURL(): ?string
    {
        return $this->adminURL . Route::EMAILTEMPLATES;
    }

    public function getTitle(): string
    {
        return \__('hasInvalidPasswordResetMailTemplateTitle');
    }

    public function generateMessage(): void
    {
        $this->addNotification(\__('hasInvalidPasswordResetMailTemplateMessage'));
    }
}
