<?php

declare(strict_types=1);

namespace JTL\Backend;

use DateTime;
use Exception;
use JTL\DB\DbInterface;
use JTL\Helpers\Request;
use JTL\L10n\GetText;
use JTL\Mail\Mail\Mail;
use JTL\Mapper\AdminLoginStatusMessageMapper;
use JTL\Mapper\AdminLoginStatusToLogLevel;
use JTL\Model\AuthLogEntry;
use JTL\Router\Route;
use JTL\Services\JTL\AlertServiceInterface;
use JTL\Session\Backend;
use JTL\Settings\Option\Globals;
use JTL\Settings\Settings;
use JTL\Shop;
use JTL\TwoFA\BackendTwoFA;
use JTL\TwoFA\BackendUserData;
use Psr\Log\LoggerInterface;
use stdClass;

use function Functional\pluck;
use function Functional\reindex;

/**
 * Class AdminAccount
 * @package JTL\Backend
 */
class AdminAccount
{
    private bool $loggedIn = false;

    private bool $valid = true;

    private bool $twoFaAuthenticated = false;

    private int $lockedMinutes = 0;

    public function __construct(
        private readonly DbInterface $db,
        private readonly LoggerInterface $authLogger,
        private readonly AdminLoginStatusMessageMapper $messageMapper,
        private readonly AdminLoginStatusToLogLevel $levelMapper,
        private readonly GetText $getText,
        private readonly AlertServiceInterface $alertService
    ) {
        Backend::getInstance();
        Shop::setIsFrontend(false);
        $this->initDefaults();
        $this->validateSession();
    }

    private function initDefaults(): void
    {
        if (!isset($_SESSION['AdminAccount'])) {
            $adminAccount              = new stdClass();
            $adminAccount->language    = $this->getText->getLanguage();
            $adminAccount->kAdminlogin = null;
            $adminAccount->oGroup      = null;
            $adminAccount->cLogin      = null;
            $adminAccount->cMail       = null;
            $adminAccount->cPass       = null;
            $adminAccount->attributes  = null;
            $_SESSION['AdminAccount']  = $adminAccount;
        }
    }

    public function getLockedMinutes(): int
    {
        return $this->lockedMinutes;
    }

    public function setLockedMinutes(int $lockedMinutes): void
    {
        $this->lockedMinutes = $lockedMinutes;
    }

    /**
     * @throws Exception
     */
    public function verifyResetPasswordHash(string $hash, string $mail): bool
    {
        $user = $this->db->select('tadminlogin', 'cMail', $mail);
        if ($user !== null) {
            // there should be a string <created_timestamp>:<hash> in the DB
            $timestampAndHash = \explode(':', $user->cResetPasswordHash);
            if (\count($timestampAndHash) === 2) {
                [$timeStamp, $originalHash] = $timestampAndHash;
                // check if the link is not expired (=24 hours valid)
                $createdAt = (new DateTime())->setTimestamp((int)$timeStamp);
                $now       = new DateTime();
                $diff      = $now->diff($createdAt);
                $secs      = ((int)$diff->format('%a') * (60 * 60 * 24)); // total days
                $secs      += (int)$diff->format('%h') * (60 * 60); // hours
                $secs      += (int)$diff->format('%i') * 60; // minutes
                $secs      += (int)$diff->format('%s'); // seconds
                if ($secs > (60 * 60 * 24)) {
                    return false;
                }
                // check the submitted hash against the saved one
                return Shop::Container()->getPasswordService()->verify($hash, $originalHash);
            }
        }

        return false;
    }

    /**
     * @throws Exception
     */
    public function prepareResetPassword(string $email): bool
    {
        $now  = (new DateTime())->format('U');
        $hash = \md5($email . Shop::Container()->getCryptoService()->randomString(30));
        $upd  = (object)['cResetPasswordHash' => $now . ':' . Shop::Container()->getPasswordService()->hash($hash)];
        $res  = $this->db->update('tadminlogin', 'cMail', $email, $upd);
        if ($res > 0) {
            $user = $this->db->select('tadminlogin', 'cMail', $email);
            if ($user === null) {
                $this->alertService->addError(\__('errorEmailNotFound'), 'errorEmailNotFound');

                return false;
            }
            $obj                    = new stdClass();
            $obj->passwordResetLink = Shop::getAdminURL() . '/' . Route::PASS . '?fpwh=' . $hash . '&mail=' . $email;
            $obj->cHash             = $hash;
            $obj->mail              = new stdClass();
            $obj->mail->toEmail     = $email;
            $obj->mail->toName      = $user->cLogin;

            $mailer = Shop::Container()->getMailer();
            $mail   = new Mail();
            $mailer->send($mail->createFromTemplateID(\MAILTEMPLATE_ADMINLOGIN_PASSWORT_VERGESSEN, $obj));

            $this->alertService->addSuccess(\__('successEmailSend'), 'successEmailSend');

            return true;
        }
        $this->alertService->addError(\__('errorEmailNotFound'), 'errorEmailNotFound');

        return false;
    }

    private function handleLoginResult(int $code, string $user): int
    {
        $log = new AuthLogEntry();

        $log->setIP(Request::getRealIP());
        $log->setCode($code);
        $log->setUser($user);

        $this->authLogger->log(
            $this->levelMapper->map($code),
            $this->messageMapper->map($code),
            $log->asArray()
        );

        return $code;
    }

    public function login(string $login, #[\SensitiveParameter] string $pass): int
    {
        $admin = $this->db->getSingleObject(
            'SELECT *, UNIX_TIMESTAMP(dGueltigBis) AS dGueltigTS
                FROM tadminlogin
                WHERE cLogin = :login',
            ['login' => $login]
        );
        if ($admin === null) {
            return $this->handleLoginResult(AdminLoginStatus::ERROR_USER_NOT_FOUND, $login);
        }
        $admin->kAdminlogin       = (int)$admin->kAdminlogin;
        $admin->kAdminlogingruppe = (int)$admin->kAdminlogingruppe;
        $admin->nLoginVersuch     = (int)$admin->nLoginVersuch;
        $admin->bAktiv            = (int)$admin->bAktiv;
        if (!$admin->bAktiv && $admin->kAdminlogingruppe !== \ADMINGROUP) {
            return $this->handleLoginResult(AdminLoginStatus::ERROR_USER_DISABLED, $login);
        }
        if ($admin->dGueltigTS && $admin->kAdminlogingruppe !== \ADMINGROUP && $admin->dGueltigTS < \time()) {
            return $this->handleLoginResult(AdminLoginStatus::ERROR_LOGIN_EXPIRED, $login);
        }
        if ($admin->nLoginVersuch >= \MAX_LOGIN_ATTEMPTS && !empty($admin->locked_at)) {
            $time        = new DateTime($admin->locked_at);
            $diffMinutes = ((new DateTime('NOW'))->getTimestamp() - $time->getTimestamp()) / 60;
            if ($diffMinutes < \LOCK_TIME) {
                $this->setLockedMinutes((int)\ceil(\LOCK_TIME - $diffMinutes));

                return AdminLoginStatus::ERROR_LOCKED;
            }
        }
        $verified = false;
        $crypted  = null;
        if (\mb_strlen($admin->cPass) === 32) {
            if (\md5($pass) !== $admin->cPass) {
                $this->setRetryCount($admin->cLogin);

                return $this->handleLoginResult(AdminLoginStatus::ERROR_INVALID_PASSWORD, $login);
            }
            if (!isset($_SESSION['AdminAccount'])) {
                $_SESSION['AdminAccount'] = new stdClass();
            }
            $_SESSION['AdminAccount']->cPass  = \md5($pass);
            $_SESSION['AdminAccount']->cLogin = $login;
            $verified                         = true;
            if ($this->checkAndUpdateHash($pass) === true) {
                $admin = $this->db->getSingleObject(
                    'SELECT *, UNIX_TIMESTAMP(dGueltigBis) AS dGueltigTS
                        FROM tadminlogin
                        WHERE cLogin = :login',
                    ['login' => $login]
                );
                if ($admin === null) {
                    throw new Exception('Admin account not found after password update');
                }
                $admin->kAdminlogin       = (int)$admin->kAdminlogin;
                $admin->kAdminlogingruppe = (int)$admin->kAdminlogingruppe;
                $admin->nLoginVersuch     = (int)$admin->nLoginVersuch;
                $admin->bAktiv            = (int)$admin->bAktiv;
            }
        } elseif (\mb_strlen($admin->cPass) === 40) {
            // default login until Shop4
            $crypted = Shop::Container()->getPasswordService()->cryptOldPasswort($pass, $admin->cPass);
        } else {
            // new default login from 4.0 on
            $verified = \password_verify($pass, $admin->cPass);
        }
        if ($verified === true || ($crypted !== null && $admin->cPass === $crypted)) {
            if (Settings::boolValue(Globals::MAINTENANCE_MODE_ACTIVE) === false) {
                foreach (\array_keys($_SESSION ?? []) as $i) {
                    unset($_SESSION[$i]);
                }
            }
            if (!isset($admin->kSprache)) {
                $admin->kSprache = Shop::getLanguageID();
            }
            $admin->cISO       = Shop::Lang()->getIsoFromLangID($admin->kSprache)->cISO ?? 'ger';
            $admin->attributes = $this->getAttributes($admin->kAdminlogin);
            \session_regenerate_id();
            $this->toSession($admin);
            $this->checkAndUpdateHash($pass);
            if (!$this->getIsTwoFaAuthenticated()) {
                return $this->handleLoginResult(AdminLoginStatus::ERROR_TWO_FACTOR_AUTH_EXPIRED, $login);
            }
            return $this->handleLoginResult(
                $this->logged()
                    ? AdminLoginStatus::LOGIN_OK
                    : AdminLoginStatus::ERROR_NOT_AUTHORIZED,
                $login
            );
        }

        $this->setRetryCount($admin->cLogin);

        return $this->handleLoginResult(AdminLoginStatus::ERROR_INVALID_PASSWORD, $login);
    }

    /**
     * @return array<string, stdClass>|null
     */
    private function getAttributes(int $userID): ?array
    {
        // try, because of SHOP-4319
        try {
            $attributes = reindex(
                $this->db->getObjects(
                    'SELECT cName, cAttribText, cAttribValue
                        FROM tadminloginattribut
                        WHERE kAdminlogin = :userID',
                    ['userID' => $userID]
                ),
                fn(stdClass $e): string => $e->cName
            );
            if (!empty($attributes) && isset($attributes['useAvatarUpload'])) {
                $attributes['useAvatarUpload']->cAttribValue = Shop::getImageBaseURL()
                    . \ltrim($attributes['useAvatarUpload']->cAttribValue, '/');
            }
        } catch (Exception) {
            $attributes = null;
        }

        return $attributes;
    }

    public function refreshAttributes(): void
    {
        $account = $this->account();
        if ($account !== false) {
            $account->attributes = $this->getAttributes($account->kAdminlogin);
        }
    }

    public function logout(): self
    {
        $this->db->delete('active_admin_sessions', 'sessionID', \session_id() ?: '');
        $this->loggedIn = false;
        \session_destroy();
        new Backend();
        \session_regenerate_id(true);

        return $this;
    }

    public function lock(): self
    {
        $this->loggedIn = false;

        return $this;
    }

    public function logged(): bool
    {
        return $this->getIsTwoFaAuthenticated() && $this->getIsAuthenticated();
    }

    public function getIsAuthenticated(): bool
    {
        return $this->loggedIn;
    }

    public function getIsTwoFaAuthenticated(): bool
    {
        return $this->twoFaAuthenticated;
    }

    public function redirectOnFailure(int $errCode = 0): void
    {
        if ($this->logged()) {
            return;
        }
        $url = !\str_contains(\basename($_SERVER['REQUEST_URI']), 'logout')
            ? '?uri=' . \base64_encode(\basename($_SERVER['REQUEST_URI']))
            : '';
        if ($errCode !== 0) {
            $url .= (!\str_contains($url, '?') ? '?' : '&') . 'errCode=' . $errCode;
        }
        \header('Location: ' . Shop::getAdminURL() . '/' . $url);
        exit;
    }

    public function account(): false|stdClass
    {
        return $this->getIsAuthenticated() ? $_SESSION['AdminAccount'] : false;
    }

    public function permission(string $permission, bool $redirectToLogin = false, bool $showNoAccessPage = false): bool
    {
        if ($permission === Permissions::API_KEYS_VIEW && \SHOW_REST_API === false) {
            return false;
        }
        if ($redirectToLogin) {
            $this->redirectOnFailure();
        }
        // grant full access to admin
        $account = $this->account();
        if ($account !== false && (int)$account->oGroup->kAdminlogingruppe === \ADMINGROUP) {
            return true;
        }
        $hasAccess = \in_array($permission, $_SESSION['AdminAccount']->oGroup->oPermission_arr ?? [], true);
        if ($showNoAccessPage && !$hasAccess) {
            Shop::Smarty()->display('tpl_inc/berechtigung.tpl');
            exit;
        }

        return $hasAccess;
    }

    public function redirectOnUrl(): void
    {
        $url    = Shop::getAdminURL() . '/';
        $parsed = \parse_url($url);
        if (!isset($parsed['host'])) {
            return;
        }
        $host = $parsed['host'];
        if (!empty($parsed['port']) && (int)$parsed['port'] > 0) {
            $host .= ':' . $parsed['port'];
        }
        if (isset($_SERVER['HTTP_HOST']) && $host !== $_SERVER['HTTP_HOST'] && \mb_strlen($_SERVER['HTTP_HOST']) > 0) {
            \header('Location: ' . $url);
            exit;
        }
    }

    private function validateSession(): self
    {
        $this->loggedIn = false;
        if (
            isset($_SESSION['AdminAccount']->cLogin, $_SESSION['AdminAccount']->cPass, $_SESSION['AdminAccount']->cURL)
            && $_SESSION['AdminAccount']->cURL === \URL_SHOP
        ) {
            $account                  = $this->db->select(
                'tadminlogin',
                'cLogin',
                $_SESSION['AdminAccount']->cLogin,
                'cPass',
                $_SESSION['AdminAccount']->cPass
            );
            $this->twoFaAuthenticated = true;
            $this->loggedIn           = isset($account->cLogin);
            if ((int)($account->b2FAauth ?? 0) === 1) {
                $this->twoFaAuthenticated = ($_SESSION['AdminAccount']->TwoFA_valid ?? false) === true;
            }
            $this->checkIfValid();
        }

        return $this;
    }

    private function checkIfValid(): void
    {
        // handle upgrade path to 5.3.0
        if (!$this->db->tableExists('active_admin_sessions')) {
            return;
        }
        $persisted = $this->db->getSingleObject(
            'SELECT *
                FROM active_admin_sessions
                WHERE sessionID = :sid',
            ['sid' => \session_id()]
        );
        if ($persisted !== null) {
            $this->db->update(
                'active_admin_sessions',
                'sessionID',
                \session_id() ?: '',
                (object)['updated' => 'NOW()']
            );
            if ((int)$persisted->valid === 0) {
                $this->valid = false;
            }
        } else {
            $ins = (object)[
                'valid'     => 1,
                'userID'    => $_SESSION['AdminAccount']->kAdminlogin,
                'sessionID' => \session_id()
            ];
            $this->db->insert('active_admin_sessions', $ins);
        }
        $this->db->query('DELETE FROM active_admin_sessions WHERE DATEDIFF(NOW(), updated) > 14');
    }

    public function doTwoFA(): bool
    {
        if (!isset($_SESSION['AdminAccount']->cLogin, $_POST['TwoFA_code'])) {
            return false;
        }
        $twoFA = new BackendTwoFA($this->db, BackendUserData::getByName($_SESSION['AdminAccount']->cLogin, $this->db));
        $valid = $twoFA->isCodeValid($_POST['TwoFA_code']);

        $this->twoFaAuthenticated              = $valid;
        $_SESSION['AdminAccount']->TwoFA_valid = $valid;

        return $valid;
    }

    /**
     * @return stdClass[]
     */
    public function favorites(): array
    {
        return $this->logged()
            ? (new AdminFavorite($this->db))->fetchAll($this->getID())
            : [];
    }

    private function toSession(stdClass $admin): self
    {
        $group = $this->getPermissionsByGroup($admin->kAdminlogingruppe);
        if ($group !== null || (int)$admin->kAdminlogingruppe === \ADMINGROUP) {
            $_SESSION['AdminAccount']              = new stdClass();
            $_SESSION['AdminAccount']->cURL        = \URL_SHOP;
            $_SESSION['AdminAccount']->kAdminlogin = (int)$admin->kAdminlogin;
            $_SESSION['AdminAccount']->cLogin      = $admin->cLogin;
            $_SESSION['AdminAccount']->cMail       = $admin->cMail;
            $_SESSION['AdminAccount']->cPass       = $admin->cPass;
            $_SESSION['AdminAccount']->language    = $admin->language ?? 'de-DE';
            $_SESSION['AdminAccount']->attributes  = $admin->attributes;

            if (!\is_object($group)) {
                $group                    = new stdClass();
                $group->kAdminlogingruppe = \ADMINGROUP;
            }

            $_SESSION['AdminAccount']->oGroup = $group;

            $this->setLastLogin($admin->cLogin)
                ->setRetryCount($admin->cLogin, true)
                ->validateSession();
        }

        return $this;
    }

    private function setLastLogin(string $login): self
    {
        $this->db->update('tadminlogin', 'cLogin', $login, (object)['dLetzterLogin' => 'NOW()']);

        return $this;
    }

    private function setRetryCount(string $login, bool $reset = false): self
    {
        if ($reset) {
            $this->db->update(
                'tadminlogin',
                'cLogin',
                $login,
                (object)['nLoginVersuch' => 0, 'locked_at' => '_DBNULL_']
            );

            return $this;
        }
        $this->db->queryPrepared(
            'UPDATE tadminlogin
                SET nLoginVersuch = nLoginVersuch+1
                WHERE cLogin = :login',
            ['login' => $login]
        );
        $data   = $this->db->select('tadminlogin', 'cLogin', $login);
        $locked = (int)($data->nLoginVersuch ?? 0) >= \MAX_LOGIN_ATTEMPTS;
        if ($locked === true && \array_key_exists('locked_at', (array)$data)) {
            $this->db->update('tadminlogin', 'cLogin', $login, (object)['locked_at' => 'NOW()']);
        }

        return $this;
    }

    private function getPermissionsByGroup(int $groupID): ?stdClass
    {
        $group = $this->db->select(
            'tadminlogingruppe',
            'kAdminlogingruppe',
            $groupID
        );
        if ($group === null) {
            return null;
        }
        $group->kAdminlogingruppe = (int)$group->kAdminlogingruppe;
        $permissions              = $this->db->selectAll(
            'tadminrechtegruppe',
            'kAdminlogingruppe',
            $groupID,
            'cRecht'
        );
        $group->oPermission_arr   = pluck($permissions, 'cRecht');

        return $group;
    }

    /**
     * update password hash if necessary
     * @throws Exception
     */
    private function checkAndUpdateHash(#[\SensitiveParameter] string $password): bool
    {
        $passwordService = Shop::Container()->getPasswordService();
        // only update hash if the db update to 4.00+ was already executed
        if (
            !isset($_SESSION['AdminAccount']->cPass, $_SESSION['AdminAccount']->cLogin)
            || $passwordService->needsRehash($_SESSION['AdminAccount']->cPass) === false
        ) {
            return false;
        }
        $this->db->update(
            'tadminlogin',
            'cLogin',
            $_SESSION['AdminAccount']->cLogin,
            (object)['cPass' => $passwordService->hash($password)]
        );

        return true;
    }

    public function getID(): int
    {
        return (int)$_SESSION['AdminAccount']->kAdminlogin;
    }

    public function getGetText(): GetText
    {
        return $this->getText;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }
}
