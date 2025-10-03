<?php

declare(strict_types=1);

namespace JTL\Backend\Wizard\Steps;

/**
 * Class Error
 * @package JTL\Backend\Wizard\Steps
 */
class Error
{
    public int $questionID;

    public int $stepID;

    public int $code;

    public string $message;

    public bool $critical = false;

    public function __construct(int $step, int $questionID, int $code)
    {
        $this->setStepID($step);
        $this->setQuestionID($questionID);
        $this->setCode($code);
        $this->setMessageByCode();
    }

    public function getQuestionID(): int
    {
        return $this->questionID;
    }

    public function setQuestionID(int $questionID): void
    {
        $this->questionID = $questionID;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    public function isCritical(): bool
    {
        return $this->critical;
    }

    public function setCritical(bool $critical): void
    {
        $this->critical = $critical;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function setCode(int $code): void
    {
        $this->code = $code;
    }

    private function setMessageByCode(): void
    {
        switch ($this->getCode()) {
            case ErrorCode::ERROR_REQUIRED:
                $error = \__('validationErrorRequired');
                $this->setCritical(true);
                break;
            case ErrorCode::INVALID_EMAIL:
                $error = \__('validationErrorIncorrectEmail');
                $this->setCritical(true);
                break;
            case ErrorCode::ERROR_SSL_PLUGIN:
                $error = \__('validationErrorSSLPlugin');
                $this->setCritical(true);
                break;
            case ErrorCode::ERROR_SSL:
                $error = \__('validationErrorSSL');
                $this->setCritical(true);
                break;
            case ErrorCode::ERROR_VAT:
                $error = \__('errorVATPattern');
                $this->setCritical(true);
                break;
            case ErrorCode::OK:
            default:
                $error = '';
                break;
        }

        $this->setMessage($error);
    }

    public function getStepID(): int
    {
        return $this->stepID;
    }

    public function setStepID(int $stepID): void
    {
        $this->stepID = $stepID;
    }
}
