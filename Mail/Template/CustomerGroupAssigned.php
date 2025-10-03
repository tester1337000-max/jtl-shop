<?php

declare(strict_types=1);

namespace JTL\Mail\Template;

/**
 * Class CustomerGroupAssigned
 * @package JTL\Mail\Template
 */
class CustomerGroupAssigned extends CustomerAccountDeleted
{
    protected ?string $id = \MAILTEMPLATE_KUNDENGRUPPE_ZUWEISEN;
}
