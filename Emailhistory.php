<?php

declare(strict_types=1);

namespace JTL;

use Exception;
use JTL\DB\DbInterface;
use stdClass;

/**
 * Class Emailhistory
 * @package JTL
 */
class Emailhistory
{
    public int $kEmailhistory = 0;

    public int $kEmailvorlage = 0;

    public string $cSubject = '';

    public string $cFromName = '';

    public string $cFromEmail = '';

    public string $cToName = '';

    public string $cToEmail = '';

    public string $dSent = '';

    private DbInterface $db;

    public function __construct(?int $id = null, ?object $data = null, ?DbInterface $db = null)
    {
        $this->db = $db ?? Shop::Container()->getDB();
        if ($id > 0) {
            $this->loadFromDB($id);
        } elseif ($data !== null) {
            foreach (\array_keys(\get_object_vars($data)) as $member) {
                $methodName = 'set' . \mb_substr($member, 1);
                if (\method_exists($this, $methodName)) {
                    $this->$methodName($data->$member);
                }
            }
        }
    }

    protected function loadFromDB(int $id): self
    {
        $data = $this->db->select('temailhistory', 'kEmailhistory', $id);
        if ($data !== null && $data->kEmailhistory > 0) {
            $this->kEmailhistory = (int)$data->kEmailhistory;
            $this->kEmailvorlage = (int)$data->kEmailvorlage;
            $this->cSubject      = $data->cSubject;
            $this->cFromName     = $data->cFromName;
            $this->cFromEmail    = $data->cFromEmail;
            $this->cToName       = $data->cToName;
            $this->cToEmail      = $data->cToEmail;
            $this->dSent         = $data->dSent;
        }

        return $this;
    }

    /**
     * @param bool $primary
     * @return ($primary is true ? int|false : bool)
     * @throws Exception
     */
    public function save(bool $primary = true): bool|int
    {
        if ($this->kEmailhistory > 0) {
            return $this->update();
        }
        $ins                = new stdClass();
        $ins->kEmailvorlage = $this->kEmailvorlage;
        $ins->cSubject      = $this->cSubject;
        $ins->cFromName     = $this->cFromName;
        $ins->cFromEmail    = $this->cFromEmail;
        $ins->cToName       = $this->cToName;
        $ins->cToEmail      = $this->cToEmail;
        $ins->dSent         = $this->dSent;

        $key = $this->db->insert('temailhistory', $ins);
        if ($key > 0) {
            return $primary ? $key : true;
        }

        return false;
    }

    /**
     * @throws Exception
     */
    public function update(): int
    {
        $upd                = new stdClass();
        $upd->kEmailhistory = $this->kEmailhistory;
        $upd->kEmailvorlage = $this->kEmailvorlage;
        $upd->cSubject      = $this->cSubject;
        $upd->cFromName     = $this->cFromName;
        $upd->cFromEmail    = $this->cFromEmail;
        $upd->cToName       = $this->cToName;
        $upd->cToEmail      = $this->cToEmail;
        $upd->dSent         = $this->dSent;

        return $this->db->updateRow('temailhistory', 'kEmailhistory', $this->getEmailhistory(), $upd);
    }

    public function delete(): int
    {
        return $this->db->delete('temailhistory', 'kEmailhistory', $this->getEmailhistory());
    }

    /**
     * @return Emailhistory[]
     */
    public function getAll(string $limitSQL = ''): array
    {
        $historyData = $this->db->getObjects(
            'SELECT * 
                FROM temailhistory 
                ORDER BY dSent DESC' . $limitSQL
        );
        $history     = [];
        foreach ($historyData as $item) {
            $item->kEmailhistory = (int)$item->kEmailhistory;
            $item->kEmailvorlage = (int)$item->kEmailvorlage;
            $history[]           = new self(null, $item, $this->db);
        }

        return $history;
    }

    public function getCount(): int
    {
        return $this->db->getSingleInt('SELECT COUNT(*) AS cnt FROM temailhistory', 'cnt');
    }

    /**
     * @param int[]|numeric-string[] $ids
     */
    public function deletePack(array $ids): int
    {
        if (\count($ids) === 0) {
            return -1;
        }

        return $this->db->getAffectedRows(
            'DELETE 
                FROM temailhistory 
                WHERE kEmailhistory IN (' . \implode(',', \array_map('\intval', $ids)) . ')'
        );
    }

    public function deleteAll(): int
    {
        Shop::Container()->getLogService()->notice('eMail-History gelöscht');
        $res = $this->db->getAffectedRows('DELETE FROM temailhistory');
        $this->db->query('TRUNCATE TABLE temailhistory');

        return $res;
    }

    public function getEmailhistory(): int
    {
        return $this->kEmailhistory;
    }

    public function setEmailhistory(int $kEmailhistory): self
    {
        $this->kEmailhistory = $kEmailhistory;

        return $this;
    }

    public function getEmailvorlage(): int
    {
        return $this->kEmailvorlage;
    }

    public function setEmailvorlage(int $kEmailvorlage): self
    {
        $this->kEmailvorlage = $kEmailvorlage;

        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->cSubject;
    }

    public function setSubject(string $subject): self
    {
        $this->cSubject = $subject;

        return $this;
    }

    public function getFromName(): ?string
    {
        return $this->cFromName;
    }

    public function setFromName(string $fromName): self
    {
        $this->cFromName = $fromName;

        return $this;
    }

    public function getFromEmail(): ?string
    {
        return $this->cFromEmail;
    }

    public function setFromEmail(string $fromEmail): self
    {
        $this->cFromEmail = $fromEmail;

        return $this;
    }

    public function getToName(): ?string
    {
        return $this->cToName;
    }

    public function setToName(string $toName): self
    {
        $this->cToName = $toName;

        return $this;
    }

    public function getToEmail(): ?string
    {
        return $this->cToEmail;
    }

    public function setToEmail(string $toEmail): self
    {
        $this->cToEmail = $toEmail;

        return $this;
    }

    public function getSent(): ?string
    {
        return $this->dSent;
    }

    public function setSent(string $date): self
    {
        $this->dSent = $date;

        return $this;
    }
}
