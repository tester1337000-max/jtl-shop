<?php

declare(strict_types=1);

namespace JTL\Boxes;

use JTL\Boxes\Items\BoxInterface;

/**
 * Interface FactoryInterface
 * @package JTL\Boxes
 */
interface FactoryInterface
{
    /**
     * @param string[][] $config
     */
    public function __construct(array $config);

    /**
     * @param int         $baseType
     * @param string|null $type
     * @return boxInterface
     */
    public function getBoxByBaseType(int $baseType, ?string $type = null): BoxInterface;
}
