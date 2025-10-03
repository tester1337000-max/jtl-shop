<?php

declare(strict_types=1);

namespace JTL\Mail\Template;

use JTL\DB\DbInterface;

/**
 * Class TemplateFactory
 * @package JTL\Mail\Template
 */
final readonly class TemplateFactory
{
    public function __construct(private DbInterface $db)
    {
    }

    public function getTemplateByID(int $id): ?TemplateInterface
    {
        $data = $this->db->select('temailvorlage', 'kEmailvorlage', $id);
        if ($data === null) {
            return null;
        }

        return $data->kPlugin > 0
            ? $this->getTemplate('kPlugin_' . $data->kPlugin . '_' . $data->cModulId)
            : $this->getTemplate($data->cModulId);
    }

    public function getTemplate(string $templateID): ?TemplateInterface
    {
        switch ($templateID) {
            case \MAILTEMPLATE_GUTSCHEIN:
                return new BalanceChanged($this->db);
            case \MAILTEMPLATE_CHECKBOX_SHOPBETREIBER:
                return new Checkbox($this->db);
            case \MAILTEMPLATE_KONTAKTFORMULAR:
                return new ContactFormSent($this->db);
            case \MAILTEMPLATE_KUNDENACCOUNT_GELOESCHT:
                return new CustomerAccountDeleted($this->db);
            case \MAILTEMPLATE_KUNDENGRUPPE_ZUWEISEN:
                return new CustomerGroupAssigned($this->db);
            case \MAILTEMPLATE_ADMINLOGIN_PASSWORT_VERGESSEN:
                return new ForgotAdminPassword($this->db);
            case \MAILTEMPLATE_PASSWORT_VERGESSEN:
                return new ForgotPassword($this->db);
            case \MAILTEMPLATE_ACCOUNTERSTELLUNG_DURCH_BETREIBER:
                return new NewAccountCreatedByAdmin($this->db);
            case \MAILTEMPLATE_KUPON:
                return new NewCoupon($this->db);
            case \MAILTEMPLATE_NEUKUNDENREGISTRIERUNG:
                return new NewCustomerRegistration($this->db);
            case \MAILTEMPLATE_NEWSLETTERANMELDEN:
                return new NewsletterRegistration($this->db);
            case \MAILTEMPLATE_BESTELLUNG_STORNO:
                return new OrderCanceled($this->db);
            case \MAILTEMPLATE_BESTELLUNG_BEZAHLT:
                return new OrderCleared($this->db);
            case \MAILTEMPLATE_BESTELLBESTAETIGUNG:
                return new OrderConfirmation($this->db);
            case \MAILTEMPLATE_BESTELLUNG_TEILVERSANDT:
                return new OrderPartiallyShipped($this->db);
            case \MAILTEMPLATE_BESTELLUNG_RESTORNO:
                return new OrderReactivated($this->db);
            case \MAILTEMPLATE_BESTELLUNG_VERSANDT:
                return new OrderShipped($this->db);
            case \MAILTEMPLATE_BESTELLUNG_AKTUALISIERT:
                return new OrderUpdated($this->db);
            case \MAILTEMPLATE_PRODUKT_WIEDER_VERFUEGBAR:
                return new ProductAvailable($this->db);
            case \MAILTEMPLATE_PRODUKT_WIEDER_VERFUEGBAR_OPTIN:
                return new ProductAvailableOptin($this->db);
            case \MAILTEMPLATE_PRODUKTANFRAGE:
                return new ProductInquiry($this->db);
            case \MAILTEMPLATE_BEWERTUNG_GUTHABEN:
                return new RatingBonus($this->db);
            case \MAILTEMPLATE_BEWERTUNGERINNERUNG:
                return new RatingReminder($this->db);
            case \MAILTEMPLATE_STATUSEMAIL:
                return new StatusMail($this->db);
            case \MAILTEMPLATE_WUNSCHLISTE:
                return new Wishlist($this->db);
            case \MAILTEMPLATE_HEADER:
                return new Header($this->db);
            case \MAILTEMPLATE_FOOTER:
                return new Footer($this->db);
            case \MAILTEMPLATE_AKZ:
                return new AKZ($this->db);
            case \str_contains($templateID, 'kPlugin'):
                $tpl = new Plugin($this->db);
                $tpl->setID($templateID);

                return $tpl;
            default:
                return null;
        }
    }
}
