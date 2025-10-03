<?php

declare(strict_types=1);

namespace JTL\Mail\Template;

use JTL\Smarty\JTLSmarty;

/**
 * Class NewsletterRegistration
 * @package JTL\Mail\Template
 */
class NewsletterRegistration extends AbstractTemplate
{
    protected ?string $id = \MAILTEMPLATE_NEWSLETTERANMELDEN;

    /**
     * @inheritdoc
     */
    public function preRender(JTLSmarty $smarty, mixed $data): void
    {
        parent::preRender($smarty, $data);
        if ($data === null) {
            return;
        }
        $smarty->assign('NewsletterEmpfaenger', $data->NewsletterEmpfaenger);
    }
}
