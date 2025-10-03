<?php

declare(strict_types=1);

namespace JTL\Mail\Validator;

use JTL\Mail\Mail\MailInterface;

/**
 * Interface ValidatorInterface
 * @package JTL\Mail\Validator
 */
interface ValidatorInterface
{
    /**
     * @param MailInterface $mail
     * @return bool
     */
    public function validate(MailInterface $mail): bool;
}
