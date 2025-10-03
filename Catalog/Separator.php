<?php

declare(strict_types=1);

namespace JTL\Catalog;

use InvalidArgumentException;
use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Language\LanguageHelper;
use JTL\Shop;
use stdClass;

/**
 * Class Separator
 * @package JTL\Catalog
 */
class Separator
{
    public int $kTrennzeichen = 0;

    protected int $kSprache = 0;

    protected int $nEinheit = 0;

    protected int $nDezimalstellen = 0;

    protected string $cDezimalZeichen = '';

    protected string $cTausenderZeichen = '';

    /**
     * @var array<int, array<int, stdClass>>
     */
    private static array $unitObject = [];

    private DbInterface $db;

    private JTLCacheInterface $cache;

    public function __construct(int $id = 0, ?DbInterface $db = null, ?JTLCacheInterface $cache = null)
    {
        $this->db    = $db ?? Shop::Container()->getDB();
        $this->cache = $cache ?? Shop::Container()->getCache();
        if ($id > 0) {
            $this->loadFromDB($id);
        }
    }

    private function loadFromDB(int $id = 0): self
    {
        $cacheID = 'units_lfdb_' . $id;
        if (($data = $this->cache->get($cacheID)) === false) {
            $data = $this->db->select('ttrennzeichen', 'kTrennzeichen', $id);
            $this->cache->set($cacheID, $data, [\CACHING_GROUP_CORE]);
        }
        if ($data !== null && $data->kTrennzeichen > 0) {
            $this->kTrennzeichen     = (int)$data->kTrennzeichen;
            $this->kSprache          = (int)$data->kSprache;
            $this->nEinheit          = (int)$data->nEinheit;
            $this->nDezimalstellen   = (int)$data->nDezimalstellen;
            $this->cDezimalZeichen   = $data->cDezimalZeichen;
            $this->cTausenderZeichen = $data->cTausenderZeichen;
        }

        return $this;
    }

    /**
     * getUnit() can be called very often within one page request
     * so try to use static class variable and object cache to avoid
     * unnecessary sql request
     */
    private static function getUnitObject(int $unitID, int $languageID): ?stdClass
    {
        if (isset(self::$unitObject[$languageID][$unitID])) {
            return self::$unitObject[$languageID][$unitID];
        }
        $cache   = Shop::Container()->getCache();
        $db      = Shop::Container()->getDB();
        $cacheID = 'units_' . $unitID . '_' . $languageID;
        if (($data = $cache->get($cacheID)) === false) {
            $data = $db->select(
                'ttrennzeichen',
                'nEinheit',
                $unitID,
                'kSprache',
                $languageID
            );
            if ($data !== null) {
                $data->kTrennzeichen   = (int)$data->kTrennzeichen;
                $data->kSprache        = (int)$data->kSprache;
                $data->nEinheit        = (int)$data->nEinheit;
                $data->nDezimalstellen = (int)$data->nDezimalstellen;
            }
            $cache->set($cacheID, $data, [\CACHING_GROUP_CORE]);
        }
        if (!isset(self::$unitObject[$languageID])) {
            self::$unitObject[$languageID] = [];
        }
        self::$unitObject[$languageID][$unitID] = $data;

        return $data;
    }

    /**
     * @param float|int|numeric-string $qty
     * @return string|float|int|numeric-string
     */
    public static function getUnit(int $unitID, int $languageID, float|int|string $qty = -1): float|int|string
    {
        if (!$languageID) {
            $languageID = LanguageHelper::getDefaultLanguage()->getId();
        }
        if ($unitID > 255) {
            // db field is only tinyint(3)!
            throw new InvalidArgumentException('Cannot use ID > 255');
        }
        if ($unitID <= 0 || $languageID <= 0) {
            return $qty;
        }
        $data = self::getUnitObject($unitID, $languageID);
        if ($data === null && self::insertMissingRow($unitID, $languageID) > 0) {
            $data = self::getUnitObject($unitID, $languageID);
        }
        if (isset($data->kTrennzeichen) && $data->kTrennzeichen > 0) {
            return \number_format(
                (float)$qty,
                $data->nDezimalstellen,
                $data->cDezimalZeichen,
                $data->cTausenderZeichen
            );
        }

        return $qty;
    }

    public static function insertMissingRow(int $unitID, int $languageID): int|false
    {
        // Standardwert [kSprache][nEinheit]
        $rows = [];
        foreach (LanguageHelper::getAllLanguages() as $language) {
            $rows[$language->getId()][\JTL_SEPARATOR_WEIGHT] = [
                'nDezimalstellen'   => 2,
                'cDezimalZeichen'   => ',',
                'cTausenderZeichen' => '.'
            ];
            $rows[$language->getId()][\JTL_SEPARATOR_LENGTH] = [
                'nDezimalstellen'   => 2,
                'cDezimalZeichen'   => ',',
                'cTausenderZeichen' => '.'
            ];
            $rows[$language->getId()][\JTL_SEPARATOR_AMOUNT] = [
                'nDezimalstellen'   => 2,
                'cDezimalZeichen'   => ',',
                'cTausenderZeichen' => '.'
            ];
        }
        if ($unitID <= 0 || $languageID <= 0) {
            return false;
        }
        if (!isset($rows[$languageID][$unitID])) {
            $rows[$languageID]          = [];
            $rows[$languageID][$unitID] = [
                'nDezimalstellen'   => 2,
                'cDezimalZeichen'   => ',',
                'cTausenderZeichen' => '.'
            ];
        }
        $ins                    = new stdClass();
        $ins->kSprache          = $languageID;
        $ins->nEinheit          = $unitID;
        $ins->nDezimalstellen   = $rows[$languageID][$unitID]['nDezimalstellen'];
        $ins->cDezimalZeichen   = $rows[$languageID][$unitID]['cDezimalZeichen'];
        $ins->cTausenderZeichen = $rows[$languageID][$unitID]['cTausenderZeichen'];

        Shop::Container()->getCache()->flushTags([\CACHING_GROUP_CORE]);

        return Shop::Container()->getDB()->insert('ttrennzeichen', $ins);
    }

    /**
     * @return array<int, self>
     */
    public static function getAll(int $languageID): array
    {
        $cacheID = 'units_all_' . $languageID;
        $cache   = Shop::Container()->getCache();
        $db      = Shop::Container()->getDB();
        /** @var array<int, self>|false $all */
        $all = $cache->get($cacheID);
        if ($all === false) {
            $all = [];
            if ($languageID > 0) {
                $data = $db->selectAll(
                    'ttrennzeichen',
                    'kSprache',
                    $languageID,
                    'kTrennzeichen',
                    'nEinheit'
                );
                foreach ($data as $item) {
                    $sep                     = new self((int)$item->kTrennzeichen, $db, $cache);
                    $all[$sep->getEinheit()] = $sep;
                }
            }
            $cache->set($cacheID, $all, [\CACHING_GROUP_CORE]);
        }

        return $all;
    }

    public function save(bool $primary = true): bool|int
    {
        $data = new stdClass();
        foreach (\array_keys(\get_object_vars($this)) as $member) {
            if (\in_array($member, ['db', 'cache'], true)) {
                continue;
            }
            $data->$member = $this->$member;
        }
        unset($data->kTrennzeichen);
        $id = $this->db->insert('ttrennzeichen', $data);
        if ($id > 0) {
            return $primary ? $id : true;
        }

        return false;
    }

    public function update(): int
    {
        $upd                    = new stdClass();
        $upd->kSprache          = $this->kSprache;
        $upd->nEinheit          = $this->nEinheit;
        $upd->nDezimalstellen   = $this->nDezimalstellen;
        $upd->cDezimalZeichen   = $this->cDezimalZeichen;
        $upd->cTausenderZeichen = $this->cTausenderZeichen;

        return $this->db->update('ttrennzeichen', 'kTrennzeichen', $this->kTrennzeichen, $upd);
    }

    public function delete(): int
    {
        return $this->db->delete('ttrennzeichen', 'kTrennzeichen', $this->kTrennzeichen);
    }

    public function setTrennzeichen(int $kTrennzeichen): self
    {
        $this->kTrennzeichen = $kTrennzeichen;

        return $this;
    }

    public function setSprache(int $languageID): self
    {
        $this->kSprache = $languageID;

        return $this;
    }

    public function setEinheit(int $nEinheit): self
    {
        $this->nEinheit = $nEinheit;

        return $this;
    }

    public function setDezimalstellen(int $nDezimalstellen): self
    {
        $this->nDezimalstellen = $nDezimalstellen;

        return $this;
    }

    public function setDezimalZeichen(string $cDezimalZeichen): self
    {
        $this->cDezimalZeichen = $cDezimalZeichen;

        return $this;
    }

    public function setTausenderZeichen(string $cTausenderZeichen): self
    {
        $this->cTausenderZeichen = $cTausenderZeichen;

        return $this;
    }

    public function getTrennzeichen(): int
    {
        return $this->kTrennzeichen;
    }

    public function getSprache(): int
    {
        return $this->kSprache;
    }

    public function getEinheit(): int
    {
        return $this->nEinheit;
    }

    public function getDezimalstellen(): int
    {
        return $this->nDezimalstellen;
    }

    public function getDezimalZeichen(): string
    {
        return \htmlentities($this->cDezimalZeichen);
    }

    public function getTausenderZeichen(): string
    {
        return \htmlentities($this->cTausenderZeichen);
    }
}
