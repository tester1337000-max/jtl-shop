<?php

declare(strict_types=1);

namespace JTL\Interfaces;

/**
 *  Service will contain business logic and guard/wrap access to the repository
 *  direct access to the repository is possible but not recommended
 */
interface ServiceInterface
{
    /**
     * @return RepositoryInterface
     */
    public function getRepository(
        string $repositoryName,
        ?RepositoryInterface &$repository = null
    ): mixed;
}
