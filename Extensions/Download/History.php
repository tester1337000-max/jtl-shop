<?php

declare(strict_types=1);

namespace JTL\Extensions\Download;

use JTL\Nice;
use JTL\Shop;
use stdClass;

/**
 * Class History
 * @package JTL\Extensions\Download
 */
class History
{
    protected ?int $kDownloadHistory = null;

    protected ?int $kDownload = null;

    protected ?int $kKunde = null;

    protected ?int $kBestellung = null;

    protected ?string $dErstellt = null;

    public function __construct(int $id = 0)
    {
        if ($id > 0) {
            $this->loadFromDB($id);
        }
    }

    public static function checkLicense(): bool
    {
        return Nice::getInstance()->checkErweiterung(\SHOP_ERWEITERUNG_DOWNLOADS);
    }

    private function loadFromDB(int $id): void
    {
        $history = Shop::Container()->getDB()->select(
            'tdownloadhistory',
            'kDownloadHistory',
            $id
        );
        if ($history !== null && $history->kDownloadHistory > 0) {
            $this->kDownload        = (int)$history->kDownload;
            $this->kDownloadHistory = (int)$history->kDownloadHistory;
            $this->kKunde           = (int)$history->kKunde;
            $this->kBestellung      = (int)$history->kBestellung;
            $this->dErstellt        = $history->dErstellt;
        }
    }

    /**
     * @return self[]
     */
    public static function getHistory(int $downloadID): array
    {
        return Shop::Container()->getDB()->getCollection(
            'SELECT kDownloadHistory AS id 
                FROM tdownloadhistory
                WHERE kDownload = :dlid
                ORDER BY dErstellt DESC',
            ['dlid' => $downloadID]
        )->map(fn(stdClass $e): self => new self((int)$e->id))->toArray();
    }

    /**
     * @return array<int, array<History>>
     */
    public static function getOrderHistory(int $customerID, int $orderID = 0): array
    {
        $history = [];
        if ($orderID > 0 || $customerID > 0) {
            $where = 'kBestellung = ' . $orderID;
            if ($orderID > 0) {
                $where .= ' AND kKunde = ' . $customerID;
            }

            $data = Shop::Container()->getDB()->getObjects(
                'SELECT kDownload, kDownloadHistory
                     FROM tdownloadhistory
                     WHERE ' . $where . '
                     ORDER BY dErstellt DESC'
            );
            foreach ($data as $item) {
                $item->kDownload = (int)$item->kDownload;
                if (!isset($history[$item->kDownload]) || !\is_array($history[$item->kDownload])) {
                    $history[$item->kDownload] = [];
                }
                $history[$item->kDownload][] = new self((int)$item->kDownloadHistory);
            }
        }

        return $history;
    }

    public function save(bool $primary = false): bool|int
    {
        $ins = $this->kopiereMembers();
        unset($ins->kDownloadHistory);

        $historyID = Shop::Container()->getDB()->insert('tdownloadhistory', $ins);
        if ($historyID > 0) {
            return $primary ? $historyID : true;
        }

        return false;
    }

    public function update(): int
    {
        $upd              = new stdClass();
        $upd->kDownload   = $this->kDownload;
        $upd->kKunde      = $this->kKunde;
        $upd->kBestellung = $this->kBestellung;
        $upd->dErstellt   = $this->dErstellt;

        return Shop::Container()->getDB()->update(
            'tdownloadhistory',
            'kDownloadHistory',
            (int)$this->kDownloadHistory,
            $upd
        );
    }

    public function setDownloadHistory(int $kDownloadHistory): self
    {
        $this->kDownloadHistory = $kDownloadHistory;

        return $this;
    }

    public function setDownload(int $kDownload): self
    {
        $this->kDownload = $kDownload;

        return $this;
    }

    public function setKunde(int $customerID): self
    {
        $this->kKunde = $customerID;

        return $this;
    }

    public function setBestellung(int $orderID): self
    {
        $this->kBestellung = $orderID;

        return $this;
    }

    public function setErstellt(string $dErstellt): self
    {
        $this->dErstellt = $dErstellt;

        return $this;
    }

    public function getDownloadHistory(): int
    {
        return (int)$this->kDownloadHistory;
    }

    public function getDownload(): int
    {
        return (int)$this->kDownload;
    }

    public function getKunde(): int
    {
        return (int)$this->kKunde;
    }

    public function getBestellung(): int
    {
        return (int)$this->kBestellung;
    }

    public function getErstellt(): ?string
    {
        return $this->dErstellt;
    }

    private function kopiereMembers(): stdClass
    {
        $obj = new stdClass();
        foreach (\array_keys(\get_object_vars($this)) as $member) {
            $obj->$member = $this->$member;
        }

        return $obj;
    }
}
