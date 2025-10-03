<?php

declare(strict_types=1);

namespace JTL\Customer;

use DateInterval;
use DateTime;
use Exception;
use JTL\Catalog\Product\Preise;
use JTL\DB\DbInterface;
use JTL\GeneralDataProtection\Journal;
use JTL\Helpers\Date;
use JTL\Helpers\Form;
use JTL\Helpers\GeneralObject;
use JTL\Language\LanguageHelper;
use JTL\Language\LanguageModel;
use JTL\MagicCompatibilityTrait;
use JTL\Mail\Mail\Mail;
use JTL\Services\JTL\PasswordServiceInterface;
use JTL\Settings\Option\Customer as CustomerOption;
use JTL\Settings\Settings;
use JTL\Shop;
use JTL\Shopsetting;
use JTL\TwoFA\FrontendTwoFA;
use JTL\TwoFA\FrontendUserData;
use JTL\TwoFA\TwoFAEmergency;
use stdClass;

use function Functional\select;

/**
 * Class Customer
 * @package JTL\Customer
 */
class Customer
{
    use MagicCompatibilityTrait;

    public const OK = 1;

    public const ERROR_LOCKED = 2;

    public const ERROR_INACTIVE = 3;

    public const ERROR_CAPTCHA = 4;

    public const ERROR_NOT_ACTIVATED_YET = 5;

    public const ERROR_INVALID_TWO_FA = 6;

    public const ERROR_DO_TWO_FA = 7;

    public const ERROR_INVALID_DATA = 0;

    public const CUSTOMER_ANONYM = 'Anonym';

    public const CUSTOMER_DELETE_DONE = 0;

    public const CUSTOMER_DELETE_DEACT = 1;

    public const CUSTOMER_DELETE_NO = 2;

    public ?int $kKunde = null;

    public ?int $kKundengruppe = null;

    public ?int $kSprache = null;

    public ?int $nRegistriert = null;

    public string|null|float $fRabatt = 0.00;

    public string|null|float $fGuthaben = 0.00;

    public ?string $cKundenNr = null;

    public ?string $cPasswort = null;

    public ?string $cAnrede = '';

    public ?string $cAnredeLocalized = '';

    public ?string $cTitel = null;

    public ?string $cVorname = null;

    public ?string $cNachname = null;

    public ?string $cFirma = null;

    public ?string $cStrasse = '';

    public ?string $cHausnummer = null;

    public ?string $cAdressZusatz = null;

    public ?string $cPLZ = '';

    public ?string $cOrt = '';

    public string $cBundesland = '';

    public ?string $cLand = null;

    public ?string $cTel = null;

    public ?string $cMobil = null;

    public ?string $cFax = null;

    public string $cMail = '';

    public ?string $cUSTID = '';

    public ?string $cWWW = '';

    public string $cSperre = 'N';

    public string $cNewsletter = '';

    public ?string $dGeburtstag = null;

    public ?string $dGeburtstag_formatted = null;

    public ?string $cHerkunft = '';

    public ?string $cAktiv = null;

    public ?string $cAbgeholt = null;

    public ?string $dErstellt = null;

    public ?string $dVeraendert = null;

    public ?string $cZusatz = null;

    public ?string $cGuthabenLocalized = null;

    public ?string $angezeigtesLand = null;

    public ?string $dErstellt_DE = null;

    public ?string $cPasswortKlartext = null;

    public ?string $dSessionInvalidate = null;

    public int $nLoginversuche = 0;

    public int $b2FAauth = 0;

    public string $c2FAauthSecret = '';

    private bool $twoFaAuthenticated = false;

    private DbInterface $db;

    private PasswordServiceInterface $passwordService;

    /**
     * @var array<string, string>
     */
    public static array $mapping = [
        'cKundenattribut_arr' => 'CustomerAttributes'
    ];

    protected ?string $dLastLogin = null;

    public function __construct(
        ?int $id = null,
        ?PasswordServiceInterface $passwordService = null,
        ?DbInterface $db = null
    ) {
        $this->passwordService = $passwordService ?? Shop::Container()->getPasswordService();
        $this->db              = $db ?? Shop::Container()->getDB();
        if ($id > 0) {
            $this->loadFromDB($id);
        }
    }

    /**
     * @return string[]
     */
    public function __sleep(): array
    {
        return select(
            \array_keys(\get_object_vars($this)),
            fn(string $e): bool => $e !== 'db' && $e !== 'passwordService'
        );
    }

    public function __wakeup(): void
    {
        $this->passwordService = Shop::Container()->getPasswordService();
        $this->db              = Shop::Container()->getDB();
    }

    private function getDB(): DbInterface
    {
        if (isset($this->db) === false) {
            $this->db = Shop::Container()->getDB();
        }

        return $this->db;
    }

    private function getPasswordService(): PasswordServiceInterface
    {
        if (isset($this->passwordService) === false) {
            $this->passwordService = Shop::Container()->getPasswordService();
        }

        return $this->passwordService;
    }

    public function holRegKundeViaEmail(string $mail): ?Customer
    {
        $id = $this->getDB()->getSingleInt(
            'SELECT kKunde 
                FROM tkunde
                WHERE cMail = :ml',
            'kKunde',
            ['ml' => $mail]
        );

        return $id > 0 ? new self($id) : null;
    }

    /**
     * @param array<string, string> $post
     * @return true|int - true, if captcha verified or no captcha necessary
     */
    public function verifyLoginCaptcha(array $post): bool|int
    {
        $conf = Settings::intValue(CustomerOption::MAX_LOGIN_TRIES);
        $mail = $post['email'] ?? '';
        if ($mail !== '' && $conf > 1) {
            $attempts = $this->getDB()->getSingleInt(
                'SELECT nLoginversuche
                    FROM tkunde
                    WHERE cMail = :ml AND nRegistriert = 1',
                'nLoginversuche',
                ['ml' => $mail]
            );
            if ($attempts >= $conf) {
                if (Form::validateCaptcha($_POST)) {
                    return true;
                }

                return $attempts;
            }
        }

        return true;
    }

    /**
     * @throws Exception
     */
    public function holLoginKunde(
        string $username,
        #[\SensitiveParameter] string $password,
        ?string $twoFACode = null
    ): int {
        if ($username === '' || $password === '') {
            return self::ERROR_INVALID_DATA;
        }
        $user = $this->checkCredentials($username, $password);
        if ($user !== false && $user->b2FAauth === 1 && Settings::boolValue(CustomerOption::ENABLE_2FA)) {
            if ($twoFACode === null) {
                return self::ERROR_DO_TWO_FA;
            }
            $userData = new FrontendUserData($user->kKunde, $user->cMail, $user->c2FAauthSecret, (bool)$user->b2FAauth);
            if ($this->doTwoFA($userData, $twoFACode) === false) {
                return self::ERROR_INVALID_TWO_FA;
            }
        }
        if (($state = $this->validateCustomerData($user)) !== self::OK) {
            return $state;
        }
        if ($user !== false && $user->kKunde > 0) {
            $this->initCustomer($user);
        }
        \executeHook(\HOOK_KUNDE_CLASS_HOLLOGINKUNDE, [
            'oKunde'        => &$this,
            'oUser'         => $user,
            'cBenutzername' => $username,
            'cPasswort'     => $password
        ]);
        if ($this->kKunde <= 0) {
            return self::ERROR_INVALID_DATA;
        }
        $this->decryptCustomerData();
        $this->cAnredeLocalized   = self::mapSalutation($this->cAnrede, $this->kSprache);
        $this->cGuthabenLocalized = $this->gibGuthabenLocalized();
        $this->setLastLogin();

        return self::OK;
    }

    public function doTwoFA(FrontendUserData $userData, string $code): bool
    {
        $twoFA = new FrontendTwoFA($this->getDB(), $userData);

        $this->twoFaAuthenticated = $twoFA->isCodeValid($code);

        return $this->twoFaAuthenticated;
    }

    private function validateCustomerData(false|stdClass $user): int
    {
        if ($user === false) {
            return self::ERROR_INVALID_DATA;
        }
        if ($user->cSperre === 'Y') {
            return self::ERROR_LOCKED;
        }
        if ($user->cAktiv === 'N') {
            return $user->cAbgeholt === 'Y' ? self::ERROR_INACTIVE : self::ERROR_NOT_ACTIVATED_YET;
        }

        return self::OK;
    }

    /**
     * @throws Exception
     */
    private function initCustomer(stdClass $user): void
    {
        foreach (\get_object_vars($user) as $k => $v) {
            $this->$k = $v;
        }
        $this->angezeigtesLand = LanguageHelper::getCountryCodeByCountryName($this->cLand ?? '');
        // check if password has to be updated because of PASSWORD_DEFAULT method changes or using old md5 hash
        if (isset($user->cPasswort) && $this->getPasswordService()->needsRehash($user->cPasswort)) {
            $upd            = new stdClass();
            $upd->cPasswort = $this->getPasswordService()->hash($user->cPasswort);
            $this->getDB()->update('tkunde', 'kKunde', (int)$user->kKunde, $upd);
        }
    }

    /**
     * @throws Exception
     */
    public function checkCredentials(string $user, #[\SensitiveParameter] string $pass): false|stdClass
    {
        $user     = \mb_substr($user, 0, 255);
        $pass     = \mb_substr($pass, 0, 255);
        $customer = $this->getDB()->select(
            'tkunde',
            'cMail',
            $user,
            'nRegistriert',
            1,
            null,
            null,
            false,
            '*, date_format(dGeburtstag, \'%d.%m.%Y\') AS dGeburtstag_formatted'
        );
        if (!$customer) {
            return false;
        }
        $customer->b2FAauth              = (int)($customer->b2FAauth ?? 0);
        $customer->kKunde                = (int)$customer->kKunde;
        $customer->kKundengruppe         = (int)$customer->kKundengruppe;
        $customer->kSprache              = (int)$customer->kSprache;
        $customer->nLoginversuche        = (int)$customer->nLoginversuche;
        $customer->nRegistriert          = (int)$customer->nRegistriert;
        $customer->dGeburtstag_formatted = $customer->dGeburtstag_formatted !== '00.00.0000'
            ? $customer->dGeburtstag_formatted
            : '';

        if (!$this->getPasswordService()->verify($pass, $customer->cPasswort)) {
            $tries = ++$customer->nLoginversuche;
            $this->getDB()->update('tkunde', 'cMail', $user, (object)['nLoginversuche' => $tries]);

            return false;
        }
        $update = false;
        if ($this->getPasswordService()->needsRehash($customer->cPasswort)) {
            $customer->cPasswort = $this->getPasswordService()->hash($pass);
            $update              = true;
        }

        if ($customer->nLoginversuche > 0) {
            $customer->nLoginversuche = 0;
            $update                   = true;
        }
        if ($update) {
            $customerData = (array)$customer;
            unset($customerData['dGeburtstag_formatted']);
            $this->getDB()->update('tkunde', 'kKunde', $customer->kKunde, (object)$customerData);
        }

        return $customer;
    }

    protected function setLastLogin(): void
    {
        $this->dLastLogin = (new DateTime())->format('Y-m-d H:i:s');
        $this->getDB()->queryPrepared(
            'UPDATE tkunde SET dLastLogin = :today WHERE kKunde = :kKunde',
            ['kKunde' => (int)$this->kKunde, 'today' => $this->dLastLogin]
        );
    }

    public function getLastLogin(): ?string
    {
        return $this->dLastLogin;
    }

    public function gibGuthabenLocalized(): string
    {
        return Preise::getLocalizedPriceString($this->fGuthaben);
    }

    public function loadFromDB(int $id): self
    {
        if ($id <= 0) {
            return $this;
        }
        $data = $this->getDB()->select('tkunde', 'kKunde', $id);
        if ($data === null || $data->kKunde <= 0) {
            return $this;
        }
        $this->kKunde           = (int)$data->kKunde;
        $this->kKundengruppe    = (int)$data->kKundengruppe;
        $this->kSprache         = (int)$data->kSprache;
        $this->cKundenNr        = $data->cKundenNr;
        $this->cPasswort        = $data->cPasswort;
        $this->cAnrede          = $data->cAnrede;
        $this->cTitel           = $data->cTitel;
        $this->cVorname         = $data->cVorname;
        $this->cNachname        = $data->cNachname;
        $this->cFirma           = $data->cFirma;
        $this->cZusatz          = $data->cZusatz;
        $this->cStrasse         = $data->cStrasse;
        $this->cHausnummer      = $data->cHausnummer;
        $this->cAdressZusatz    = $data->cAdressZusatz;
        $this->cPLZ             = $data->cPLZ;
        $this->cOrt             = $data->cOrt;
        $this->cBundesland      = $data->cBundesland;
        $this->cLand            = $data->cLand;
        $this->cTel             = $data->cTel;
        $this->cMobil           = $data->cMobil;
        $this->cFax             = $data->cFax;
        $this->cMail            = $data->cMail;
        $this->cUSTID           = $data->cUSTID;
        $this->cWWW             = $data->cWWW;
        $this->cSperre          = $data->cSperre;
        $this->fGuthaben        = $data->fGuthaben;
        $this->cNewsletter      = $data->cNewsletter;
        $this->dGeburtstag      = $data->dGeburtstag;
        $this->fRabatt          = $data->fRabatt;
        $this->cHerkunft        = $data->cHerkunft;
        $this->dErstellt        = $data->dErstellt;
        $this->dVeraendert      = $data->dVeraendert;
        $this->cAktiv           = $data->cAktiv;
        $this->cAbgeholt        = $data->cAbgeholt;
        $this->nRegistriert     = (int)$data->nRegistriert;
        $this->nLoginversuche   = (int)$data->nLoginversuche;
        $this->dLastLogin       = $data->dLastLogin;
        $this->cAnredeLocalized = self::mapSalutation($this->cAnrede, $this->kSprache);
        $this->angezeigtesLand  = LanguageHelper::getCountryCodeByCountryName($data->cLand);
        $this->b2FAauth         = (int)$data->b2FAauth;
        $this->c2FAauthSecret   = $data->c2FAauthSecret;
        $this->decryptCustomerData();

        $this->dGeburtstag_formatted = $this->dGeburtstag === null
            ? ''
            : (new DateTime($this->dGeburtstag))->format('d.m.Y');

        $this->cGuthabenLocalized = $this->gibGuthabenLocalized();
        $this->dErstellt_DE       = $this->dErstellt !== null
            ? (new DateTime($this->dErstellt))->format('d.m.Y')
            : null;
        \executeHook(\HOOK_KUNDE_CLASS_LOADFROMDB);

        return $this;
    }

    private function encryptCustomerData(): self
    {
        $cryptoService = Shop::Container()->getCryptoService();

        $this->cNachname = $cryptoService->encryptXTEA(\trim($this->cNachname ?? ''));
        $this->cFirma    = $cryptoService->encryptXTEA(\trim($this->cFirma ?? ''));
        $this->cZusatz   = $cryptoService->encryptXTEA(\trim($this->cZusatz ?? ''));
        $this->cStrasse  = $cryptoService->encryptXTEA(\trim($this->cStrasse ?? ''));

        return $this;
    }

    private function decryptCustomerData(): self
    {
        $cryptoService = Shop::Container()->getCryptoService();

        $this->cNachname = \trim($cryptoService->decryptXTEA($this->cNachname ?? ''));
        $this->cFirma    = \trim($cryptoService->decryptXTEA($this->cFirma ?? ''));
        $this->cZusatz   = \trim($cryptoService->decryptXTEA($this->cZusatz ?? ''));
        $this->cStrasse  = \trim($cryptoService->decryptXTEA($this->cStrasse ?? ''));

        return $this;
    }

    public function insertInDB(): int
    {
        \executeHook(\HOOK_KUNDE_DB_INSERT, ['oKunde' => &$this]);

        $this->encryptCustomerData();
        $obj                     = new stdClass();
        $obj->kKundengruppe      = $this->kKundengruppe;
        $obj->kSprache           = $this->kSprache;
        $obj->cKundenNr          = $this->cKundenNr;
        $obj->cPasswort          = $this->cPasswort;
        $obj->cAnrede            = $this->cAnrede;
        $obj->cTitel             = $this->cTitel;
        $obj->cVorname           = $this->cVorname;
        $obj->cNachname          = $this->cNachname;
        $obj->cFirma             = $this->cFirma;
        $obj->cZusatz            = $this->cZusatz;
        $obj->cStrasse           = $this->cStrasse;
        $obj->cHausnummer        = $this->cHausnummer;
        $obj->cAdressZusatz      = $this->cAdressZusatz;
        $obj->cPLZ               = $this->cPLZ;
        $obj->cOrt               = $this->cOrt;
        $obj->cBundesland        = $this->cBundesland;
        $obj->cLand              = $this->cLand;
        $obj->cTel               = $this->cTel;
        $obj->cMobil             = $this->cMobil;
        $obj->cFax               = $this->cFax;
        $obj->cMail              = $this->cMail;
        $obj->cUSTID             = $this->cUSTID;
        $obj->cWWW               = $this->cWWW;
        $obj->cSperre            = $this->cSperre;
        $obj->fGuthaben          = $this->fGuthaben;
        $obj->cNewsletter        = $this->cNewsletter;
        $obj->fRabatt            = $this->fRabatt;
        $obj->cHerkunft          = $this->cHerkunft;
        $obj->dErstellt          = $this->dErstellt ?? '_DBNULL_';
        $obj->dVeraendert        = $this->dVeraendert ?? 'NOW()';
        $obj->cAktiv             = $this->cAktiv;
        $obj->cAbgeholt          = $this->cAbgeholt;
        $obj->nRegistriert       = $this->nRegistriert;
        $obj->nLoginversuche     = $this->nLoginversuche;
        $obj->c2FAauthSecret     = $this->c2FAauthSecret;
        $obj->b2FAauth           = $this->b2FAauth;
        $obj->dSessionInvalidate = $this->dSessionInvalidate ?? '_DBNULL_';
        $obj->dGeburtstag        = Date::convertDateToMysqlStandard($this->dGeburtstag);
        $obj->cLand              = $this->pruefeLandISO($obj->cLand);
        $this->kKunde            = $this->getDB()->insert('tkunde', $obj);
        $this->decryptCustomerData();

        $this->cAnredeLocalized   = self::mapSalutation($this->cAnrede, $this->kSprache);
        $this->cGuthabenLocalized = $this->gibGuthabenLocalized();
        if ($this->dErstellt !== null) {
            if (\mb_convert_case($this->dErstellt, \MB_CASE_LOWER) === 'now()') {
                $this->dErstellt = (new DateTime())->format('Y-m-d');
            }
            $this->dErstellt_DE = (new DateTime($this->dErstellt))->format('d.m.Y');
        }

        return $this->kKunde;
    }

    public function updateInDB(): int
    {
        if ($this->kKunde === null) {
            return 0;
        }
        $this->dGeburtstag           = Date::convertDateToMysqlStandard($this->dGeburtstag);
        $this->dGeburtstag_formatted = $this->dGeburtstag === '_DBNULL_'
            ? ''
            : Date::safeDateFormat($this->dGeburtstag, 'd.m.Y', '', 'Y-m-d');

        $this->encryptCustomerData();
        $obj     = GeneralObject::copyMembers($this);
        $oldData = $this->getDB()->select('tkunde', 'kKunde', $obj->kKunde);
        unset(
            $obj->cPasswort,
            $obj->angezeigtesLand,
            $obj->dGeburtstag_formatted,
            $obj->Anrede,
            $obj->cAnredeLocalized,
            $obj->cGuthabenLocalized,
            $obj->dErstellt_DE,
            $obj->cPasswortKlartext,
            $obj->dLastLogin,
        );
        if ($obj->dGeburtstag === null || $obj->dGeburtstag === '') {
            $obj->dGeburtstag = '_DBNULL_';
        }
        if ($obj->dErstellt === null || $obj->dErstellt === '') {
            $obj->dErstellt = '_DBNULL_';
        }
        if ($obj->dSessionInvalidate === null || $obj->dSessionInvalidate === '') {
            $obj->dSessionInvalidate = '_DBNULL_';
        }
        $obj->cLand          = $this->pruefeLandISO($obj->cLand);
        $obj->dVeraendert    = 'NOW()';
        $obj->b2FAauth       = $this->b2FAauth;
        $obj->c2FAauthSecret = $this->c2FAauthSecret;
        if (
            $oldData !== null
            && ($obj->cMail !== $oldData->cMail || $obj->b2FAauth !== (int)$oldData->b2FAauth)
        ) {
            $obj->dSessionInvalidate = 'NOW()';
        }
        $return = $this->getDB()->update('tkunde', 'kKunde', $obj->kKunde, $obj);
        $this->decryptCustomerData();

        $this->cAnredeLocalized   = self::mapSalutation($this->cAnrede, $this->kSprache);
        $this->cGuthabenLocalized = $this->gibGuthabenLocalized();
        $this->dErstellt_DE       = $this->dErstellt !== null
            ? (new DateTime($this->dErstellt))->format('d.m.Y')
            : null;

        return $return;
    }

    public function pruefeLandISO(string $iso): string
    {
        \preg_match('/[a-zA-Z]{2}/', $iso, $hits);
        if (\mb_strlen($hits[0] ?? '') !== \mb_strlen($iso)) {
            $cISO = LanguageHelper::getIsoCodeByCountryName($iso);
            if ($cISO !== 'noISO' && $cISO !== '') {
                $iso = $cISO;
            }
        }

        return $iso;
    }

    public function kopiereSession(): self
    {
        foreach (\array_keys(\get_object_vars($_SESSION['Kunde'])) as $oElement) {
            $this->$oElement = $_SESSION['Kunde']->$oElement;
        }
        $this->cAnredeLocalized = self::mapSalutation($this->cAnrede, $this->kSprache);

        return $this;
    }

    public function verschluesselAlleKunden(): self
    {
        foreach ($this->getDB()->getObjects('SELECT * FROM tkunde') as $customer) {
            if ($customer->kKunde > 0) {
                unset($tmp);
                $tmp = new self((int)$customer->kKunde);
                $tmp->updateInDB();
            }
        }

        return $this;
    }

    public static function isEqual(Customer $customer1, Customer $customer2): bool
    {
        $members1 = \array_keys(\get_class_vars(\get_class($customer1)));
        $members2 = \array_keys(\get_class_vars(\get_class($customer2)));
        if (\count($members1) !== \count($members2)) {
            return false;
        }
        foreach ($members1 as $member) {
            if (!isset($customer2->$member)) {
                return false;
            }
            $value1 = $customer1->$member;
            $value2 = null;
            foreach ($members2 as $member2) {
                if ($member === $member2) {
                    $value2 = $customer2->$member;
                }
            }
            if ($value1 !== $value2) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws Exception
     */
    public function updatePassword(#[\SensitiveParameter] ?string $password = null): self
    {
        if ($password === null) {
            $clearTextPassword = $this->getPasswordService()->generate(12);
            $this->cPasswort   = $this->getPasswordService()->hash($clearTextPassword);

            $upd                     = new stdClass();
            $upd->cPasswort          = $this->cPasswort;
            $upd->nLoginversuche     = 0;
            $upd->dSessionInvalidate = 'NOW()';
            $this->getDB()->update('tkunde', 'kKunde', (int)$this->kKunde, $upd);

            $obj                 = new stdClass();
            $obj->tkunde         = $this;
            $obj->neues_passwort = $clearTextPassword;

            $mailer = Shop::Container()->getMailer();
            $mail   = new Mail();
            $mailer->send($mail->createFromTemplateID(\MAILTEMPLATE_PASSWORT_VERGESSEN, $obj));

            return $this;
        }
        $this->cPasswort = $this->getPasswordService()->hash(\mb_substr($password, 0, 255));

        $upd                     = new stdClass();
        $upd->cPasswort          = $this->cPasswort;
        $upd->nLoginversuche     = 0;
        $upd->dSessionInvalidate = 'NOW()';
        $this->getDB()->update('tkunde', 'kKunde', (int)$this->kKunde, $upd);

        return $this;
    }

    /**
     * @throws Exception
     */
    public function prepareResetPassword(bool $requestedByCustomer = true): bool
    {
        $cryptoService = Shop::Container()->getCryptoService();
        if (!$this->kKunde) {
            return false;
        }
        $key        = $cryptoService->randomString(32);
        $linkHelper = Shop::Container()->getLinkService();
        $expires    = new DateTime();
        $interval   = new DateInterval('P1D');
        $expires->add($interval);
        $this->getDB()->queryPrepared(
            'INSERT INTO tpasswordreset(kKunde, cKey, dExpires)
                VALUES (:kKunde, :cKey, :dExpires)
                ON DUPLICATE KEY UPDATE cKey = :cKey, dExpires = :dExpires',
            [
                'kKunde'   => $this->kKunde,
                'cKey'     => $key,
                'dExpires' => $expires->format('Y-m-d H:i:s'),
            ]
        );

        $customerLangCode       = LanguageHelper::getLanguageDataByType('', $this->getLanguageID());
        $obj                    = new stdClass();
        $obj->tkunde            = $this;
        $obj->passwordResetLink = $linkHelper->getStaticRoute(
            'pass.php',
            true,
            true,
            \is_string($customerLangCode) ? $customerLangCode : null,
        );
        $obj->passwordResetLink .= '?' . \http_build_query(['fpwh' => $key]);
        $obj->cHash             = $key;
        $obj->neues_passwort    = 'Es ist leider ein Fehler aufgetreten. Bitte kontaktieren Sie uns.';

        $mailer     = Shop::Container()->getMailer();
        $mail       = new Mail();
        $sendResult = $mailer->send($mail->createFromTemplateID(\MAILTEMPLATE_PASSWORT_VERGESSEN, $obj));
        if (
            $requestedByCustomer === false
            && $sendResult
            && $this->has2FA()
        ) {
            $this->disable2FAandDeleteCodes();
        }

        return true;
    }

    public function getID(): int
    {
        return (int)$this->kKunde;
    }

    public function getGroupID(): int
    {
        $customerGroupID = (int)$this->kKundengruppe > 0 ? (int)$this->kKundengruppe : CustomerGroup::getCurrent();

        return $customerGroupID > 0 ? $customerGroupID : CustomerGroup::getDefaultGroupID();
    }

    public function getLanguageID(): int
    {
        return (int)$this->kSprache;
    }

    public function setLanguageID(int $languageID): void
    {
        $this->kSprache = $languageID;
    }

    public function isLoggedIn(): bool
    {
        return $this->kKunde > 0 && isset($_SESSION['Kunde']->kKunde) && $_SESSION['Kunde']->kKunde === $this->kKunde;
    }

    public function getDiscount(): float
    {
        return (float)$this->fRabatt;
    }

    /**
     * @former mappeKundenanrede()
     */
    public static function mapSalutation(?string $salutation, ?int $languageID, ?int $customerID = 0): string
    {
        if ($salutation === null) {
            return '';
        }
        if (($languageID <= 0 && $customerID <= 0) || $salutation === '') {
            return $salutation;
        }
        if ($languageID === 0 && $customerID > 0) {
            $customerLangID = Shop::Container()->getDB()->getSingleInt(
                'SELECT kSprache
                    FROM tkunde
                    WHERE kKunde = :cid',
                'kSprache',
                ['cid' => $customerID]
            );
            if ($customerLangID > 0) {
                $languageID = $customerLangID;
            }
        }
        $lang     = null;
        $langCode = '';
        if ($languageID > 0) { // Kundensprache, falls gesetzt und gültig
            try {
                $lang       = Shop::Lang()->getLanguageByID($languageID);
                $langCode   = $lang->getCode();
                $languageID = $lang->getId();
            } catch (Exception) {
                $lang = null;
            }
        }
        if ($lang === null) { // Ansonsten Standardsprache
            $default    = Shop::Lang()->getDefaultLanguage();
            $langCode   = $default->getCode();
            $languageID = $default->getId();
        }
        if ($languageID === Shop::getLanguageID()) {
            return Shop::Lang()->get($salutation === 'm' ? 'salutationM' : 'salutationW');
        }
        $value = Shop::Container()->getDB()->getSingleObject(
            'SELECT tsprachwerte.cWert
                FROM tsprachwerte
                JOIN tsprachiso
                    ON tsprachiso.cISO = :ciso
                WHERE tsprachwerte.kSprachISO = tsprachiso.kSprachISO
                    AND tsprachwerte.cName = :cname',
            ['ciso' => $langCode, 'cname' => $salutation === 'm' ? 'salutationM' : 'salutationW']
        );
        if ($value !== null && $value->cWert !== '') {
            $salutation = $value->cWert;
        }

        return $salutation;
    }

    public function deleteAccount(
        string $issuerType,
        int $issuerID,
        bool $force = false,
        bool $confirmationMail = false
    ): int {
        $customerID = $this->getID();
        if (empty($customerID)) {
            return self::CUSTOMER_DELETE_NO;
        }
        if ($force) {
            $this->erasePersonalData($issuerType, $issuerID);

            return self::CUSTOMER_DELETE_DONE;
        }
        $openOrders = $this->getOpenOrders();
        if (!$openOrders) {
            $this->erasePersonalData($issuerType, $issuerID);
            $logMessage = \sprintf('Account with ID kKunde = %s deleted', $customerID);

            $retVal = self::CUSTOMER_DELETE_DONE;
        } else {
            if ($this->nRegistriert === 0) {
                return self::CUSTOMER_DELETE_NO;
            }
            $this->getDB()->update(
                'tkunde',
                'kKunde',
                $customerID,
                (object)[
                    'cPasswort'    => '',
                    'nRegistriert' => 0,
                ]
            );
            $logMessage = \sprintf(
                'Account with ID kKunde = %s deleted, but had %s open orders with %s still in cancellation time. ' .
                'Account is deactivated until all orders are completed.',
                $customerID,
                $openOrders->openOrders,
                $openOrders->ordersInCancellationTime
            );

            (new Journal())->addEntry(
                $issuerType,
                $customerID,
                Journal::ACTION_CUSTOMER_DEACTIVATED,
                $logMessage,
                (object)['kKunde' => $customerID]
            );

            $retVal = self::CUSTOMER_DELETE_DEACT;
        }
        Shop::Container()->getLogService()->notice($logMessage);
        if ($confirmationMail) {
            $mailer = Shop::Container()->getMailer();
            $mail   = new Mail();
            $mailer->send(
                $mail->createFromTemplateID(
                    \MAILTEMPLATE_KUNDENACCOUNT_GELOESCHT,
                    (object)['tkunde' => $this]
                )
            );
        }

        return $retVal;
    }

    /**
     * @return false|stdClass
     */
    public function getOpenOrders(): bool|stdClass
    {
        $cancellationTime = Shopsetting::getInstance($this->getDB())->getValue(
            \CONF_GLOBAL,
            'global_cancellation_time'
        );
        $customerID       = $this->getID();

        $openOrderCount                = $this->getDB()->getSingleInt(
            'SELECT COUNT(kBestellung) AS orderCount
                FROM tbestellung
                WHERE cStatus NOT IN (:orderSent, :orderCanceled)
                    AND kKunde = :customerId',
            'orderCount',
            [
                'customerId'    => $customerID,
                'orderSent'     => \BESTELLUNG_STATUS_VERSANDT,
                'orderCanceled' => \BESTELLUNG_STATUS_STORNO,
            ]
        );
        $ordersInCancellationTimeCount = $this->getDB()->getSingleInt(
            'SELECT COUNT(kBestellung) AS orderCount
                FROM tbestellung
                WHERE kKunde = :customerId
                    AND cStatus = :orderSent
                    AND DATE(dVersandDatum) > DATE_SUB(NOW(), INTERVAL :cancellationTime DAY)',
            'orderCount',
            [
                'customerId'       => $customerID,
                'orderSent'        => \BESTELLUNG_STATUS_VERSANDT,
                'cancellationTime' => $cancellationTime,
            ]
        );

        if ($openOrderCount > 0 || $ordersInCancellationTimeCount > 0) {
            return (object)[
                'ordersInCancellationTime' => $ordersInCancellationTimeCount,
                'openOrders'               => $openOrderCount
            ];
        }

        return false;
    }

    public function getCustomerAttributes(bool $force = false): CustomerAttributes
    {
        static $customerAttributes = null;

        if ($customerAttributes === null || $force) {
            $customerAttributes = new CustomerAttributes($this->getID());
        }

        return $customerAttributes;
    }

    private function syncDeletion(string $issuerType): int
    {
        if ($this->cAbgeholt !== 'Y') {
            return 0;
        }

        $syncData              = new stdClass();
        $syncData->kKunde      = $this->getID();
        $syncData->customer_id = $this->cKundenNr;
        $syncData->deleted     = 'NOW()';
        $syncData->issuer      = $issuerType;
        $syncData->ack         = 0;

        return $this->getDB()->upsert('deleted_customers', $syncData, ['kKunde', 'customer_id']);
    }

    private function erasePersonalData(string $issuerType, int $issuerID): void
    {
        $customerID = $this->getID();
        $db         = $this->getDB();
        if (empty($customerID)) {
            return;
        }
        $db->delete('tlieferadresse', 'kKunde', $customerID);
        $db->delete('trechnungsadresse', 'kKunde', $customerID);
        $db->delete('tkundenattribut', 'kKunde', $customerID);
        $db->update(
            'tkunde',
            'kKunde',
            $customerID,
            (object)[
                'cKundenNr'     => self::CUSTOMER_ANONYM,
                'cPasswort'     => '',
                'cAnrede'       => '',
                'cTitel'        => '',
                'cVorname'      => self::CUSTOMER_ANONYM,
                'cNachname'     => self::CUSTOMER_ANONYM,
                'cFirma'        => '',
                'cZusatz'       => '',
                'cStrasse'      => '',
                'cHausnummer'   => '',
                'cAdressZusatz' => '',
                'cPLZ'          => '',
                'cOrt'          => '',
                'cBundesland'   => '',
                'cLand'         => '',
                'cTel'          => '',
                'cMobil'        => '',
                'cFax'          => '',
                'cMail'         => self::CUSTOMER_ANONYM,
                'cUSTID'        => '',
                'cWWW'          => '',
                'cSperre'       => 'Y',
                'fGuthaben'     => 0,
                'cNewsletter'   => 'N',
                'dGeburtstag'   => '_DBNULL_',
                'fRabatt'       => 0,
                'cHerkunft'     => '',
                'dVeraendert'   => 'now()',
                'cAktiv'        => 'N',
                'nRegistriert'  => 0,
                'cAbgeholt'     => 'Y',
            ]
        );
        $this->syncDeletion($issuerType);
        $db->delete('tkundendatenhistory', 'kKunde', $customerID);
        $db->delete('tkundenkontodaten', 'kKunde', $customerID);
        $db->delete('tzahlungsinfo', 'kKunde', $customerID);
        $db->delete('tkontakthistory', 'cMail', $this->cMail);
        $db->delete('tproduktanfragehistory', 'cMail', $this->cMail);
        $db->delete('tverfuegbarkeitsbenachrichtigung', 'cMail', $this->cMail);

        $db->update('tbewertung', 'kKunde', $customerID, (object)['cName' => self::CUSTOMER_ANONYM]);
        $db->update(
            'tnewskommentar',
            'kKunde',
            $customerID,
            (object)[
                'cName'  => self::CUSTOMER_ANONYM,
                'cEmail' => self::CUSTOMER_ANONYM
            ]
        );
        $db->queryPrepared(
            'DELETE FROM tnewsletterempfaenger
                WHERE cEmail = :email
                    OR kKunde = :customerID',
            ['email' => $this->cMail, 'customerID' => $customerID]
        );

        $obj            = new stdClass();
        $obj->cAnrede   = self::CUSTOMER_ANONYM;
        $obj->cVorname  = self::CUSTOMER_ANONYM;
        $obj->cNachname = self::CUSTOMER_ANONYM;
        $obj->cEmail    = self::CUSTOMER_ANONYM;
        $db->update('tnewsletterempfaengerhistory', 'kKunde', $customerID, $obj);
        $db->update('tnewsletterempfaengerhistory', 'cEmail', $this->cMail, $obj);

        $db->insert(
            'tnewsletterempfaengerhistory',
            (object)[
                'kSprache'     => $this->kSprache,
                'kKunde'       => $customerID,
                'cAnrede'      => self::CUSTOMER_ANONYM,
                'cVorname'     => self::CUSTOMER_ANONYM,
                'cNachname'    => self::CUSTOMER_ANONYM,
                'cEmail'       => self::CUSTOMER_ANONYM,
                'cOptCode'     => '',
                'cLoeschCode'  => '',
                'cAktion'      => 'Geloescht',
                'dAusgetragen' => 'NOW()',
                'dEingetragen' => '_DBNULL_',
                'dOptCode'     => '_DBNULL_'
            ]
        );
        $db->queryPrepared(
            'DELETE twunschliste, twunschlistepos, twunschlisteposeigenschaft, twunschlisteversand
                FROM twunschliste
                LEFT JOIN twunschlistepos
                    ON twunschliste.kWunschliste = twunschlistepos.kWunschliste
                LEFT JOIN twunschlisteposeigenschaft
                    ON twunschlisteposeigenschaft.kWunschlistePos = twunschlistepos.kWunschlistePos
                LEFT JOIN twunschlisteversand
                    ON twunschlisteversand.kWunschliste = twunschliste.kWunschliste
                WHERE twunschliste.kKunde = :customerID',
            ['customerID' => $customerID]
        );
        $db->queryPrepared(
            'DELETE twarenkorbpers, twarenkorbperspos, twarenkorbpersposeigenschaft
                FROM twarenkorbpers
                LEFT JOIN twarenkorbperspos
                    ON twarenkorbperspos.kWarenkorbPers = twarenkorbpers.kWarenkorbPers
                LEFT JOIN twarenkorbpersposeigenschaft
                    ON twarenkorbpersposeigenschaft.kWarenkorbPersPos = twarenkorbperspos.kWarenkorbPersPos
                WHERE twarenkorbpers.kKunde = :customerID',
            ['customerID' => $customerID]
        );

        $logMessage = \sprintf('Account with ID kKunde = %s deleted', $customerID);
        (new Journal())->addEntry(
            $issuerType,
            $issuerID,
            Journal::ACTION_CUSTOMER_DELETED,
            $logMessage,
            (object)['kKunde' => $customerID]
        );
    }

    public function localize(LanguageModel $lang): self
    {
        $oldLangCode = Shop::Lang()->gibISO();
        if ($oldLangCode !== $lang->getCode()) {
            Shop::Lang()->setzeSprache($lang->getCode());
        }
        if ($this->cAnrede === 'w') {
            $this->cAnredeLocalized = Shop::Lang()->get('salutationW');
        } elseif ($this->cAnrede === 'm') {
            $this->cAnredeLocalized = Shop::Lang()->get('salutationM');
        } else {
            $this->cAnredeLocalized = Shop::Lang()->get('salutationGeneral');
        }
        if ($this->cLand !== null) {
            if (isset($_SESSION['Kunde'])) {
                $_SESSION['Kunde']->cLand = $this->cLand;
            }
            if (($country = Shop::Container()->getCountryService()->getCountry($this->cLand)) !== null) {
                $this->angezeigtesLand = $country->getName($lang->getId());
            }
        }
        Shop::Lang()->setzeSprache($oldLangCode);

        return $this;
    }

    public function has2FA(): bool
    {
        return $this->b2FAauth === 1;
    }

    public function set2FAauth(int $b2FAauth): void
    {
        $this->b2FAauth = $b2FAauth;
    }

    public function get2FASecret(): string
    {
        return $this->c2FAauthSecret;
    }

    public function set2FASecret(string $secret): void
    {
        $this->c2FAauthSecret = $secret;
    }

    public function isTwoFaAuthenticated(): bool
    {
        return $this->twoFaAuthenticated;
    }

    private function disable2FAandDeleteCodes(): void
    {
        $this->set2FASecret('');
        $this->set2FAauth(0);
        if ($this->updateInDB() > 0) {
            (new TwoFAEmergency($this->getDB()))->removeExistingCodes(
                FrontendUserData::getByID(
                    (int)$this->kKunde,
                    $this->getDB()
                )
            );
        }
    }

    public function getOrderCount(): int
    {
        return $this->getDB()->getSingleInt(
            'SELECT COUNT(kBestellung) AS orderCount
                FROM tbestellung
                WHERE tbestellung.kKunde = :customerID
                  AND (cStatus = :paid OR cStatus = :shipped)',
            'orderCount',
            [
                'customerID' => $this->getID(),
                'paid'       => \BESTELLUNG_STATUS_BEZAHLT,
                'shipped'    => \BESTELLUNG_STATUS_VERSANDT,
            ]
        );
    }
}
