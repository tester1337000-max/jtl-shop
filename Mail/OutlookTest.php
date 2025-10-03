<?php

declare(strict_types=1);

namespace JTL\Mail;

use Greew\OAuth2\Client\Provider\Azure;
use JTL\Settings\Option\Email;
use JTL\Settings\Settings;
use PHPMailer\PHPMailer\OAuth;

class OutlookTest extends SmtpTest
{
    public function run(Settings $settings): bool
    {
        $email        = $settings->string(Email::MAIL_SENDER);
        $clientId     = $settings->string(Email::OAUTH_CLIENT_ID);
        $clientSecret = $settings->string(Email::OAUTH_CLIENT_SECRET);
        $tenantId     = $settings->string(Email::OAUTH_TENANT_ID);
        $refreshToken = $settings->string(Email::OAUTH_REFRESH_TOKEN);

        $provider = new Azure([
            'clientId'     => $clientId,
            'clientSecret' => $clientSecret,
            'tenantId'     => $tenantId,
        ]);

        $oauth = new OAuth([
            'provider'     => $provider,
            'clientId'     => $clientId,
            'clientSecret' => $clientSecret,
            'refreshToken' => $refreshToken,
            'userName'     => $email,
        ]);

        return $this->runGeneric(
            host: 'smtp.office365.com',
            port: 587,
            smtpEncryption: 'ssl',
            user: $settings->string(Email::SMTP_USER),
            pass: $settings->string(Email::SMTP_PASS),
            authType: 'XOAUTH2',
            oauth: $oauth,
        );
    }
}
