<?php

declare(strict_types=1);

namespace JTL\dbeS;

use JTL\DB\DbInterface;
use JTL\Shop;
use Psr\Log\LoggerInterface;

/**
 * Class Synclogin
 * @package JTL\dbeS
 */
class Synclogin
{
    public ?string $cMail = null;

    public ?string $cName = null;

    public ?string $cPass = null;

    public ?int $kSynclogin = null;

    public function __construct(DbInterface $db, LoggerInterface $logger)
    {
        $obj = $db->select('tsynclogin', 'kSynclogin', 1);
        if ($obj !== null) {
            $this->cMail      = $obj->cMail;
            $this->cName      = $obj->cName;
            $this->cPass      = $obj->cPass;
            $this->kSynclogin = (int)$obj->kSynclogin;
        } else {
            $logger->error('Kein Sync-Login gefunden.');
        }
    }

    /**
     * @throws \Exception
     */
    public function checkLogin(string $user, string $pass): bool
    {
        return $this->cName !== null
            && $this->cPass !== null
            && $this->cName === $user
            && Shop::Container()->getPasswordService()->verify($pass, $this->cPass) === true;
    }
}
