<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Router\Route;
use Systemcheck\Environment as SystemCheckEnvironment;
use Systemcheck\Platform\PDOConnection;

class Environment extends AbstractStatusCheck
{
    protected bool $includeInServiceReport = true;

    public function isOK(): bool
    {
        PDOConnection::getInstance()->setConnection($this->db->getPDO());
        $systemcheck = new SystemcheckEnvironment();
        $systemcheck->executeTestGroup('Shop5');

        return $systemcheck->getIsPassed();
    }

    public function getTitle(): string
    {
        return \__('server');
    }

    public function getURL(): ?string
    {
        return $this->adminURL . Route::SYSTEMCHECK;
    }

    public function generateMessage(): void
    {
    }
}
