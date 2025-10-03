<?php

declare(strict_types=1);

namespace JTL\Services\JTL;

use Illuminate\Support\Collection;
use JTL\Alert\Alert;

/**
 * Class AlertService
 * @package JTL\Services\JTL
 */
class AlertService implements AlertServiceInterface
{
    /**
     * @var Collection<int, Alert>
     */
    private Collection $alertList;

    public function __construct()
    {
        $this->alertList = new Collection();
        $this->initFromSession();
    }

    /**
     * @inheritdoc
     */
    public function initFromSession(): void
    {
        foreach ($_SESSION['alerts'] ?? [] as $alertSerialized) {
            /** @var Alert|false $alert */
            $alert = \unserialize($alertSerialized, ['allowed_classes', Alert::class]);
            if ($alert !== false) {
                $this->pushAlert($alert);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function addAlert(string $type, string $message, string $key, ?array $options = null): ?Alert
    {
        if (\trim($message) === '' || \trim($type) === '' || \trim($key) === '') {
            return null;
        }
        $alert = new Alert($type, $message, $key, $options);
        $this->pushAlert($alert);

        return $alert;
    }

    /**
     * @inheritdoc
     */
    public function addError(string $message, string $key, ?array $options = null): ?Alert
    {
        return $this->addAlert(Alert::TYPE_ERROR, $message, $key, $options);
    }

    /**
     * @inheritdoc
     */
    public function addWarning(string $message, string $key, ?array $options = null): ?Alert
    {
        return $this->addAlert(Alert::TYPE_WARNING, $message, $key, $options);
    }

    /**
     * @inheritdoc
     */
    public function addInfo(string $message, string $key, ?array $options = null): ?Alert
    {
        return $this->addAlert(Alert::TYPE_INFO, $message, $key, $options);
    }

    /**
     * @inheritdoc
     */
    public function addSuccess(string $message, string $key, ?array $options = null): ?Alert
    {
        return $this->addAlert(Alert::TYPE_SUCCESS, $message, $key, $options);
    }

    /**
     * @inheritdoc
     */
    public function addDanger(string $message, string $key, ?array $options = null): ?Alert
    {
        return $this->addAlert(Alert::TYPE_DANGER, $message, $key, $options);
    }

    /**
     * @inheritdoc
     */
    public function addNotice(string $message, string $key, ?array $options = null): ?Alert
    {
        return $this->addAlert(Alert::TYPE_NOTE, $message, $key, $options);
    }

    /**
     * @inheritdoc
     */
    public function getAlert(string $key): ?Alert
    {
        return $this->getAlertList()->first(fn(Alert $alert): bool => $alert->getKey() === $key);
    }

    /**
     * @inheritdoc
     */
    public function displayAlertByKey(string $key): void
    {
        if ($alert = $this->getAlert($key)) {
            $alert->display();
        }
    }

    /**
     * @return Collection<int, Alert>
     */
    public function getAlertList(): Collection
    {
        return $this->alertList;
    }

    /**
     * @inheritdoc
     */
    public function alertTypeExists(string $type): bool
    {
        return $this->getAlertList()->filter(fn(Alert $alert): bool => $alert->getType() === $type)->count() > 0;
    }

    /**
     * @inheritdoc
     */
    public function removeAlertByKey(string $key): void
    {
        /** @var int|false $id */
        $id = $this->getAlertList()->search(fn(Alert $alert): bool => $alert->getKey() === $key);
        if ($id !== false) {
            /** @var Alert $alert */
            $alert = $this->getAlertList()->pull($id);
            if ($alert->getSaveInSession()) {
                $alert->removeFromSession();
            }
        }
    }

    private function pushAlert(Alert $alert): void
    {
        $this->removeAlertByKey($alert->getKey());
        $this->getAlertList()->push($alert);
    }
}
