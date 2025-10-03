<?php

declare(strict_types=1);

namespace JTL\TwoFA;

use Exception;
use JTL\DB\DbInterface;
use JTL\Shop;

/**
 * Class TwoFAEmergency
 * @package JTL\TwoFA
 */
class TwoFAEmergency
{
    /**
     * @var string[]
     */
    private array $codes = [];

    private int $codeCount = 10;

    public function __construct(private readonly DbInterface $db)
    {
    }

    /**
     * create a pool of emergency codes for the current admin-account and store them in the DB.
     *
     * @param UserData $userData - user-data, as delivered from TwoFA-object
     * @return string[] - new created emergency-codes (as written into the DB)
     * @throws Exception
     */
    public function createNewCodes(UserData $userData): array
    {
        $passwordService = Shop::Container()->getPasswordService();
        $bindings        = [];
        $rowValues       = '';
        $valCount        = 'a';
        for ($i = 0; $i < $this->codeCount; $i++) {
            $code = \mb_substr(\md5((string)\random_int(1000, 9000)), 0, 16);

            $this->codes[] = $code;
            if ($rowValues !== '') {
                $rowValues .= ', ';
            }
            $code = $passwordService->hash($code);

            $bindings[$valCount] = $userData->getID();
            $rowValues           .= '(:' . $valCount . ',';
            $valCount++;
            $bindings[$valCount] = $code;
            $rowValues           .= ' :' . $valCount . ')';
            $valCount++;
        }
        $this->db->queryPrepared(
            'INSERT INTO `' . $userData->getEmergencyCodeTableName()
            . '`(`' . $userData->getKeyName() . '`, `cEmergencyCode`) VALUES' . $rowValues,
            $bindings
        );

        return $this->codes;
    }

    public function removeExistingCodes(UserData $userData): void
    {
        $this->db->delete(
            $userData->getEmergencyCodeTableName(),
            $userData->getKeyName(),
            $userData->getID()
        );
    }

    public function isValidEmergencyCode(UserData $userData, string $code): bool
    {
        $hashes = $this->db->selectArray(
            $userData->getEmergencyCodeTableName(),
            $userData->getKeyName(),
            $userData->getID()
        );
        if (\count($hashes) === 0) {
            return false;
        }

        foreach ($hashes as $item) {
            if (\password_verify($code, $item->cEmergencyCode) !== true) {
                continue;
            }
            $effected = $this->db->delete(
                $userData->getEmergencyCodeTableName(),
                [$userData->getKeyName(), 'cEmergencyCode'],
                [$userData->getID(), $item->cEmergencyCode]
            );
            if ($effected !== 1) {
                Shop::Container()->getLogService()->error(
                    '2FA emergency code for user {id} could not be deleted.',
                    ['id' => $userData->getID()]
                );
            }

            return true;
        }

        return false;
    }
}
