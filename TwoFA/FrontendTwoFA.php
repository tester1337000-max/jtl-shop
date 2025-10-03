<?php

declare(strict_types=1);

namespace JTL\TwoFA;

use JTL\Shop;
use stdClass;

/**
 * Class FrontendTwoFA
 * @package JTL\TwoFA
 */
class FrontendTwoFA extends TwoFA
{
    public static function getNewTwoFA(string $userName): string
    {
        $db    = Shop::Container()->getDB();
        $twoFA = new self($db, FrontendUserData::getByName($userName, $db));

        $userData           = new stdClass();
        $userData->szSecret = $twoFA->createNewSecret()->getSecret();
        $userData->szQRcode = $twoFA->getQRcode();

        return \json_encode($userData, \JSON_THROW_ON_ERROR) ?: '';
    }

    public static function genTwoFAEmergencyCodes(string $userName): stdClass
    {
        $db    = Shop::Container()->getDB();
        $twoFA = new self($db, FrontendUserData::getByName($userName, $db));

        $data            = new stdClass();
        $data->loginName = $twoFA->getUserData()->getName();
        $data->shopName  = $twoFA->getShopName();

        $emergencyCodes = new TwoFAEmergency($db);
        $emergencyCodes->removeExistingCodes($twoFA->getUserData());

        $data->vCodes = $emergencyCodes->createNewCodes($twoFA->getUserData());

        return $data;
    }
}
