<?php

declare(strict_types=1);

namespace JTL\Mail;

use JTL\Settings\Option\Email;
use JTL\Settings\Settings;
use League\OAuth2\Client\Provider\Google;
use PHPMailer\PHPMailer\OAuth;

class GmailTest extends SmtpTest
{
    public function run(Settings $settings): bool
    {
        $email        = $settings->string(Email::MAIL_SENDER);
        $clientId     = $settings->string(Email::OAUTH_CLIENT_ID);
        $clientSecret = $settings->string(Email::OAUTH_CLIENT_SECRET);
        $refreshToken = $settings->string(Email::OAUTH_REFRESH_TOKEN);

        $provider = new Google([
            'clientId'     => $clientId,
            'clientSecret' => $clientSecret,
        ]);

        $oauth = new OAuth([
            'provider'     => $provider,
            'clientId'     => $clientId,
            'clientSecret' => $clientSecret,
            'refreshToken' => $refreshToken,
            'userName'     => $email,
        ]);

        return $this->runGeneric(
            host:           'smtp.gmail.com',
            port:           587,
            smtpEncryption: 'ssl',
            user:           $email,
            pass:           '',
            authType:       'XOAUTH2',
            oauth:           $oauth,
        );
    }
}
