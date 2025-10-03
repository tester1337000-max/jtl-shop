<?php

declare(strict_types=1);

namespace JTL\Mail\Template;

use JTL\DB\DbInterface;
use JTL\Mail\Renderer\RendererInterface;
use JTL\Smarty\JTLSmarty;

/**
 * Interface TemplateInterface
 * @package JTL\Mail\Template
 */
interface TemplateInterface
{
    /**
     * @param DbInterface $db
     */
    public function __construct(DbInterface $db);

    /**
     * @param int $languageID
     * @param int $customerGroupID
     * @return Model|null
     */
    public function load(int $languageID, int $customerGroupID): ?Model;

    /**
     * @return array<mixed>
     */
    public function getConfig(): array;

    /**
     * @param array<mixed> $config
     */
    public function setConfig(array $config): void;

    /**
     * @param JTLSmarty $smarty
     * @param mixed     $data
     */
    public function preRender(JTLSmarty $smarty, mixed $data): void;

    /**
     * @param RendererInterface $renderer
     * @param int               $languageID
     * @param int               $customerGroupID
     */
    public function render(RendererInterface $renderer, int $languageID, int $customerGroupID): void;

    /**
     * @return Model|null
     */
    public function getModel(): ?Model;

    /**
     * @return string
     */
    public function getID(): string;

    /**
     * @param string $id
     */
    public function setID(string $id): void;

    /**
     * @return string|null
     */
    public function getFromMail(): ?string;

    /**
     * @param string|null $mail
     */
    public function setFromMail(?string $mail): void;

    /**
     * @return string|null
     */
    public function getFromName(): ?string;

    /**
     * @param string|null $name
     */
    public function setFromName(?string $name): void;

    /**
     * @return array<mixed>
     */
    public function getCopyTo(): array;

    /**
     * @param array<mixed> $copy
     */
    public function setCopyTo(array $copy): void;

    /**
     * @return array<mixed>
     */
    public function getLegalData(): array;

    /**
     * @return string|null
     */
    public function getHTML(): ?string;

    /**
     * @param string|null $html
     */
    public function setHTML(?string $html): void;

    /**
     * @return string|null
     */
    public function getText(): ?string;

    /**
     * @param string|null $text
     */
    public function setText(?string $text): void;

    /**
     * @return string|null
     */
    public function getSubject(): ?string;

    /**
     * @param string|null $overrideSubject
     */
    public function setSubject(?string $overrideSubject): void;

    /**
     * @return int
     */
    public function getLanguageID(): int;

    /**
     * @param int $languageID
     */
    public function setLanguageID(int $languageID): void;
}
