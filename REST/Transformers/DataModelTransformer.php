<?php

declare(strict_types=1);

namespace JTL\REST\Transformers;

use JTL\Model\DataModelInterface;
use League\Fractal\TransformerAbstract;

/**
 * Class DataModelTransformer
 * @package JAPI\Transformers
 */
class DataModelTransformer extends TransformerAbstract
{
    /**
     * @param DataModelInterface $category
     * @return array<string, mixed>
     */
    public function transform(DataModelInterface $category): array
    {
        return $category->rawArray(true);
    }
}
