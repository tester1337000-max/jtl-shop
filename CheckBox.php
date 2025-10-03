<?php

declare(strict_types=1);

namespace JTL;

use Exception;
use InvalidArgumentException;
use JTL\Cache\JTLCacheInterface;
use JTL\Checkbox\CheckboxDomainObject;
use JTL\Checkbox\CheckboxFunction\CheckboxFunctionService;
use JTL\Checkbox\CheckboxLanguage\CheckboxLanguageDomainObject;
use JTL\Checkbox\CheckboxLanguage\CheckboxLanguageService;
use JTL\Checkbox\CheckboxService;
use JTL\Checkbox\CheckboxValidationDomainObject;
use JTL\Customer\CustomerGroup;
use JTL\DB\DbInterface;
use JTL\Extensions\Download\Download;
use JTL\Helpers\GeneralObject;
use JTL\Helpers\Request;
use JTL\Helpers\Text;
use JTL\Helpers\Typifier;
use JTL\Language\LanguageHelper;
use JTL\Link\Link;
use JTL\Mail\Mail\Mail;
use JTL\Optin\Optin;
use JTL\Optin\OptinNewsletter;
use JTL\Optin\OptinRefData;
use JTL\Session\Frontend;
use Psr\Log\LoggerInterface;
use stdClass;

/**
 * Class CheckBox
 * @package JTL
 */
class CheckBox
{
    public const CHECKBOX_DOWNLOAD_ORDER_COMPLETE = 'RightOfWithdrawalOfDownloadItems';

    public int $kCheckBox = 0;

    public int $kLink = 0;

    public int $kCheckBoxFunktion = 0;

    public string $cName = '';

    public string $cKundengruppe = '';

    public string $cAnzeigeOrt = '';

    public int $nAktiv = 0;

    public int $nPflicht = 0;

    public int $nLogging = 0;

    public int $nSort = 0;

    public string $dErstellt = '';

    public string $dErstellt_DE = '';

    /**
     * @var array<int, array<mixed>>|stdClass[]
     */
    public array $oCheckBoxSprache_arr = [];

    public ?stdClass $oCheckBoxFunktion = null;

    /**
     * @var int[]
     */
    public array $kKundengruppe_arr = [];

    /**
     * @var int[]
     */
    public array $kAnzeigeOrt_arr = [];

    public ?string $cID = null;

    public ?string $cLink = null;

    public ?Link $oLink = null;

    public string $identifier;

    public ?bool $isActive = null;

    public ?string $cLinkURL = null;

    public ?string $cLinkURLFull = null;

    public ?string $cBeschreibung = null;

    public ?string $cErrormsg = null;

    public int $nInternal = 0;

    protected DbInterface $db;

    protected ?LoggerInterface $logService;

    private JTLCacheInterface $cache;

    public function __construct(
        int $id = 0,
        ?DbInterface $db = null,
        protected CheckboxService $service = new CheckboxService(),
        protected CheckboxLanguageService $languageService = new CheckboxLanguageService(),
        protected CheckboxFunctionService $functionService = new CheckboxFunctionService(),
        ?LoggerInterface $logService = null,
        ?JTLCacheInterface $cache = null
    ) {
        $this->db    = $db ?? Shop::Container()->getDB();
        $this->cache = $cache ?? Shop::Container()->getCache();
        try {
            $this->logService = $logService ?? Shop::Container()->getLogService();
        } catch (Exception) {
            $this->logService = null;
        }
        $this->oLink = new Link($this->db);
        $this->loadFromDB($id);
    }

    private function loadFromDB(int $id): self
    {
        if ($id <= 0) {
            return $this;
        }
        $cacheID = 'chkbx_' . $id;
        if (($checkbox = $this->cache->get($cacheID)) !== false) {
            foreach (\array_keys(\get_object_vars($checkbox)) as $member) {
                if ($member === 'db') {
                    continue;
                }
                $this->$member = $checkbox->$member;
            }
            $this->loadLink();
            $this->checkAndUpdateFunctionIfNecessary($checkbox);

            return $this;
        }
        $checkbox = $this->service->get($id);

        if ($checkbox === null) {
            return $this;
        }
        if ($checkbox->getCheckBoxFunction() !== null) {
            $this->oCheckBoxFunktion = $checkbox->getCheckBoxFunction()->toObject();
        }
        $this->fillProperties($checkbox);
        $this->saveToCache($cacheID);

        return $this;
    }

    private function saveToCache(string $cacheID): void
    {
        $item = new stdClass();
        foreach (\get_object_vars($this) as $name => $value) {
            if (\is_object($this->$name)) {
                continue;
            }
            $item->$name = $value;
        }
        $this->cache->set($cacheID, $item, [\CACHING_GROUP_CORE, 'checkbox']);
    }

    private function loadLink(): void
    {
        $this->oLink = new Link($this->db);
        if ($this->kLink > 0) {
            try {
                $this->oLink->load($this->kLink);
            } catch (InvalidArgumentException) {
                $this->logService?->error('Checkbox cannot link to link ID {id}', ['id' => $this->kLink]);
            }
        } else {
            $this->cLink = 'kein interner Link';
        }
    }

    /**
     * @return CheckBox[]
     */
    public function getCheckBoxFrontend(
        int $location,
        int $customerGroupID = 0,
        bool $active = false,
        bool $lang = false,
        bool $special = false,
        bool $logging = false
    ): array {
        if ($customerGroupID === 0) {
            if (isset($_SESSION['Kundengruppe']->kKundengruppe)) {
                $customerGroupID = Frontend::getCustomerGroup()->getID();
            } else {
                $customerGroupID = CustomerGroup::getDefaultGroupID();
            }
        }
        $validationData = new CheckboxValidationDomainObject(
            Typifier::intify($customerGroupID),
            Typifier::intify($location),
            Typifier::boolify($active),
            Typifier::boolify($logging),
            Typifier::boolify($lang),
            Typifier::boolify($special),
            false
        );

        return $this->service->getCheckBoxValidationData($validationData);
    }

    /**
     * @param array<mixed> $post
     * @return array<string, int>
     */
    public function validateCheckBox(int $location, int $customerGroupID, array $post, bool $active = false): array
    {
        $validationData = new CheckboxValidationDomainObject(
            Typifier::intify($customerGroupID),
            Typifier::intify($location),
            Typifier::boolify($active),
            false,
            false,
            false,
            Download::hasDownloads(Frontend::getCart())
        );

        return $this->service->validateCheckBox($validationData, $post);
    }

    /**
     * @param array<mixed> $post
     * @param array<mixed> $params
     */
    public function triggerSpecialFunction(
        int $location,
        int $customerGroupID,
        bool $active,
        array $post,
        array $params = []
    ): self {
        $checkboxes = $this->getCheckBoxFrontend($location, $customerGroupID, $active, true, true);
        foreach ($checkboxes as $checkbox) {
            if ($checkbox->oCheckBoxFunktion === null || !isset($post[$checkbox->cID])) {
                continue;
            }
            if ($checkbox->oCheckBoxFunktion->pluginID > 0) {
                $params['oCheckBox'] = $checkbox;
                \executeHook(\HOOK_CHECKBOX_CLASS_TRIGGERSPECIALFUNCTION, $params);
            } else {
                // Festdefinierte Shopfunktionen
                switch ($checkbox->oCheckBoxFunktion->identifier) {
                    case 'jtl_newsletter': // Newsletteranmeldung
                        $params['oKunde'] = GeneralObject::copyMembers($params['oKunde']);
                        $this->sfCheckBoxNewsletter($params['oKunde'], $location);
                        break;

                    case 'jtl_adminmail': // CheckBoxMail
                        $params['oKunde'] = GeneralObject::copyMembers($params['oKunde']);
                        $this->sfCheckBoxMailToAdmin($params['oKunde'], $checkbox, $location);
                        break;

                    default:
                        break;
                }
            }
        }

        return $this;
    }

    /**
     * @param array<mixed> $post
     */
    public function checkLogging(int $location, int $customerGroupID, array $post, bool $active = false): self
    {
        $checkboxes = $this->getCheckBoxFrontend($location, $customerGroupID, $active, false, false, true);
        foreach ($checkboxes as $checkbox) {
            $checked          = $this->checkboxWasChecked((string)$checkbox->cID, $post);
            $log              = new stdClass();
            $log->kCheckBox   = $checkbox->kCheckBox;
            $log->kBesucher   = (int)($_SESSION['oBesucher']->kBesucher ?? '0');
            $log->kBestellung = (int)($_SESSION['kBestellung'] ?? '0');
            $log->bChecked    = (int)$checked;
            $log->dErstellt   = 'NOW()';
            $this->db->insert('tcheckboxlogging', $log);
        }

        return $this;
    }

    /**
     * @param array<mixed> $post
     */
    private function checkboxWasChecked(string $idx, array $post): bool
    {
        $value = $post[$idx] ?? null;
        if ($value === null) {
            return false;
        }
        if ($value === 'on' || $value === 'Y' || $value === 'y') {
            $value = true;
        } elseif ($value === 'N' || $value === 'n' || $value === '') {
            $value = false;
        } else {
            $value = (bool)$value;
        }

        return $value;
    }

    /**
     * @return CheckBox[]
     */
    public function getAll(string $limitSQL = '', bool $active = false): array
    {
        return $this->db->getCollection(
            'SELECT kCheckBox AS id
                FROM tcheckbox' . ($active ? ' WHERE nAktiv = 1' : '') . '
                ORDER BY nSort ' . $limitSQL
        )->map(fn(stdClass $e): self => new self((int)$e->id, $this->db))->all();
    }

    public function getTotalCount(bool $active = false): int
    {
        return $this->db->getSingleInt(
            'SELECT COUNT(*) AS cnt
                FROM tcheckbox' . ($active ? ' WHERE nAktiv = 1' : ''),
            'cnt'
        );
    }

    /**
     * @param int[] $checkboxIDs
     */
    public function activate(array $checkboxIDs): bool
    {
        $res = $this->service->activate($checkboxIDs);
        $this->cache->flushTags(['checkbox']);

        return $res;
    }

    /**
     * @param int[] $checkboxIDs
     */
    public function deactivate(array $checkboxIDs): bool
    {
        $res = $this->service->deactivate($checkboxIDs);
        $this->cache->flushTags(['checkbox']);

        return $res;
    }

    /**
     * @param int[] $checkboxIDs
     */
    public function delete(array $checkboxIDs): bool
    {
        $res = $this->service->deleteByIDs($checkboxIDs);
        $this->cache->flushTags(['checkbox']);

        return $res;
    }

    /**
     * @return stdClass[]
     */
    public function getCheckBoxFunctions(): array
    {
        return $this->db->getCollection(
            'SELECT *
                FROM tcheckboxfunktion
                ORDER BY cName'
        )->each(static function (stdClass $e): void {
            $e->kCheckBoxFunktion = (int)$e->kCheckBoxFunktion;
            $e->cName             = \__($e->cName);
        })->all();
    }

    /**
     * @param array<mixed> $post
     * @param array<mixed> $languages
     */
    public function getCheckboxDomainObject(array $post, array $languages): CheckboxDomainObject
    {
        return $this->service->getCheckBoxDomainObject($post, $languages);
    }

    public function save(CheckboxDomainObject $checkboxDO): self
    {
        $this->populateSelf($checkboxDO);
        if (\count($checkboxDO->getLanguages()) === 0) {
            return $this;
        }
        $this->insertDB(null, null, $checkboxDO);
        // we don't know, when "now()" took place, therefore we have to look up, what actually has been saved
        $savedCheckbox = $this->service->get($this->kCheckBox);
        if ($savedCheckbox instanceof CheckboxDomainObject) {
            $this->fillProperties($savedCheckbox);
            $this->cache->flushTags(['checkbox']);
            $this->saveToCache('chkbx_' . $this->kCheckBox);
        }

        return $this;
    }

    private function populateSelf(CheckboxDomainObject $checkboxDO): void
    {
        foreach ($checkboxDO->toArrayMapped() as $property => $value) {
            if (\array_key_exists($property, \get_object_vars($this))) {
                $this->$property = \is_bool($value) ? (int)$value : $value;
            }
        }
        foreach ($checkboxDO->getLanguages() as $iso => $texts) {
            $langID = $this->getSprachKeyByISO($iso);

            $this->oCheckBoxSprache_arr[$langID] = [
                'kCheckBox'     => $checkboxDO->getID(),
                'kSprache'      => $langID,
                'cText'         => $texts['text'],
                'cBeschreibung' => $texts['descr'],
            ];
        }
    }

    /**
     * @param array<mixed>|null         $texts
     * @param array<mixed>|null         $descriptions
     * @param CheckboxDomainObject|null $checkboxDO
     * @return $this
     */
    public function insertDB(
        ?array $texts = [],
        ?array $descriptions = [],
        ?CheckboxDomainObject $checkboxDO = null
    ): self {
        if (!isset($checkboxDO)) {
            $languages  = $this->prepareLanguagesForDO($texts, $descriptions);
            $checkboxDO = $this->getCheckboxDomainObject((array)$this, $languages);
        }
        $checkboxID = $checkboxDO->getCheckboxID();
        // Since method used to do the update too
        if ($checkboxID > 0) {
            $this->kCheckBox = $checkboxID;
            $this->updateDB($checkboxDO);

            return $this;
        }
        $checkboxID                 = $this->service->insert($checkboxDO);
        $this->kCheckBox            = $checkboxID;
        $this->oCheckBoxSprache_arr = $this->addLocalization($checkboxDO, $checkboxID);

        return $this;
    }

    public function updateDB(CheckboxDomainObject $checkboxDO): self
    {
        $this->service->update($checkboxDO);
        $this->oCheckBoxSprache_arr = $this->updateLocalization($checkboxDO);

        return $this;
    }

    /**
     * @return array<int, stdClass>
     */
    private function addLocalization(CheckboxDomainObject $checkboxDO, int $checkboxID = 0): array
    {
        $checkBoxLanguageArr = [];
        foreach ($checkboxDO->getLanguages() as $iso => $texts) {
            $checkboxLanguageDO = $this->prepareLocalizationObject($checkboxID, $iso, $texts);
            $this->languageService->update($checkboxLanguageDO);
            $checkBoxLanguageArr[$checkboxLanguageDO->getLanguageID()] = $checkboxLanguageDO->toObjectMapped();
        }

        return $checkBoxLanguageArr;
    }

    /**
     * @return array<int, stdClass>
     */
    private function updateLocalization(CheckboxDomainObject $checkboxDO): array
    {
        $this->dismissObsoleteLanguages($checkboxDO->getCheckboxID());

        $checkBoxLanguageArr = [];
        foreach ($checkboxDO->getLanguages() as $iso => $texts) {
            $checkboxLanguageDO = $this->prepareLocalizationObject($checkboxDO->getCheckboxID(), $iso, $texts);
            $this->languageService->update($checkboxLanguageDO);
            $checkBoxLanguageArr[$checkboxLanguageDO->getLanguageID()] = $checkboxLanguageDO->toObjectMapped();
        }

        return $checkBoxLanguageArr;
    }

    /**
     * @param array<string, string> $texts
     */
    private function prepareLocalizationObject(
        int $checkBoxID,
        string $iso,
        array $texts = []
    ): CheckboxLanguageDomainObject {
        return new CheckboxLanguageDomainObject(
            Typifier::intify($checkBoxID),
            0,
            $this->getSprachKeyByISO($iso),
            $iso,
            (string)Typifier::stringify($texts['text'] ?? ''),
            (string)Typifier::stringify($texts['descr'] ?? ''),
        );
    }

    private function getSprachKeyByISO(string $iso): int
    {
        return (int)(LanguageHelper::getLangIDFromIso($iso)->kSprache ?? 0);
    }

    private function sfCheckBoxNewsletter(mixed $customer, int $location): bool
    {
        if (!\is_object($customer)) {
            return false;
        }
        $refData = (new OptinRefData())
            ->setSalutation($customer->cAnrede ?? '')
            ->setFirstName($customer->cVorname ?? '')
            ->setLastName($customer->cNachname ?? '')
            ->setEmail($customer->cMail)
            ->setLanguageID(Shop::getLanguageID())
            ->setRealIP(Request::getRealIP());
        try {
            (new Optin(OptinNewsletter::class))
                ->getOptinInstance()
                ->createOptin($refData, $location)
                ->sendActivationMail();
        } catch (Exception) {
            $this->logService?->error('Checkbox cannot link to link ID {id}', ['id' => $this->kLink]);

            return false;
        }

        return true;
    }

    public function sfCheckBoxMailToAdmin(object $customer, CheckBox $checkBox, int $location): bool
    {
        if (!isset($customer->cVorname, $customer->cNachname, $customer->cMail)) {
            return false;
        }
        $conf = Shop::getSettingSection(\CONF_EMAILS);
        if (!empty($conf['email_master_absender'])) {
            $data                = new stdClass();
            $data->oCheckBox     = $checkBox;
            $data->oKunde        = $customer;
            $data->tkunde        = $customer;
            $data->cAnzeigeOrt   = $this->mappeCheckBoxOrte($location);
            $data->mail          = new stdClass();
            $data->mail->toEmail = $conf['email_master_absender'];
            $data->mail->toName  = $conf['email_master_absender_name']
                ?? $conf['email_master_absender'];

            $mailer = Shop::Container()->getMailer();
            $mail   = new Mail();

            return $mailer->send($mail->createFromTemplateID(\MAILTEMPLATE_CHECKBOX_SHOPBETREIBER, $data));
        }

        return false;
    }

    public function mappeCheckBoxOrte(int $location): string
    {
        return self::gibCheckBoxAnzeigeOrte()[$location] ?? '';
    }

    /**
     * @return array<int, string>
     */
    public static function gibCheckBoxAnzeigeOrte(): array
    {
        Shop::Container()->getGetText()->loadAdminLocale('pages/checkbox');

        return [
            \CHECKBOX_ORT_REGISTRIERUNG        => \__('checkboxPositionRegistration'),
            \CHECKBOX_ORT_BESTELLABSCHLUSS     => \__('checkboxPositionOrderFinal'),
            \CHECKBOX_ORT_NEWSLETTERANMELDUNG  => \__('checkboxPositionNewsletterRegistration'),
            \CHECKBOX_ORT_KUNDENDATENEDITIEREN => \__('checkboxPositionEditCustomerData'),
            \CHECKBOX_ORT_KONTAKT              => \__('checkboxPositionContactForm'),
            \CHECKBOX_ORT_FRAGE_ZUM_PRODUKT    => \__('checkboxPositionProductQuestion'),
            \CHECKBOX_ORT_FRAGE_VERFUEGBARKEIT => \__('checkboxPositionAvailabilityNotification')
        ];
    }

    public function getLink(): Link
    {
        return $this->oLink ?? throw new Exception('Link not found');
    }

    public function dismissObsoleteLanguages(int $kCheckBox): void
    {
        $this->db->queryPrepared(
            'DELETE FROM tcheckboxsprache 
                WHERE kSprache NOT IN (SELECT kSprache FROM tsprache) AND kCheckBox = :kCheckBox',
            ['kCheckBox' => $kCheckBox]
        );
    }

    /**
     * @param array<mixed>|null $texts
     * @param array<mixed>|null $descriptions
     * @return array<string, array{text: string, descr: string}>
     */
    protected function prepareLanguagesForDO(?array $texts, ?array $descriptions): array
    {
        $languages = [];
        foreach ($texts ?? [] as $iso => $language) {
            $languages[$iso] = [
                'text'  => $language,
                'descr' => $descriptions[$iso] ?? ''
            ];
        }

        return $languages;
    }

    public function fillProperties(CheckboxDomainObject $checkbox): void
    {
        $this->kCheckBox         = $checkbox->getCheckboxID();
        $this->kLink             = $checkbox->getLinkID();
        $this->kCheckBoxFunktion = $checkbox->getCheckboxFunctionID();
        $this->cName             = $checkbox->getName();
        $this->cKundengruppe     = $checkbox->getCustomerGroupsSelected();
        $this->cAnzeigeOrt       = $checkbox->getDisplayAt();
        $this->nAktiv            = (int)$checkbox->isActive();
        $this->nPflicht          = (int)$checkbox->isMandatory();
        $this->nLogging          = (int)$checkbox->isLogging();
        $this->nSort             = $checkbox->getSort();
        $this->cID               = 'CheckBox_' . $this->kCheckBox;
        $this->dErstellt         = $checkbox->getCreated();
        $this->dErstellt_DE      = $checkbox->getCreatedDE();
        $this->kKundengruppe_arr = Text::parseSSKint($checkbox->getCustomerGroupsSelected());
        $this->kAnzeigeOrt_arr   = Text::parseSSKint($checkbox->getDisplayAt());
        $this->nInternal         = (int)$checkbox->getInternal();

        $this->loadLink();
        if ($this->oCheckBoxSprache_arr === []) {
            $localized = $this->languageService->getList(['kCheckBox' => $this->kCheckBox]);
            foreach ($localized as $translation) {
                $this->oCheckBoxSprache_arr[$translation->getLanguageID()] = $translation->toObjectMapped(true);
            }
        }
    }

    public function checkAndUpdateFunctionIfNecessary(CheckboxDomainObject|stdClass $checkbox): void
    {
        $functionData = $this->functionService->get($this->kCheckBoxFunktion);
        if ($functionData !== null) {
            $this->oCheckBoxFunktion = $this->service->prepareCheckboxFunctionDomainObject($functionData)->toObject();
        }
    }
}
