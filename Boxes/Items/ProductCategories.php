<?php

declare(strict_types=1);

namespace JTL\Boxes\Items;

use JTL\Catalog\Category\MenuItem;
use JTL\Helpers\Category;
use JTL\Session\Frontend;

/**
 * Class ProductCategories
 * @package JTL\Boxes\Items
 */
final class ProductCategories extends AbstractBox
{
    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $show = isset($config['global']['global_sichtbarkeit'])
            && ((int)$config['global']['global_sichtbarkeit'] !== 3 || Frontend::getCustomer()->getID() > 0);
        $this->setShow($show);
    }

    /**
     * @return MenuItem[]
     */
    private function getCategories(): array
    {
        $categories = Category::getInstance();
        $list       = $categories->combinedGetAll();
        $boxID      = $this->getCustomID();
        if ($boxID <= 0) {
            return $list;
        }
        return \array_filter(
            $list,
            static fn(MenuItem $item): bool => $item->getFunctionalAttribute(\KAT_ATTRIBUT_KATEGORIEBOX) !== null
                && (int)$item->getFunctionalAttribute(\KAT_ATTRIBUT_KATEGORIEBOX) === $boxID
        );
    }

    public function init(): void
    {
        if ($this->getShow() === true) {
            $categories = $this->getCategories();
            $this->setItems($categories);
            $this->setShow(\count($categories) > 0);
        }
    }
}
