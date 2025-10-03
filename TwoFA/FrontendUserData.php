<?php

declare(strict_types=1);

namespace JTL\TwoFA;

use JTL\DB\DbInterface;

/**
 * Class FrontendUserData
 * @package JTL\TwoFA
 */
class FrontendUserData extends UserData
{
    public static function getByName(string $userName, DbInterface $db): self
    {
        $userData = new self();
        $data     = $db->select('tkunde', 'cMail', $userName);
        if ($data !== null) {
            $userData->setName($userName);
            $userData->setID((int)$data->kKunde);
            $userData->setSecret($data->c2FAauthSecret);
            $userData->setUse2FA((bool)$data->b2FAauth);
        }

        return $userData;
    }

    public static function getByID(int $id, DbInterface $db): self
    {
        $userData = new self();
        $data     = $db->select('tkunde', 'kKunde', $id);
        if ($data !== null) {
            $userData->setName($data->cMail);
            $userData->setID($id);
            $userData->setSecret($data->c2FAauthSecret);
            $userData->setUse2FA((bool)$data->b2FAauth);
        }

        return $userData;
    }

    public function getTableName(): string
    {
        return 'tkunde';
    }

    public function getEmergencyCodeTableName(): string
    {
        return 'tkunde2facodes';
    }

    public function getKeyName(): string
    {
        return 'kKunde';
    }
}
