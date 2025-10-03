<?php

declare(strict_types=1);

namespace JTL\Mail\Validator;

use JTL\DB\DbInterface;
use JTL\Mail\Mail\MailInterface;
use JTL\Mail\Template\Model;

/**
 * Class MailValidator
 * @package JTL\Mail\Validator
 */
final class MailValidator implements ValidatorInterface
{
    private const ERR_DIVIDER = "\n---------------------------\n";

    /**
     * @param DbInterface  $db
     * @param array<mixed> $config
     */
    public function __construct(private readonly DbInterface $db, private readonly array $config)
    {
    }

    /**
     * @inheritdoc
     */
    public function validate(MailInterface $mail): bool
    {
        $model = $mail->getTemplate()?->getModel();
        if ($model === null) {
            return true;
        }
        $mailIsValid = true;
        $errText     = self::ERR_DIVIDER;
        if ($mail->getTemplate() !== null) {
            if ($this->isTemplateActivated($model) === false) {
                $mailIsValid = false;
                $errText     .= 'Please activate the corresponding email template ('
                    . $mail->getTemplate()->getID()
                    . ").\nTo do this, go to\nAdministration > Email > Templates\nin the back end of JTL-Shop.\n"
                    . 'Click the pen icon to open the template for editing and select -Yes- for -Send email-.'
                    . self::ERR_DIVIDER;
            }
            if ($this->checkBody($mail) === false) {
                $mailIsValid = false;
                $errText     .= 'Please enter a content for the corresponding email template ('
                    . $mail->getTemplate()->getID() . "). To do this, go to\nAdministration > Email > Templates\n"
                    . 'in the back end of JTL-Shop and use the pen icon to open the email template for editing.'
                    . self::ERR_DIVIDER;
            }
            if ($this->isBlacklisted($mail->getToMail())) {
                $mailIsValid = false;
                $errText     .= 'Tried to send an email using template -'
                    . $mail->getTemplate()->getID() . '- to -' . $mail->getToMail() . "-\n"
                    . "The recipient of the email is on the email blacklist.\n"
                    . "You can view the email blacklist in the back end of JTL-Shop at\n"
                    . 'Administration > Email > Blacklist'
                    . self::ERR_DIVIDER;
            }
        } else {
            $mailIsValid = false;
            $errText     .= "Tried to send an email, but corresponding template is missing.\n" . self::ERR_DIVIDER;
        }
        if ($mailIsValid === false) {
            $mail->setError($errText);
        }

        return $mailIsValid;
    }

    public function checkBody(MailInterface $mail): bool
    {
        return \mb_strlen($mail->getBodyHTML()) > 0 || \mb_strlen($mail->getBodyText()) > 0;
    }

    public function isBlacklisted(string $email): bool
    {
        if ($this->config['emailblacklist']['blacklist_benutzen'] !== 'Y') {
            return false;
        }
        $blackList = $this->db->select('temailblacklist', 'cEmail', $email);
        if ($blackList === null || empty($blackList->cEmail)) {
            return false;
        }
        $block = (object)[
            'cEmail'        => $blackList->cEmail,
            'dLetzterBlock' => 'NOW()'
        ];
        $this->db->insert('temailblacklistblock', $block);

        return true;
    }

    public function isTemplateActivated(Model $model): bool
    {
        return $model->getActive() === true;
    }
}
