<?php

declare(strict_types=1);

namespace JTL\Alert;

use JTL\Shop;

/**
 * Class Alert
 * @package JTL\Alert
 */
class Alert
{
    private bool $dismissable = false;

    private int $fadeOut = self::FADE_NEVER;

    private bool $showInAlertListTemplate = true;

    private bool $saveInSession = false;

    private ?string $linkHref = null;

    private ?string $linkText = null;

    private ?string $icon = null;

    private ?string $id = null;

    public const TYPE_PRIMARY   = 'primary';
    public const TYPE_SECONDARY = 'secondary';
    public const TYPE_SUCCESS   = 'success';
    public const TYPE_DANGER    = 'danger';
    public const TYPE_WARNING   = 'warning';
    public const TYPE_INFO      = 'info';
    public const TYPE_LIGHT     = 'light';
    public const TYPE_DARK      = 'dark';

    public const TYPE_ERROR = 'error';
    public const TYPE_NOTE  = 'note';

    public const FADE_FAST   = 3000;
    public const FADE_SLOW   = 8000;
    public const FADE_MEDIUM = 5000;
    public const FADE_NEVER  = 0;

    public const ICON_WARNING = 'warning';
    public const ICON_INFO    = 'info-circle';
    public const ICON_CHECK   = 'check-circle';

    /**
     * @return string[]
     */
    public function __sleep(): array
    {
        $propertiesToSave = ['type', 'message', 'key'];
        if ($this->getOptions() !== null) {
            $propertiesToSave[] = 'options';
        }

        return $propertiesToSave;
    }

    public function __wakeup(): void
    {
        $this->initAlert();
    }

    /**
     * @param array<string, bool|int|string>|null $options
     */
    public function __construct(
        private string $type,
        private string $message,
        private string $key,
        private ?array $options = null
    ) {
        $this->initAlert();
    }

    private function initAlert(): void
    {
        switch ($this->getType()) {
            case self::TYPE_DANGER:
            case self::TYPE_ERROR:
            case self::TYPE_WARNING:
                $this->setDismissable(true)
                    ->setIcon(self::ICON_WARNING);
                break;
            case self::TYPE_INFO:
            case self::TYPE_NOTE:
                $this->setIcon(self::ICON_INFO);
                break;
            case self::TYPE_SUCCESS:
                $this->setFadeOut(self::FADE_SLOW)
                    ->setIcon(self::ICON_CHECK);
                break;
            default:
                break;
        }
        $this->updatePropertiesFromOptions();
        if ($this->getSaveInSession()) {
            $this->addToSession();
        }
    }

    private function updatePropertiesFromOptions(): void
    {
        if ($this->getOptions() === null) {
            return;
        }
        foreach ($this->getOptions() as $optionKey => $optionValue) {
            $methodName = 'set' . \ucfirst($optionKey);
            if (\is_callable([$this, $methodName])) {
                $this->$methodName($optionValue);
            }
        }
    }

    public function display(): void
    {
        echo Shop::Smarty()->assign('alert', $this)
            ->fetch('snippets/alert.tpl');

        if ($this->getSaveInSession()) {
            $this->removeFromSession();
        }
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    private function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    private function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    private function setKey(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    public function getDismissable(): bool
    {
        return $this->dismissable;
    }

    private function setDismissable(bool $dismissable): self
    {
        $this->dismissable = $dismissable;

        return $this;
    }

    public function getFadeOut(): int
    {
        return $this->fadeOut;
    }

    private function setFadeOut(int $fadeOut): self
    {
        $this->fadeOut = $fadeOut;

        return $this;
    }

    public function getSaveInSession(): bool
    {
        return $this->saveInSession;
    }

    private function setSaveInSession(bool $saveInSession): self
    {
        $this->saveInSession = $saveInSession;

        return $this;
    }

    public function getShowInAlertListTemplate(): bool
    {
        return $this->showInAlertListTemplate;
    }

    private function setShowInAlertListTemplate(bool $showInAlertListTemplate): self
    {
        $this->showInAlertListTemplate = $showInAlertListTemplate;

        return $this;
    }

    public function getLinkHref(): ?string
    {
        return $this->linkHref;
    }

    private function setLinkHref(string $linkHref): self
    {
        $this->linkHref = $linkHref;

        return $this;
    }

    public function getLinkText(): ?string
    {
        return $this->linkText;
    }

    private function setLinkText(string $linkText): self
    {
        $this->linkText = $linkText;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    private function setIcon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * @return array<string, bool|int|string>|null
     */
    private function getOptions(): ?array
    {
        return $this->options;
    }

    /**
     * @param array<string, string>|null $options
     */
    private function setOptions(?array $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    private function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    private function addToSession(): void
    {
        if (!isset($_SESSION['alerts'])) {
            $_SESSION['alerts'] = [];
        }
        $_SESSION['alerts'][$this->getKey()] = \serialize($this);
    }

    public function removeFromSession(): void
    {
        if (isset($_SESSION['alerts'][$this->getKey()])) {
            unset($_SESSION['alerts'][$this->getKey()]);
        }
    }

    public function getCssType(): string
    {
        return match ($this->getType()) {
            self::TYPE_ERROR => self::TYPE_DANGER,
            self::TYPE_NOTE  => self::TYPE_INFO,
            default          => $this->getType(),
        };
    }
}
