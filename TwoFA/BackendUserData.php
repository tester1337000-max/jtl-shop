<?php

declare(strict_types=1);

namespace JTL\TwoFA;

use JTL\DB\DbInterface;

/**
 * Class BackendUserData
 * @package JTL\TwoFA
 */
class BackendUserData extends UserData
{
    public static function getByName(string $userName, DbInterface $db): self
    {
        $userData = new self();
        $userData->setName($userName);
        $data = $db->select('tadminlogin', 'cLogin', $userName);
        if ($data !== null) {
            $userData->setID((int)$data->kAdminlogin);
            $userData->setSecret($data->c2FAauthSecret);
            $userData->setUse2FA((bool)$data->b2FAauth);
        }

        return $userData;
    }

    public static function getByID(int $id, DbInterface $db): self
    {
        $userData = new self();
        $data     = $db->select('tadminlogin', 'kAdminlogin', $id);
        if ($data !== null) {
            $userData->setName($data->cLogin);
            $userData->setID($id);
            $userData->setSecret($data->c2FAauthSecret);
            $userData->setUse2FA((bool)$data->b2FAauth);
        }

        return $userData;
    }

    public function getTableName(): string
    {
        return 'tadminlogin';
    }

    public function getEmergencyCodeTableName(): string
    {
        return 'tadmin2facodes';
    }

    public function getKeyName(): string
    {
        return 'kAdminlogin';
    }
}
