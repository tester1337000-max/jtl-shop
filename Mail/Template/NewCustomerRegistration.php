<?php

declare(strict_types=1);

namespace JTL\Mail\Template;

/**
 * Class NewCustomerRegistration
 * @package JTL\Mail\Template
 */
class NewCustomerRegistration extends CustomerAccountDeleted
{
    protected ?string $id = \MAILTEMPLATE_NEUKUNDENREGISTRIERUNG;
}
