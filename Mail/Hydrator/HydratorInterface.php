<?php

declare(strict_types=1);

namespace JTL\Mail\Hydrator;

use JTL\DB\DbInterface;
use JTL\Language\LanguageModel;
use JTL\Shopsetting;
use JTL\Smarty\JTLSmarty;

/**
 * Interface HydratorInterface
 * @package JTL\Mail\Hydrator
 */
interface HydratorInterface
{
    /**
     * @param object|null   $data
     * @param LanguageModel $language
     */
    public function hydrate(?object $data, LanguageModel $language): void;

    /**
     * @param string $variable
     * @param mixed  $content
     */
    public function add(string $variable, mixed $content): void;

    /**
     * @return JTLSmarty
     */
    public function getSmarty(): JTLSmarty;

    /**
     * @param JTLSmarty $smarty
     */
    public function setSmarty(JTLSmarty $smarty): void;

    /**
     * @return DbInterface
     */
    public function getDB(): DbInterface;

    /**
     * @param DbInterface $db
     */
    public function setDB(DbInterface $db): void;

    /**
     * @return Shopsetting
     */
    public function getSettings(): Shopsetting;

    /**
     * @param Shopsetting $settings
     */
    public function setSettings(Shopsetting $settings): void;
}
