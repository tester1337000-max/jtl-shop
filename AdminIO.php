<?php

declare(strict_types=1);

namespace JTL\Backend;

use JTL\IO\IO;
use JTL\IO\IOError;

/**
 * Class AdminIO
 * @package JTL\Backend
 */
class AdminIO extends IO
{
    protected ?AdminAccount $oAccount = null;

    public function setAccount(AdminAccount $account): self
    {
        $this->oAccount = $account;

        return $this;
    }

    public function getAccount(): ?AdminAccount
    {
        return $this->oAccount;
    }

    /**
     * @throws \Exception
     */
    public function register(
        string $name,
        array|callable|null $function = null,
        ?string $include = null,
        ?string $permission = null
    ): self {
        parent::register($name, $function, $include);
        $this->functions[$name][] = $permission;

        return $this;
    }

    /**
     * @throws \Exception
     */
    public function execute(string $name, mixed $params): mixed
    {
        if (!$this->exists($name)) {
            return new IOError('Function not registered');
        }

        $permission = $this->functions[$name][2];

        if ($permission !== null && !$this->oAccount?->permission($permission)) {
            return new IOError('User does not have the required permissions to execute this function', 401);
        }

        return parent::execute($name, $params);
    }
}
