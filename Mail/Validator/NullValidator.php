<?php

declare(strict_types=1);

namespace JTL\Mail\Validator;

use JTL\Mail\Mail\MailInterface;

/**
 * Class NullValidator
 * @package JTL\Mail\Validator
 */
final class NullValidator implements ValidatorInterface
{
    /**
     * @inheritdoc
     */
    public function validate(MailInterface $mail): bool
    {
        return true;
    }
}
