<?php

declare(strict_types=1);

namespace JTL\Mail\Template;

use JTL\Smarty\JTLSmarty;

/**
 * Class ContactFormSent
 * @package JTL\Mail\Template
 */
class ContactFormSent extends AbstractTemplate
{
    protected ?string $id = \MAILTEMPLATE_KONTAKTFORMULAR;

    /**
     * @inheritdoc
     */
    public function preRender(JTLSmarty $smarty, mixed $data): void
    {
        parent::preRender($smarty, $data);
        if (!empty($this->config['kontakt']['kontakt_absender_name'])) {
            $this->setFromName($this->config['kontakt']['kontakt_absender_name']);
        }
        if (!empty($this->config['kontakt']['kontakt_absender_mail'])) {
            $this->setFromMail($this->config['kontakt']['kontakt_absender_mail']);
        }
        if ($data === null) {
            return;
        }
        $smarty->assign('Nachricht', $data->tnachricht);
    }
}
