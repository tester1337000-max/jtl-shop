<?php

declare(strict_types=1);

namespace JTL\Boxes\Items;

/**
 * Class DirectPurchase
 *
 * @package JTL\Boxes\Items
 */
final class DirectPurchase extends AbstractBox
{
    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->setShow(true);
        \executeHook(\HOOK_BOXEN_INC_SCHNELLKAUF);
    }
}
