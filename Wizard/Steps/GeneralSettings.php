<?php

declare(strict_types=1);

namespace JTL\Backend\Wizard\Steps;

use JTL\Backend\Wizard\Question;
use JTL\Backend\Wizard\QuestionInterface;
use JTL\Backend\Wizard\QuestionType;
use JTL\Backend\Wizard\QuestionValidation;
use JTL\Backend\Wizard\SelectOption;
use JTL\DB\DbInterface;
use JTL\Services\JTL\AlertServiceInterface;
use JTL\Settings\Option\Checkout;
use JTL\Settings\Option\Customer;
use JTL\Settings\Option\Globals;
use JTL\Settings\Settings;

/**
 * Class GeneralSettings
 * @package JTL\Backend\Wizard\Steps
 */
final class GeneralSettings extends AbstractStep
{
    public function __construct(DbInterface $db, AlertServiceInterface $alertService)
    {
        parent::__construct($db, $alertService);
        $this->setTitle(\__('stepOne'));
        $this->setDescription(\__('stepOneDesc'));
        $this->setID(1);

        $question = new Question($db);
        $question->setID(1);
        $question->setSubheading(\__('shopSettings'));
        $question->setText(\__('shopName'));
        $question->setDescription(\__('shopNameDesc'));
        $question->setValue(Settings::stringValue(Globals::SHOP_NAME));
        $question->setType(QuestionType::TEXT);
        $question->setOnSave(function (QuestionInterface $question): void {
            $question->updateConfig('global_shopname', $question->getValue());
        });
        $this->addQuestion($question);

        $question = new Question($db);
        $question->setID(2);
        $question->setIsRequired(false);
        $question->setValue(true);
        $question->setLabel(\__('secureDefaultSettings'));
        $question->setDescription(\__('secureDefaultSettingsDesc'));
        $question->setSummaryText(\__('secureDefaultSettings'));
        $question->setType(QuestionType::BOOL);
        $question->setOnSave(function (QuestionInterface $question): void {
            if (
                $question->getValue() === true
                && ((isset($_SERVER['HTTPS']) && \strtolower($_SERVER['HTTPS']) === 'on')
                    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'))
            ) {
                $question->updateConfig('kaufabwicklung_ssl_nutzen', 'P');
                $question->updateConfig('email_smtp_verschluesselung', 'tls');
                $question->updateConfig('email_methode', 'smtp');
                $question->updateConfig('global_cookie_secure', 'Y');
                $question->updateConfig('global_cookie_httponly', 'Y');
            } else {
                $question->updateConfig('kaufabwicklung_ssl_nutzen', 'N');
                $question->updateConfig('email_smtp_verschluesselung', '');
//                $question->updateConfig('email_methode', 'mail');
                $question->updateConfig('global_cookie_secure', 'S');
                $question->updateConfig('global_cookie_httponly', 'S');
            }
        });
        $question->setValidation(function (QuestionInterface $question): int {
            $questionValidation = new QuestionValidation($question);
            $questionValidation->checkSSL();

            return $questionValidation->getValidationError();
        });
        $this->addQuestion($question);

        $question = new Question($db);
        $question->setSubheading(\__('vatSettings'));
        $question->setID(3);
        $question->setText(\__('vatIDCompany'));
        $question->setDescription(\__('vatIDCompanyTitle'));
        $question->setIsRequired(false);
        $question->setValue(Settings::stringValue(Customer::USTID));
        $question->setType(QuestionType::TEXT);
        $question->setOnSave(function (QuestionInterface $question): void {
            $question->updateConfig('shop_ustid', $question->getValue());
        });
        $question->setValidation(function (QuestionInterface $question): int {
            $questionValidation = new QuestionValidation($question);
            $questionValidation->checkVAT();

            return $questionValidation->getValidationError();
        });
        $this->addQuestion($question);

        $question = new Question($db);
        $question->setID(4);
        $question->setText(\__('smallEntrepreneur'));
        $question->setDescription(\__('vatSmallEntrepreneurTitle'));
        $question->setLabel(\__('vatSmallEntrepreneur'));
        $question->setSummaryText(\__('vatSettings'));
        $question->setType(QuestionType::BOOL);
        $question->setIsRequired(false);
        $question->setValue(false);
        $question->setOnSave(function (QuestionInterface $question): void {
            if ($question->getValue() === true) {
                $question->updateConfig('global_ust_auszeichnung', 'endpreis');
                $question->updateConfig('global_steuerpos_anzeigen', 'N');
                $question->setLocalization(
                    'ger',
                    'global',
                    'footnoteExclusiveVat',
                    'Gemäß §19 UStG wird keine Umsatzsteuer berechnet'
                );
                $question->setLocalization(
                    'eng',
                    'global',
                    'footnoteExclusiveVat',
                    'According to the § 19 UStG we do not charge the german sales tax, ' .
                    'and consequently do not account it (small business)'
                );
            } else {
                $question->updateConfig('global_ust_auszeichnung', 'auto');
                $question->updateConfig('global_steuerpos_anzeigen', 'Y');
                $question->setLocalization(
                    'ger',
                    'global',
                    'footnoteExclusiveVat',
                    'Alle Preise zzgl. gesetzlicher USt.'
                );
                $question->setLocalization(
                    'eng',
                    'global',
                    'footnoteExclusiveVat',
                    'All prices exclusive legal <abbr title="value added tax">VAT</abbr>'
                );
            }
        });
        $this->addQuestion($question);

        $question = new Question($db);
        $question->setID(5);
        $question->setText(\__('customerGroupDesc'));
        $question->setDescription(\__('customerGroupDescTitle'));
        $question->setSummaryText(\__('customerGroup'));
        $question->setType(QuestionType::MULTI_BOOL);
        $question->setIsFullWidth(true);
        $question->setIsRequired(false);
        $question->setValue(false);
        $option = new SelectOption();
        $option->setName(\__('customerGroupB2B'));
        $option->setValue('b2b');
        $question->addOption($option);
        $option = new SelectOption();
        $option->setName(\__('customerGroupB2C'));
        $option->setValue('b2c');
        $question->addOption($option);
        $question->setOnSave(function (QuestionInterface $question): void {
            $value = $question->getValue();
            $b2b   = $value === 'b2b' || (\is_array($value) && \in_array('b2b', $value, true));
            $b2c   = $value === 'b2c' || (\is_array($value) && \in_array('b2c', $value, true));
            if ($b2b === true && $b2c === true) {
                $question->updateConfig('kundenregistrierung_abfragen_firma', 'O');
                $question->updateConfig('kundenregistrierung_abfragen_ustid', 'O');
            } elseif ($b2b === true) {
                $question->updateConfig('kundenregistrierung_abfragen_firma', 'Y');
                $question->updateConfig('kundenregistrierung_abfragen_ustid', 'Y');
                $question->updateConfig('bestellvorgang_wrb_anzeigen', 0);
            } elseif ($b2c === true) {
                $question->updateConfig('kundenregistrierung_abfragen_firma', 'N');
                $question->updateConfig('kundenregistrierung_abfragen_ustid', 'N');
            }
        });
        $this->addQuestion($question);

        $question = new Question($db);
        $question->setID(6);
        $question->setSubheading(\__('orderNumberSettings'));
        $question->setText(\__('bestellabschluss_bestellnummer_praefix_name'));
        $question->setDescription(\__('bestellabschluss_bestellnummer_praefix_desc'));
        $question->setType(QuestionType::TEXT);
        $question->setIsRequired(false);
        $question->setValue(Settings::stringValue(Checkout::ORDER_ID_PREFIX));
        $question->setOnSave(function (QuestionInterface $question): void {
            $question->updateConfig('bestellabschluss_bestellnummer_praefix', $question->getValue());
        });
        $this->addQuestion($question);

        $question = new Question($db);
        $question->setID(7);
        $question->setText(\__('bestellabschluss_bestellnummer_suffix_name'));
        $question->setDescription(\__('bestellabschluss_bestellnummer_suffix_desc'));
        $question->setType(QuestionType::TEXT);
        $question->setIsRequired(false);
        $question->setValue(Settings::stringValue(Checkout::ORDER_ID_SUFFIX));
        $question->setOnSave(function (QuestionInterface $question): void {
            $question->updateConfig('bestellabschluss_bestellnummer_suffix', $question->getValue());
        });

        $this->addQuestion($question);
        $question = new Question($db);
        $question->setID(8);
        $question->setText(\__('bestellabschluss_bestellnummer_anfangsnummer_name'));
        $question->setDescription(\__('bestellabschluss_bestellnummer_anfangsnummer_desc'));
        $question->setType(QuestionType::NUMBER);
        $question->setIsRequired(false);
        $question->setValue(Settings::intValue(Checkout::ORDER_ID_START));
        $question->setOnSave(function (QuestionInterface $question): void {
            $question->updateConfig('bestellabschluss_bestellnummer_anfangsnummer', $question->getValue());
        });
        $this->addQuestion($question);
    }
}
