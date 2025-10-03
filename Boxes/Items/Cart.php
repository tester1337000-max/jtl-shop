<?php

declare(strict_types=1);

namespace JTL\Boxes\Items;

/**
 * Class Cart
 *
 * @package JTL\Boxes\Items
 */
final class Cart extends AbstractBox
{
    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->addMapping('elemente', 'Items');
        if (isset($_SESSION['Warenkorb']->PositionenArr)) {
            $this->setItems(\array_reverse($_SESSION['Warenkorb']->PositionenArr));
        }
        $this->setShow(true);
    }
}
