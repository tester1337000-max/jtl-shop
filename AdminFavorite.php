<?php

declare(strict_types=1);

namespace JTL\Backend;

use Exception;
use JTL\DB\DbInterface;
use JTL\Helpers\GeneralObject;
use JTL\Helpers\URL;
use JTL\Shop;

/**
 * Class AdminFavorite
 * @package JTL\Backend
 */
class AdminFavorite
{
    public int $kAdminfav = 0;

    public int $kAdminlogin = 0;

    public string $cTitel = '';

    public string $cUrl = '';

    public int $nSort = 0;

    public function __construct(private readonly DbInterface $db, int $id = 0)
    {
        if ($id > 0) {
            $this->loadFromDB($id);
        }
    }

    public function loadFromDB(int $id): self
    {
        $obj = $this->db->select('tadminfavs', 'kAdminfav', $id);
        if ($obj !== null) {
            $this->kAdminlogin = (int)$obj->kAdminlogin;
            $this->kAdminfav   = (int)$obj->kAdminfav;
            $this->nSort       = (int)$obj->nSort;
            $this->cTitel      = $obj->cTitel;
            $this->cUrl        = $obj->cUrl;
        }
        \executeHook(\HOOK_ATTRIBUT_CLASS_LOADFROMDB);

        return $this;
    }

    public function insertInDB(): int
    {
        $obj = GeneralObject::copyMembers($this);
        unset($obj->kAdminfav);

        return $this->db->insert('tadminfavs', $obj);
    }

    public function updateInDB(): int
    {
        $obj = GeneralObject::copyMembers($this);

        return $this->db->update('tadminfavs', 'kAdminfav', $obj->kAdminfav, $obj);
    }

    /**
     * @return \stdClass[]
     */
    public function fetchAll(int $adminID): array
    {
        try {
            $favs = $this->db->selectAll(
                'tadminfavs',
                'kAdminlogin',
                $adminID,
                'kAdminfav, cTitel, cUrl',
                'nSort ASC'
            );
        } catch (Exception) {
            return [];
        }
        foreach ($favs as $fav) {
            $fav->bExtern = true;
            $fav->cAbsUrl = $fav->cUrl;
            if (!\str_starts_with($fav->cUrl, 'http')) {
                $fav->bExtern = false;
                $fav->cAbsUrl = Shop::getURL() . '/' . $fav->cUrl;
            }
        }

        return $favs;
    }

    public function add(int $id, string $title, string $url, int $sort = -1): bool
    {
        $urlHelper = new URL($url);
        $url       = \str_replace(
            [Shop::getURL(), Shop::getURL(true)],
            '',
            $urlHelper->normalize()
        );

        $url = \strip_tags($url);
        $url = \ltrim($url, '/');
        $url = \filter_var($url, \FILTER_SANITIZE_URL);
        if ($url === false) {
            return false;
        }
        if ($sort < 0) {
            $sort = \count($this->fetchAll($id));
        }
        $item = (object)[
            'kAdminlogin' => $id,
            'cTitel'      => $title,
            'cUrl'        => $url,
            'nSort'       => $sort
        ];
        if ($id > 0 && \mb_strlen($item->cTitel) > 0 && \mb_strlen($item->cUrl) > 0) {
            $exists = $this->db->select(
                'tadminfavs',
                ['kAdminlogin', 'cUrl'],
                [$id, $url]
            );
            if ($exists === null) {
                $this->db->insertRow('tadminfavs', $item);
            }

            return true;
        }

        return false;
    }

    public function remove(int $adminID, int $favID = 0): void
    {
        if ($favID > 0) {
            $this->db->delete('tadminfavs', ['kAdminfav', 'kAdminlogin'], [$favID, $adminID]);
        } else {
            $this->db->delete('tadminfavs', 'kAdminlogin', $adminID);
        }
    }
}
