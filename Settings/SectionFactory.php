<?php

declare(strict_types=1);

namespace JTL\Backend\Settings;

use JTL\Backend\Settings\Sections\Base;
use JTL\Backend\Settings\Sections\Checkout;
use JTL\Backend\Settings\Sections\Comparelist;
use JTL\Backend\Settings\Sections\PaymentMethod;
use JTL\Backend\Settings\Sections\SectionInterface;

/**
 * Class SectionFactory
 * @package JTL\Backend\Settings
 */
class SectionFactory
{
    public function getSection(int $sectionID, Manager $manager): SectionInterface
    {
        return match ($sectionID) {
            \CONF_KAUFABWICKLUNG  => new Checkout($manager, $sectionID),
            \CONF_ZAHLUNGSARTEN   => new PaymentMethod($manager, $sectionID),
            \CONF_VERGLEICHSLISTE => new Comparelist($manager, $sectionID),
            default               => new Base($manager, $sectionID),
        };
    }
}
