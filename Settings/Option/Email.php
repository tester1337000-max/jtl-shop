<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum Email: string implements OptionInterface
{
    case MAIL_METHOD           = 'email_methode';
    case SENDMAIL_PATH         = 'email_sendmail_pfad';
    case SMTP_HOST             = 'email_smtp_hostname';
    case SMTP_PORT             = 'email_smtp_port';
    case SMTP_AUTH             = 'email_smtp_auth';
    case SMTP_USER             = 'email_smtp_user';
    case SMTP_PASS             = 'email_smtp_pass';
    case SMTP_ENCRYPTION       = 'email_smtp_verschluesselung';
    case MAIL_SENDER           = 'email_master_absender';
    case MAIL_SENDER_NAME      = 'email_master_absender_name';
    case MAIL_SEND_IMMEDIATELY = 'email_send_immediately';
    case OAUTH_CLIENT_ID       = 'oauth_client_id';
    case OAUTH_CLIENT_SECRET   = 'oauth_client_secret';
    case OAUTH_TENANT_ID       = 'oauth_tenant_id';
    case OAUTH_ACCESS_TOKEN    = 'oauth_access_token';
    case OAUTH_REFRESH_TOKEN   = 'oauth_refresh_token';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::EMAIL;
    }
}
