<?php

declare(strict_types=1);

namespace JTL\Backend\Settings\Sections;

use JTL\Backend\Settings\Manager;

/**
 * Class Checkout
 * @package JTL\Backend\Settings\Sections
 */
class Checkout extends Base
{
    /**
     * @inheritdoc
     */
    public function __construct(Manager $manager, int $sectionID)
    {
        parent::__construct($manager, $sectionID);
        $this->hasSectionMarkup = true;
    }

    /**
     * @inheritdoc
     */
    public function getSectionMarkup(): string
    {
        return $this->smarty->fetch('tpl_inc/settingsection_warenkorb.tpl');
    }
}
