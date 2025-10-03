<?php

declare(strict_types=1);

namespace JTL\Mail\Template;

use JTL\Smarty\JTLSmarty;

/**
 * Class BalanceChanged
 * @package JTL\Mail\Template
 */
class BalanceChanged extends AbstractTemplate
{
    protected ?string $id = \MAILTEMPLATE_GUTSCHEIN;

    /**
     * @inheritdoc
     */
    public function preRender(JTLSmarty $smarty, mixed $data): void
    {
        parent::preRender($smarty, $data);
        if ($data === null) {
            return;
        }
        $smarty->assign('Gutschein', $data->tgutschein);
    }
}
