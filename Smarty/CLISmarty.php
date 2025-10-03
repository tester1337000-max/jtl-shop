<?php

declare(strict_types=1);

namespace JTL\Smarty;

/**
 * Class CLISmarty
 * @package JTL\Smarty
 */
class CLISmarty extends JTLSmarty
{
    public function __construct()
    {
        parent::__construct(true, ContextType::CLI);
        $this->setCaching(JTLSmarty::CACHING_OFF)
            ->setDebugging(false);
    }

    protected function initTemplate(): ?string
    {
        return null;
    }
}
