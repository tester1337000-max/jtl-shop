<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum Newsletter: string implements OptionInterface
{
    case DO_USE           = 'newsletter_active';
    case MAILING_METHOD   = 'newsletter_emailmethode';
    case SENDMAIL_PATH    = 'newsletter_sendmailpfad';
    case SMTP_HOST        = 'newsletter_smtp_host';
    case SMTP_PORT        = 'newsletter_smtp_port';
    case SMTP_AUTH        = 'newsletter_smtp_authnutzen';
    case SMTP_USER        = 'newsletter_smtp_benutzer';
    case SMTP_PASS        = 'newsletter_smtp_pass';
    case SMTP_ENCRYPTION  = 'newsletter_smtp_verschluesselung';
    case SENDER_ADDRESS   = 'newsletter_emailadresse';
    case TEST_RECIPIENT   = 'newsletter_emailtest';
    case SENDER_NAME      = 'newsletter_emailabsender';
    case DOUBLE_OPT_IN    = 'newsletter_doubleopt';
    case SPAM_PROTECTION  = 'newsletter_sicherheitscode';
    case SEND_DELAY_HOURS = 'newsletter_send_delay';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::NEWSLETTER;
    }
}
