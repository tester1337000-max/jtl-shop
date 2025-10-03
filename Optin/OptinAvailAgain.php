<?php

declare(strict_types=1);

namespace JTL\Optin;

use JTL\Campaign;
use JTL\Catalog\Product\Artikel;
use JTL\Helpers\Request;
use JTL\Mail\Mail\Mail;
use JTL\Session\Frontend;
use JTL\Shop;
use stdClass;

/**
 * Class OptinAvailAgain
 * @package JTL\Optin
 */
class OptinAvailAgain extends OptinBase implements OptinInterface
{
    private Artikel $product;

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
    }

    /**
     * @inheritdoc
     */
    public function createOptin(OptinRefData $refData, int $location = 0): OptinInterface
    {
        $this->refData                       = $refData;
        $options                             = Artikel::getDefaultOptions();
        $options->nKeineSichtbarkeitBeachten = 1;
        $this->product                       = new Artikel($this->dbHandler);
        $this->product->fuelleArtikel($this->refData->getProductId(), $options);
        $this->saveOptin($this->generateUniqOptinCode());

        return $this;
    }

    /**
     * send the optin activation mail
     */
    public function sendActivationMail(): void
    {
        if ($this->refData === null) {
            return;
        }
        $customerId              = Frontend::getCustomer()->getID();
        $recipient               = new stdClass();
        $recipient->kSprache     = Shop::getLanguageID();
        $recipient->kKunde       = $customerId;
        $recipient->nAktiv       = $customerId > 0;
        $recipient->cAnrede      = $this->refData->getSalutation();
        $recipient->cVorname     = $this->refData->getFirstName();
        $recipient->cNachname    = $this->refData->getLastName();
        $recipient->cEmail       = $this->refData->getEmail();
        $recipient->dEingetragen = $this->nowDataTime->format('Y-m-d H:i:s');

        $optin                  = new stdClass();
        $productURL             = Shop::getURL() . '/' . $this->product->cSeo;
        $optinCodePrefix        = '?' . \QUERY_PARAM_OPTIN_CODE . '=';
        $optin->activationURL   = $productURL . $optinCodePrefix . self::ACTIVATE_CODE . $this->optCode;
        $optin->deactivationURL = $productURL . $optinCodePrefix . self::DELETE_CODE . $this->optCode;

        $templateData                                   = new stdClass();
        $templateData->tkunde                           = $_SESSION['Kunde'] ?? null;
        $templateData->tartikel                         = $this->product;
        $templateData->tverfuegbarkeitsbenachrichtigung = [];
        $templateData->optin                            = $optin;
        $templateData->mailReceiver                     = $recipient;

        $mailer = Shop::Container()->getMailer();
        $mail   = new Mail();
        $mailer->send($mail->createFromTemplateID(\MAILTEMPLATE_PRODUKT_WIEDER_VERFUEGBAR_OPTIN, $templateData));

        Shop::Container()->getAlertService()->addInfo(
            Shop::Lang()->get('availAgainOptinCreated', 'messages'),
            'availAgainOptinCreated'
        );
    }

    /**
     * @throws \Exception
     */
    public function activateOptin(): void
    {
        if ($this->refData === null) {
            return;
        }
        $data                  = new stdClass();
        $data->kSprache        = $this->refData->getLanguageID();
        $data->cIP             = Request::getRealIP();
        $data->dErstellt       = 'NOW()';
        $data->nStatus         = 0;
        $data->kArtikel        = $this->refData->getProductId();
        $data->cMail           = $this->refData->getEmail();
        $data->cVorname        = $this->refData->getFirstName();
        $data->cNachname       = $this->refData->getLastName();
        $data->customerGroupID = $this->refData->getCustomerGroupID();

        \executeHook(\HOOK_ARTIKEL_INC_BENACHRICHTIGUNG, ['Benachrichtigung' => $data]);

        $inquiryID = $this->dbHandler->getLastInsertedID(
            'INSERT INTO tverfuegbarkeitsbenachrichtigung
                (cVorname, cNachname, cMail, kSprache, kArtikel, cIP, dErstellt, nStatus, customerGroupID)
                VALUES
                (:cVorname, :cNachname, :cMail, :kSprache, :kArtikel, :cIP, NOW(), :nStatus, :customerGroupID)
                ON DUPLICATE KEY UPDATE
                    cVorname = :cVorname, cNachname = :cNachname, ksprache = :kSprache,
                    cIP = :cIP, dErstellt = NOW(), nStatus = :nStatus, customerGroupID = :customerGroupID',
            \get_object_vars($data)
        );
        Campaign::setCampaignAction(\KAMPAGNE_DEF_VERFUEGBARKEITSANFRAGE, $inquiryID, 1.0);
    }

    /**
     * do opt-in specific de-activations
     */
    public function deactivateOptin(): void
    {
        if ($this->refData !== null) {
            $this->dbHandler->delete('tverfuegbarkeitsbenachrichtigung', 'cMail', $this->refData->getEmail());
        }
    }

    /**
     * @return Artikel
     */
    public function getProduct(): Artikel
    {
        return $this->product;
    }

    /**
     * @param Artikel $product
     * @return OptinAvailAgain
     */
    public function setProduct(Artikel $product): self
    {
        $this->product = $product;

        return $this;
    }

    /**
     * load a optin-tupel, via email and productID
     * restore its reference data
     */
    protected function loadOptin(): void
    {
        $refData = $this->dbHandler->getObjects(
            'SELECT *
              FROM toptin
              WHERE cMail = :mail
                AND kOptinClass = :optinclass',
            [
                'mail'       => $this->emailAddress,
                'optinclass' => \get_class($this)
            ]
        );
        foreach ($refData as $optin) {
            /** @var OptinRefData $refData */
            $refData = \unserialize($optin->cRefData, [OptinRefData::class]);
            if ($refData->getProductId() === $this->getProduct()->kArtikel) {
                $this->foundOptinTupel = $optin;
            }
        }
    }
}
