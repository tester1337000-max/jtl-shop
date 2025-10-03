<?php

declare(strict_types=1);

namespace JTL\Backend\Wizard\Steps;

use JTL\Backend\AdminAccount;
use JTL\Backend\Wizard\Question;
use JTL\Backend\Wizard\QuestionInterface;
use JTL\Backend\Wizard\QuestionType;
use JTL\DB\DbInterface;
use JTL\Mail\Template\TemplateFactory;
use JTL\Services\JTL\AlertServiceInterface;
use JTL\Settings\Option\Email;
use JTL\Settings\Settings;

/**
 * Class EmailSettings
 * @package JTL\Backend\Wizard\Steps
 */
final class EmailSettings extends AbstractStep
{
    public function __construct(DbInterface $db, AlertServiceInterface $alertService, AdminAccount $adminAccount)
    {
        parent::__construct($db, $alertService);
        $this->setTitle(\__('stepTwo'));
        $this->setDescription(\__('stepTwoDesc'));
        $this->setID(2);

        $question = new Question($db);
        $question->setID(11);
        $question->setText(\__('email_master_absender_name'));
        $question->setDescription(\__('email_master_absender_desc'));
        $question->setType(QuestionType::EMAIL);
        $question->setValue(Settings::stringValue(Email::MAIL_SENDER));
        $question->setOnSave(function (QuestionInterface $question): void {
            $question->updateConfig('email_master_absender', $question->getValue());
        });
        $this->addQuestion($question);

        $question = new Question($db);
        $question->setID(12);
        $question->setText(\__('email_master_absender_name_name'));
        $question->setDescription(\__('email_master_absender_name_desc'));
        $question->setType(QuestionType::TEXT);
        $question->setValue(Settings::stringValue(Email::MAIL_SENDER_NAME));
        $question->setOnSave(function (QuestionInterface $question): void {
            $question->updateConfig('email_master_absender_name', $question->getValue());
        });
        $this->addQuestion($question);

        $template = (new TemplateFactory($db))->getTemplate(\MAILTEMPLATE_BESTELLBESTAETIGUNG);
        if ($template === null) {
            throw new \InvalidArgumentException('Template "order confirmation" not found');
        }
        $template->load(1, 1);

        $question = new Question($db);
        $question->setID(13);
        $question->setText(\__('orderConfirmationBCC'));
        $question->setDescription(\__('orderConfirmationBCCDesc'));
        $question->setType(QuestionType::TEXT);
        $question->setValue(\implode(';', $template->getCopyTo()));
        $question->setIsFullWidth(true);
        $question->setIsRequired(false);
        $question->setOnSave(function (QuestionInterface $question) use ($template, $db): void {
            // @TODO use Mail classes ( saveEmailSetting() )
            $emailTemplateID = $db->getSingleInt(
                'SELECT kEmailvorlage
                    FROM temailvorlage
                    WHERE cModulId = :cModulId',
                'kEmailvorlage',
                ['cModulId' => \MAILTEMPLATE_BESTELLBESTAETIGUNG]
            );
            if (empty($template->getCopyTo())) {
                $db->queryPrepared(
                    "INSERT INTO temailvorlageeinstellungen VALUES (:emailTemplateID, 'cEmailCopyTo', :emailBCC)",
                    [
                        'emailTemplateID' => $emailTemplateID,
                        'emailBCC'        => $question->getValue()
                    ]
                );
            } else {
                $db->queryPrepared(
                    "UPDATE temailvorlageeinstellungen
                        SET cValue = :emailBCC
                        WHERE kEmailvorlage = :emailTemplateID
                            AND cKey = 'cEmailCopyTo'",
                    [
                        'emailTemplateID' => $emailTemplateID,
                        'emailBCC'        => $question->getValue()
                    ]
                );
            }
        });
        $this->addQuestion($question);

        $question = new Question($db);
        $question->setID(14);
        $question->setText(\__('adminUserEmail'));
        $question->setDescription(\__('adminUserEmailDesc'));
        $question->setType(QuestionType::EMAIL);
        $question->setIsFullWidth(true);
        $question->setValue(
            $db->select(
                'tadminlogin',
                'kAdminlogin',
                $adminAccount->getID()
            )->cMail ?? ''
        );
        $question->setOnSave(function (QuestionInterface $question) use ($adminAccount, $db): void {
            $db->update(
                'tadminlogin',
                'kAdminlogin',
                $adminAccount->getID(),
                (object)['cMail' => $question->getValue()]
            );
        });
        $this->addQuestion($question);
    }
}
