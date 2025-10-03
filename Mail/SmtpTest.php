<?php

declare(strict_types=1);

namespace JTL\Mail;

use JTL\Services\JTL\AlertServiceInterface;
use JTL\Settings\Option\Email;
use JTL\Settings\Settings;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\OAuth;
use PHPMailer\PHPMailer\SMTP;

/**
 * Class SmtpTest
 * @package JTL\Mail
 */
class SmtpTest
{
    public function __construct(protected AlertServiceInterface $alertService)
    {
    }

    protected function runGeneric(
        string $host,
        int $port,
        string $smtpEncryption,
        string $user,
        string $pass,
        ?string $authType = null,
        ?OAuth $oauth = null,
    ): bool {
        $smtp = new SMTP();
        $smtp->setDebugLevel(SMTP::DEBUG_CONNECTION);
        try {
            if (!$smtp->connect($host, $port)) {
                throw new Exception(\__('smtpTestErrorConnect'));
            }
            if (!$smtp->hello(\gethostname() ?: '')) {
                throw new Exception(\__('smtpTestErrorEHLO') . ': ' . $smtp->getError()['error']);
            }
            $e = $smtp->getServerExtList();
            if (\is_array($e) && \array_key_exists('STARTTLS', $e)) {
                $tlsok = $smtp->startTLS();
                if (!$tlsok) {
                    throw new Exception(\__('smtpTestErrorEncryption') . ': ' . $smtp->getError()['error']);
                }
                if (!$smtp->hello(\gethostname() ?: '')) {
                    throw new Exception(\__('smtpTestErrorEHLO2') . ': ' . $smtp->getError()['error']);
                }
                $e = $smtp->getServerExtList();
            } elseif ($smtpEncryption === 'tls') {
                throw new Exception(\__('smtpTestErrorNoTLS'));
            }
            if (!\is_array($e) || !\array_key_exists('AUTH', $e)) {
                throw new Exception(\__('smtpTestErrorNoAuth'));
            }
            try {
                $result = $smtp->authenticate($user, $pass, $authType, $oauth);
            } catch (\Exception $e) {
                throw new Exception(\__('smtpTestErrorAuthFailed') . ': ' . $e->getMessage());
            }
            if (!$result) {
                throw new Exception(\__('smtpTestErrorAuthFailed') . ': ' . $smtp->getError()['error']);
            }
            echo 'Connected ok!';
        } catch (Exception $e) {
            echo 'SMTP error: ' . $e->getMessage(), "\n";
            $this->alertService->addError(\__('smtpTestError') . ': ' . $e->getMessage(), 'smtpTestError');
        }

        return $smtp->quit();
    }

    public function run(Settings $settings): bool
    {
        return $this->runGeneric(
            host: $settings->string(Email::SMTP_HOST),
            port: $settings->int(Email::SMTP_PORT),
            smtpEncryption: $settings->string(Email::SMTP_ENCRYPTION),
            user: $settings->string(Email::SMTP_USER),
            pass: $settings->string(Email::SMTP_PASS),
        );
    }
}
