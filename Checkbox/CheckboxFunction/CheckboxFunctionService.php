<?php

declare(strict_types=1);

namespace JTL\Checkbox\CheckboxFunction;

use JTL\Abstracts\AbstractService;

/**
 * Class CheckboxFunctionService
 * @package JTL\Checkbox\CheckboxFunction
 */
class CheckboxFunctionService extends AbstractService
{
    public function __construct(protected CheckboxFunctionRepository $repository = new CheckboxFunctionRepository())
    {
    }

    /**
     * @return CheckboxFunctionRepository
     */
    protected function getRepository(): CheckboxFunctionRepository
    {
        return $this->repository;
    }

    public function get(int $id): ?\stdClass
    {
        return $this->getRepository()->get($id);
    }
}
