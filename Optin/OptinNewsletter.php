<?php

declare(strict_types=1);

namespace JTL\Optin;

use JTL\CheckBox;
use JTL\Exceptions\InvalidInputException;
use JTL\Helpers\Form;
use JTL\Helpers\Request;
use JTL\Helpers\Text;
use JTL\Mail\Mail\Mail;
use JTL\Services\JTL\AlertServiceInterface;
use JTL\Session\Frontend;
use JTL\Shop;
use JTL\SimpleMail;
use stdClass;

/**
 * Class OptinNewsletter
 * @package JTL\Optin
 */
class OptinNewsletter extends OptinBase implements OptinInterface
{
    private bool $hasSendingPermission = false;

    private ?int $historyID = null;

    private AlertServiceInterface $alertHelper;

    /**
     * @var array<string, array<mixed>>
     */
    private array $conf;

    /**
     * @param array<mixed> $inheritData
     */
    public function __construct(array $inheritData)
    {
        [
            $this->dbHandler,
            $this->nowDataTime,
            $this->refData,
            $this->emailAddress,
            $this->optCode,
            $this->actionPrefix
        ] = $inheritData;

        $this->alertHelper = Shop::Container()->getAlertService();
        $this->conf        = Shop::getSettings([\CONF_NEWSLETTER]);
    }

    /**
     * @former newsletterAnmeldungPlausi()
     * @return array<string, int>
     */
    protected function checkCaptcha(int $location = \CHECKBOX_ORT_NEWSLETTERANMELDUNG): array
    {
        $res = [];
        if (
            $location === \CHECKBOX_ORT_NEWSLETTERANMELDUNG
            && Shop::getSettingValue(\CONF_NEWSLETTER, 'newsletter_sicherheitscode') !== 'N'
            && !Form::validateCaptcha($_POST)
        ) {
            $res['captcha'] = 2;
        }

        return $res;
    }

    /**
     * @inheritdoc
     * @throws InvalidInputException
     */
    public function createOptin(
        OptinRefData $refData,
        int $location = \CHECKBOX_ORT_NEWSLETTERANMELDUNG
    ): OptinInterface {
        $this->refData = $refData;
        $this->optCode = $this->generateUniqOptinCode();
        if (!SimpleMail::checkBlacklist($this->refData->getEmail())) {
            $checks              = new stdClass();
            $checks->nPlausi_arr = [];
            if (Text::filterEmailAddress($this->refData->getEmail()) !== false) {
                $checks->nPlausi_arr            = $this->checkCaptcha($location);
                $kKundengruppe                  = Frontend::getCustomerGroup()->getID();
                $checkBox                       = new CheckBox();
                $checks->nPlausi_arr            = \array_merge(
                    $checks->nPlausi_arr,
                    $checkBox->validateCheckBox($location, $kKundengruppe, $_POST, true)
                );
                $checks->cPost_arr['cAnrede']   = Text::filterXSS($this->refData->getSalutation());
                $checks->cPost_arr['cVorname']  = Text::filterXSS($this->refData->getFirstName());
                $checks->cPost_arr['cNachname'] = Text::filterXSS($this->refData->getLastName());
                $checks->cPost_arr['cEmail']    = Text::filterXSS($this->refData->getEmail());
                $checks->cPost_arr['captcha']   = isset($_POST['captcha'])
                    ? Text::htmlentities(Text::filterXSS((string)$_POST['captcha']))
                    : null;
                if (\count($checks->nPlausi_arr) === 0) {
                    $checks = $this->saveData($checkBox, $kKundengruppe, $checks);
                }
            } else {
                // "Entschuldigung, Ihre E-Mail-Adresse ist nicht im richtigen Format."
                $this->alertHelper->addError(
                    Shop::Lang()->get('newsletterWrongemail', 'errorMessages'),
                    'newsletterWrongemail'
                );
            }

            Shop::Smarty()->assign('oPlausi', $checks);
            $this->dbHandler->delete('tnewsletterempfaengerblacklist', 'cMail', $this->refData->getEmail());
        } else {
            Shop::Container()->getAlertService()->addError(
                Text::filterEmailAddress($_POST['cEmail']) !== false
                    ? (Shop::Lang()->get('kwkEmailblocked', 'errorMessages') . '<br />')
                    : (Shop::Lang()->get('invalidEmail') . '<br />'),
                'newsletterBlockedInvalid'
            );

            throw new InvalidInputException('invalid email: ', $this->refData->getEmail());
        }

        if ($this->hasSendingPermission === true) {
            $this->saveOptin($this->optCode);
        }

        return $this;
    }

    /**
     * @throws InvalidInputException
     */
    public function sendActivationMail(): void
    {
        if ($this->hasSendingPermission !== true || $this->refData === null) {
            return;
        }
        if (!Text::filterEmailAddress($this->refData->getEmail()) !== false) {
            throw new InvalidInputException(
                Shop::Lang()->get('newsletterWrongemail', 'errorMessages'),
                $this->refData->getEmail()
            );
        }
        $shopURL                       = Shop::getURL();
        $optinCodePrefix               = '/?' . \QUERY_PARAM_OPTIN_CODE . '=';
        $recipient                     = new stdClass();
        $recipient->kSprache           = Shop::getLanguageID();
        $recipient->kKunde             = (int)($_SESSION['Kunde']->kKunde ?? '0');
        $recipient->nAktiv             = $recipient->kKunde > 0;
        $recipient->cAnrede            = $this->refData->getSalutation();
        $recipient->cVorname           = $this->refData->getFirstName();
        $recipient->cNachname          = $this->refData->getLastName();
        $recipient->cEmail             = $this->refData->getEmail();
        $recipient->cLoeschURL         = $shopURL . $optinCodePrefix . self::DELETE_CODE . $this->optCode;
        $recipient->cFreischaltURL     = $shopURL . $optinCodePrefix . self::ACTIVATE_CODE . $this->optCode;
        $recipient->dLetzterNewsletter = '_DBNULL_';
        $recipient->dEingetragen       = $this->nowDataTime->format('Y-m-d H:i:s');

        $templateData                       = new stdClass();
        $templateData->tkunde               = $_SESSION['Kunde'] ?? null;
        $templateData->NewsletterEmpfaenger = $recipient;

        $mailer = Shop::Container()->getMailer();
        $mail   = new Mail();
        $mailer->send($mail->createFromTemplateID(\MAILTEMPLATE_NEWSLETTERANMELDEN, $templateData));

        $this->dbHandler->update(
            'tnewsletterempfaengerhistory',
            'kNewsletterEmpfaengerHistory',
            $this->historyID ?? 0,
            (object)['cEmailBodyHtml' => $mail->getBodyHTML()]
        );
        $this->alertHelper->addNotice(Shop::Lang()->get('newsletterAdd', 'messages'), 'newsletterAdd');
    }

    /**
     * @throws \Exception
     */
    public function activateOptin(): void
    {
        parent::activateOptin();

        $optinCode  = self::ACTIVATE_CODE . $this->optCode;
        $recicpient = $this->dbHandler->select('tnewsletterempfaenger', 'cOptCode', $optinCode);
        if (!isset($recicpient->kNewsletterEmpfaenger) || $recicpient->kNewsletterEmpfaenger <= 0) {
            return;
        }
        \executeHook(
            \HOOK_NEWSLETTER_PAGE_EMPFAENGERFREISCHALTEN,
            ['oNewsletterEmpfaenger' => $recicpient]
        );
        $this->dbHandler->update(
            'tnewsletterempfaenger',
            'kNewsletterEmpfaenger',
            (int)$recicpient->kNewsletterEmpfaenger,
            (object)['nAktiv' => 1]
        );
        $this->dbHandler->query(
            'UPDATE tnewsletterempfaenger, tkunde
                SET tnewsletterempfaenger.kKunde = tkunde.kKunde
                WHERE tkunde.cMail = tnewsletterempfaenger.cEmail
                    AND tnewsletterempfaenger.kKunde = 0'
        );
        $upd           = new stdClass();
        $upd->dOptCode = 'NOW()';
        $upd->cOptIp   = Request::getRealIP();
        $this->dbHandler->update(
            'tnewsletterempfaengerhistory',
            ['cOptCode', 'cAktion'],
            [$optinCode, 'Eingetragen'],
            $upd
        );
    }

    /**
     * legacy de-activation
     */
    public function deactivateOptin(): void
    {
        if (!empty($this->optCode)) {
            $deleteCode = self::DELETE_CODE . $this->optCode;
            $recicpient = $this->dbHandler->select('tnewsletterempfaenger', 'cLoeschCode', $deleteCode);
            if (!empty($recicpient->cLoeschCode)) {
                \executeHook(
                    \HOOK_NEWSLETTER_PAGE_EMPFAENGERLOESCHEN,
                    ['oNewsletterEmpfaenger' => $recicpient]
                );

                $this->dbHandler->delete('tnewsletterempfaenger', 'cLoeschCode', $deleteCode);
                $hist               = new stdClass();
                $hist->kSprache     = $recicpient->kSprache;
                $hist->kKunde       = $recicpient->kKunde;
                $hist->cAnrede      = $recicpient->cAnrede;
                $hist->cVorname     = $recicpient->cVorname;
                $hist->cNachname    = $recicpient->cNachname;
                $hist->cEmail       = $recicpient->cEmail;
                $hist->cOptCode     = $recicpient->cOptCode;
                $hist->cLoeschCode  = $recicpient->cLoeschCode;
                $hist->cAktion      = 'Geloescht';
                $hist->dEingetragen = $recicpient->dEingetragen;
                $hist->dAusgetragen = 'NOW()';
                $hist->dOptCode     = '_DBNULL_';
                $hist->cRegIp       = Request::getRealIP();
                $this->dbHandler->insert('tnewsletterempfaengerhistory', $hist);

                \executeHook(
                    \HOOK_NEWSLETTER_PAGE_HISTORYEMPFAENGEREINTRAGEN,
                    ['oNewsletterEmpfaengerHistory' => $hist]
                );
                $blacklist            = new stdClass();
                $blacklist->cMail     = $recicpient->cEmail;
                $blacklist->dErstellt = 'NOW()';
                $this->dbHandler->insert('tnewsletterempfaengerblacklist', $blacklist);
            } else {
                $this->alertHelper->addError(Shop::Lang()->get('newsletterNocode', 'errorMessages'), 'nwsltrNocode');
            }
        } elseif (!empty($this->emailAddress)) {
            // de-activate by mail-address
            $recicpient = $this->dbHandler->select(
                'tnewsletterempfaenger',
                'cEmail',
                Text::htmlentities(Text::filterXSS((string)$_POST['cEmail']))
            );
            if (!empty($recicpient->kNewsletterEmpfaenger)) {
                \executeHook(
                    \HOOK_NEWSLETTER_PAGE_EMPFAENGERLOESCHEN,
                    ['oNewsletterEmpfaenger' => $recicpient]
                );
                $this->dbHandler->delete(
                    'tnewsletterempfaenger',
                    'cEmail',
                    Text::htmlentities(Text::filterXSS((string)$_POST['cEmail']))
                );
                $hist               = new stdClass();
                $hist->kSprache     = $recicpient->kSprache;
                $hist->kKunde       = $recicpient->kKunde;
                $hist->cAnrede      = $recicpient->cAnrede;
                $hist->cVorname     = $recicpient->cVorname;
                $hist->cNachname    = $recicpient->cNachname;
                $hist->cEmail       = $recicpient->cEmail;
                $hist->cOptCode     = $recicpient->cOptCode;
                $hist->cLoeschCode  = $recicpient->cLoeschCode;
                $hist->cAktion      = 'Geloescht';
                $hist->dEingetragen = $recicpient->dEingetragen;
                $hist->dAusgetragen = 'NOW()';
                $hist->dOptCode     = '_DBNULL_';
                $hist->cRegIp       = Request::getRealIP();
                $this->dbHandler->insert('tnewsletterempfaengerhistory', $hist);

                \executeHook(
                    \HOOK_NEWSLETTER_PAGE_HISTORYEMPFAENGEREINTRAGEN,
                    ['oNewsletterEmpfaengerHistory' => $hist]
                );
                $blacklist            = new stdClass();
                $blacklist->cMail     = $recicpient->cEmail;
                $blacklist->dErstellt = 'NOW()';
                $this->dbHandler->insert('tnewsletterempfaengerblacklist', $blacklist);
                // former: newsletterDelete = "Sie wurden erfolgreich aus unserem Newsletterverteiler ausgetragen."
                $this->alertHelper->addInfo(Shop::Lang()->get('optinCanceled', 'messages'), 'optinCanceled');
            } else {
                $this->alertHelper->addError(
                    Shop::Lang()->get('newsletterNoexists', 'errorMessages'),
                    'newsletterNoexists'
                );
            }
        }
    }

    /**
     * NOTE: the table `tnewsletterempfaengerhistory` has to be written before this method is called
     *
     * @param stdClass[] $optins
     * @throws \Exception
     */
    public function bulkActivateOptins(array $optins): void
    {
        $realIP     = Request::getRealIP();
        $languageID = Shop::getLanguageID();
        $groupID    = Frontend::getCustomer()->getGroupID();
        foreach ($optins as $singleOptin) {
            $this->setCode($singleOptin->cOptCode);
            $this->refData = (new OptinRefData())
                ->setSalutation($singleOptin->cAnrede)
                ->setFirstName($singleOptin->cVorname)
                ->setLastName($singleOptin->cNachname)
                ->setEmail($singleOptin->cEmail)
                ->setCustomerID($singleOptin->kKunde)
                ->setCustomerGroupID($groupID)
                ->setLanguageID($singleOptin->kSprache ?? $languageID)
                ->setRealIP($realIP);
            $this->saveOptin($this->optCode);
            $this->loadOptin();
            parent::activateOptin();
            $upd = (object)[
                'dOptCode' => 'NOW()',
                'cOptIp'   => $realIP
            ];
            $this->dbHandler->update(
                'tnewsletterempfaengerhistory',
                ['cOptCode', 'cAktion'],
                ['ac' . $this->optCode, 'Aktiviert'],
                $upd
            );
        }
    }

    /**
     * only for FILE IMPORTS of newsletter receivers without sending optin-mails!
     */
    public function bypassSendingPermission(): OptinInterface
    {
        $this->hasSendingPermission = true;

        return $this;
    }

    /**
     * @throws \Exception
     */
    public function saveData(CheckBox $checkBox, int $customerGroupID, stdClass $checks): stdClass
    {
        if ($this->refData === null) {
            return new stdClass();
        }
        $nlCustomer = null;
        $recipient  = $this->dbHandler->select(
            'tnewsletterempfaenger',
            'cEmail',
            $this->refData->getEmail()
        );
        if (!empty($recipient->dEingetragen)) {
            $recipient->Datum = (new \DateTime($recipient->dEingetragen))->format('d.m.Y H:i');
        }
        $customerID = Frontend::getCustomer()->getID();
        if ($customerID > 0) {
            $nlCustomer = $this->dbHandler->select(
                'tnewsletterempfaenger',
                'kKunde',
                $customerID
            );
        }
        if (
            (isset($recipient->cEmail) && $recipient->cEmail !== '')
            || (isset($nlCustomer->kKunde) && $nlCustomer->kKunde > 0)
        ) {
            // former: TYPE_ERROR, newsletterExists = "Fehler: Ihre E-Mail-Adresse ist bereits vorhanden."
            $this->alertHelper->addInfo(
                Shop::Lang()->get('optinSucceededMailSent', 'messages'),
                'optinSucceededMailSent'
            );

            return $checks;
        }
        $this->handleCheckbox($checkBox, $customerGroupID);
        $recipient = $this->addRecipient($customerID);
        $this->addHistory($customerID, $recipient);
        if (
            ($this->conf['newsletter']['newsletter_doubleopt'] === 'U' && empty($_SESSION['Kunde']->kKunde))
            || $this->conf['newsletter']['newsletter_doubleopt'] === 'A'
        ) {
            // opt-in mail (only for unknown users)
            $this->hasSendingPermission = true;
            $checks                     = new stdClass();
        } else {
            // do not send an opt-in mail, but activate this subscription
            $this->saveOptin($this->optCode);
            $this->activateOptin();
            // "Vielen Dank, Sie wurden in den Newsletterversand eingetragen."
            $this->alertHelper->addNotice(
                Shop::Lang()->get('newsletterNomailAdd', 'messages'),
                'newsletterNomailAdd'
            );
        }

        return $checks;
    }

    public function handleCheckbox(CheckBox $checkBox, int $customerGroupID): void
    {
        if ($this->refData === null) {
            return;
        }
        $customerData            = new stdClass();
        $customerData->cAnrede   = $this->refData->getSalutation();
        $customerData->cVorname  = $this->refData->getFirstName();
        $customerData->cNachname = $this->refData->getLastName();
        $customerData->cMail     = $this->refData->getEmail();
        $customerData->cRegIp    = $this->refData->getRealIP();
        $checkBox->triggerSpecialFunction(
            \CHECKBOX_ORT_NEWSLETTERANMELDUNG,
            $customerGroupID,
            true,
            $_POST,
            ['oKunde' => $customerData]
        );
        $checkBox->checkLogging(\CHECKBOX_ORT_NEWSLETTERANMELDUNG, $customerGroupID, $_POST, true);
    }

    public function addRecipient(int $customerID): stdClass
    {
        if ($this->refData === null) {
            return new stdClass();
        }
        $recipient                     = new stdClass();
        $recipient->kSprache           = Shop::getLanguageID();
        $recipient->kKunde             = $customerID;
        $recipient->nAktiv             = ($customerID > 0
            && $this->conf['newsletter']['newsletter_doubleopt'] === 'U') ? 1 : 0;
        $recipient->cAnrede            = $this->refData->getSalutation();
        $recipient->cVorname           = $this->refData->getFirstName();
        $recipient->cNachname          = $this->refData->getLastName();
        $recipient->cEmail             = $this->refData->getEmail();
        $recipient->cOptCode           = self::ACTIVATE_CODE . $this->optCode;
        $recipient->cLoeschCode        = self::DELETE_CODE . $this->optCode;
        $recipient->dEingetragen       = 'NOW()';
        $recipient->dLetzterNewsletter = '_DBNULL_';
        \executeHook(\HOOK_NEWSLETTER_PAGE_EMPFAENGEREINTRAGEN, [
            'oNewsletterEmpfaenger' => $recipient
        ]);
        $this->dbHandler->insert('tnewsletterempfaenger', $recipient);

        return $recipient;
    }

    public function addHistory(int $customerID, stdClass $recipient): void
    {
        if ($this->refData === null) {
            return;
        }
        $history               = new stdClass();
        $history->kSprache     = Shop::getLanguageID();
        $history->kKunde       = $customerID;
        $history->cAnrede      = $this->refData->getSalutation();
        $history->cVorname     = $this->refData->getFirstName();
        $history->cNachname    = $this->refData->getLastName();
        $history->cEmail       = $this->refData->getEmail();
        $history->cOptCode     = $recipient->cOptCode;
        $history->cLoeschCode  = $recipient->cLoeschCode;
        $history->cAktion      = 'Eingetragen';
        $history->dEingetragen = 'NOW()';
        $history->dAusgetragen = '_DBNULL_';
        $history->dOptCode     = '_DBNULL_';
        $history->cRegIp       = $this->refData->getRealIP();

        $this->historyID = $this->dbHandler->insert(
            'tnewsletterempfaengerhistory',
            $history
        );
        \executeHook(\HOOK_NEWSLETTER_PAGE_HISTORYEMPFAENGEREINTRAGEN, [
            'oNewsletterEmpfaengerHistory' => $history
        ]);
    }
}
