<?php

declare(strict_types=1);

namespace JTL\Mail\Template;

use JTL\Smarty\JTLSmarty;

/**
 * Class Wishlist
 * @package JTL\Mail\Template
 */
class Wishlist extends AbstractTemplate
{
    protected ?string $id = \MAILTEMPLATE_WUNSCHLISTE;

    /**
     * @inheritdoc
     */
    public function preRender(JTLSmarty $smarty, mixed $data): void
    {
        parent::preRender($smarty, $data);
        if ($data === null) {
            return;
        }
        $smarty->assign('Wunschliste', $data->twunschliste);
    }
}
