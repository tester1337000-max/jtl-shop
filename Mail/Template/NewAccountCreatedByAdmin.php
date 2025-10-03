<?php

declare(strict_types=1);

namespace JTL\Mail\Template;

use JTL\Link\SpecialPageNotFoundException;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;

/**
 * Class NewAccountCreatedByAdmin
 * @package JTL\Mail\Template
 */
class NewAccountCreatedByAdmin extends AbstractTemplate
{
    protected ?string $id = \MAILTEMPLATE_ACCOUNTERSTELLUNG_DURCH_BETREIBER;

    /**
     * @inheritdoc
     */
    public function preRender(JTLSmarty $smarty, mixed $data): void
    {
        parent::preRender($smarty, $data);

        try {
            $link = Shop::Container()->getLinkService()->getSpecialPage(\LINKTYP_PASSWORD_VERGESSEN)->getURL();
        } catch (SpecialPageNotFoundException $e) {
            Shop::Container()->getLogService()->warning($e->getMessage());
            $link = Shop::Container()->getLinkService()->getStaticRoute('pass.php');
        }
        $smarty->assign('newPasswordURL', $link);
    }
}
