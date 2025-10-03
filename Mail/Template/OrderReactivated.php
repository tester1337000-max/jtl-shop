<?php

declare(strict_types=1);

namespace JTL\Mail\Template;

/**
 * Class OrderReactivated
 * @package JTL\Mail\Template
 */
class OrderReactivated extends OrderCleared
{
    protected ?string $id = \MAILTEMPLATE_BESTELLUNG_RESTORNO;
}
