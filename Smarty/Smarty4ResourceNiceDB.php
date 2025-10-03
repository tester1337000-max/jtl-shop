<?php

declare(strict_types=1);

namespace JTL\Smarty;

use JTL\DB\DbInterface;

class Smarty4ResourceNiceDB extends \Smarty_Resource_Custom
{
    protected SmartyResourceNiceDB $smartyResource;

    public function __construct(DbInterface $db, string $type = ContextType::EXPORT)
    {
        $this->smartyResource = new SmartyResourceNiceDB($db, $type);
    }

    public function getType(): string
    {
        return $this->smartyResource->getType();
    }

    public function setType(string $type): void
    {
        $this->smartyResource->setType($type);
    }

    protected function fetch($name, &$source, &$mtime): void
    {
        $this->smartyResource->fetch($name, $source, $mtime);
    }

    protected function fetchTimestamp($name): int
    {
        return \time();
    }
}
