<?php

declare(strict_types=1);

namespace JTL\Newsletter;

class Recipient
{
    public ?string $cAnrede = null;

    public ?string $cEmail = null;

    public ?string $cVorname = null;

    public ?string $cNachname = null;

    public int $kKunde = 0;

    public int $customerGroupID = 0;

    public ?int $kSprache = null;

    public ?string $cOptCode = null;

    public ?string $cLoeschCode = null;

    public ?string $dEingetragen = null;

    public int $nAktiv = 1;
}
