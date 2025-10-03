<?php

declare(strict_types=1);

namespace JTL\Mail\Template;

/**
 * Class RatingReminder
 * @package JTL\Mail\Template
 */
class RatingReminder extends OrderShipped
{
    protected ?string $id = \MAILTEMPLATE_BEWERTUNGERINNERUNG;
}
