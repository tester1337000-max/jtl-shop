<?php

declare(strict_types=1);

namespace JTL\Backend\Settings;

/**
 * Class Log
 * @package JTL\Backend\Settings
 */
class Log
{
    private int $id;

    private int $adminID;

    private string $adminName;

    private string $changerIp;

    private string $settingName;

    private string $settingType;

    private string $valueOld;

    private string $valueNew;

    private string $date;

    public function __construct()
    {
    }

    /**
     * @param \stdClass $data
     * @return Log
     */
    public function init(\stdClass $data): self
    {
        $this->setID((int)$data->kEinstellungenLog);
        $this->setAdminID((int)$data->kAdminlogin);
        $this->setAdminName($data->adminName ?? \__('unknown') . '(' . $data->kAdminlogin . ')');
        $this->setChangerIP($data->cIP ?? '');
        $this->setSettingType($data->settingType);
        $this->setSettingName($data->cEinstellungenName);
        $this->setValueNew($data->cEinstellungenWertNeu);
        $this->setValueOld($data->cEinstellungenWertAlt);
        $this->setDate($data->dDatum);

        return $this;
    }

    public function getID(): int
    {
        return $this->id;
    }

    public function setID(int $id): void
    {
        $this->id = $id;
    }

    public function getAdminID(): int
    {
        return $this->adminID;
    }

    public function setAdminID(int $adminId): void
    {
        $this->adminID = $adminId;
    }

    public function getSettingName(): string
    {
        return $this->settingName;
    }

    public function setSettingName(string $settingName): void
    {
        $this->settingName = $settingName;
    }

    public function getValueOld(): string
    {
        return $this->valueOld;
    }

    public function setValueOld(string $valueOld): void
    {
        $this->valueOld = $valueOld;
    }

    public function getValueNew(): string
    {
        return $this->valueNew;
    }

    public function setValueNew(string $valueNew): void
    {
        $this->valueNew = $valueNew;
    }

    public function getDate(): string
    {
        return $this->date;
    }

    public function setDate(string $date): void
    {
        $this->date = $date;
    }

    public function getAdminName(): string
    {
        return $this->adminName;
    }

    public function setAdminName(string $adminName): void
    {
        $this->adminName = $adminName;
    }

    public function getChangerIP(): string
    {
        return $this->changerIp;
    }

    public function setChangerIP(string $ip): void
    {
        $this->changerIp = $ip;
    }

    public function getSettingType(): string
    {
        return $this->settingType;
    }

    public function setSettingType(string $settingType): void
    {
        $this->settingType = $settingType;
    }
}
