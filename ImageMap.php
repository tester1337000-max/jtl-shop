<?php

declare(strict_types=1);

namespace JTL;

use JTL\Catalog\Product\Artikel;
use JTL\DB\DbInterface;
use JTL\Helpers\Text;
use JTL\Session\Frontend;
use stdClass;

/**
 * Class ImageMap
 * @package JTL
 */
class ImageMap implements IExtensionPoint
{
    public int $kSprache;

    public int $kKundengruppe;

    public function __construct(private readonly DbInterface $db)
    {
        $this->kSprache      = Shop::getLanguageID();
        $this->kKundengruppe = Frontend::getCustomer()->getGroupID();
    }

    /**
     * @inheritdoc
     */
    public function init(int $id, bool $fetchAll = false): self
    {
        $imageMap = $this->fetch($id, $fetchAll);
        if (\is_object($imageMap)) {
            Shop::Smarty()->assign('oImageMap', $imageMap);
        }

        return $this;
    }

    /**
     * @return stdClass[]
     */
    public function fetchAll(): array
    {
        return \array_map(
            static function (stdClass $im): stdClass {
                $im->kImageMap = (int)$im->kImageMap;
                $im->kKampagne = (int)$im->kKampagne;
                $im->active    = (int)$im->active;

                return $im;
            },
            $this->db->getObjects(
                'SELECT *, IF(
                (CURDATE() >= DATE(vDatum)) AND (
                    bDatum IS NULL 
                    OR CURDATE() <= DATE(bDatum)
                    OR bDatum = 0), 1, 0) AS active 
                FROM timagemap
                ORDER BY cTitel ASC'
            )
        );
    }

    public function fetch(int $id, bool $fetchAll = false, bool $fill = true): bool|stdClass
    {
        $sql = 'SELECT *
                    FROM timagemap
                    WHERE kImageMap = ' . $id;
        if (!$fetchAll) {
            $sql .= ' AND (CURDATE() >= DATE(vDatum)) AND (bDatum IS NULL OR CURDATE() <= DATE(bDatum) OR bDatum = 0)';
        }
        $map = $this->db->getSingleObject($sql);
        if ($map === null) {
            return false;
        }
        $map->oArea_arr = $this->db->selectAll(
            'timagemaparea',
            'kImageMap',
            (int)$map->kImageMap
        );
        $map->kImageMap = (int)$map->kImageMap;
        $map->kKampagne = (int)$map->kKampagne;
        $map->cBildPfad = Shop::getImageBaseURL() . \PFAD_IMAGEMAP . $map->cBildPfad;
        $path           = \parse_url($map->cBildPfad, \PHP_URL_PATH) ?: '';
        $map->cBild     = \mb_substr($path, \mb_strrpos($path, '/') + 1);
        if (!\file_exists(\PFAD_ROOT . \PFAD_IMAGEMAP . $map->cBild)) {
            return $map;
        }
        [$map->fWidth, $map->fHeight] = \getimagesize(\PFAD_ROOT . \PFAD_IMAGEMAP . $map->cBild) ?: [0, 0];
        foreach ($map->oArea_arr as $area) {
            $area->kImageMapArea = (int)$area->kImageMapArea;
            $area->kImageMap     = (int)$area->kImageMap;
            $area->kArtikel      = (int)$area->kArtikel;
            $area->oCoords       = new stdClass();
            $coords              = \explode(',', $area->cCoords);
            if (\count($coords) === 4) {
                $area->oCoords->x = (int)$coords[0];
                $area->oCoords->y = (int)$coords[1];
                $area->oCoords->w = (int)$coords[2];
                $area->oCoords->h = (int)$coords[3];
            }
            $this->addProduct($area, $fill);
            $area->cUrl     = $area->cUrl ?: '';
            $area->cUrlFull = \str_starts_with($area->cUrl, 'http://')
            || \str_starts_with($area->cUrl, 'https://')
                ? $area->cUrl
                : Shop::getURL() . '/' . $area->cUrl;
        }

        return $map;
    }

    private function addProduct(stdClass $area, bool $fill = true): void
    {
        $area->oArtikel = null;
        if ($area->kArtikel <= 0) {
            return;
        }
        $defaultOptions = Artikel::getDefaultOptions();
        $area->oArtikel = new Artikel($this->db);
        if ($fill === true) {
            $area->oArtikel->fuelleArtikel(
                $area->kArtikel,
                $defaultOptions,
                $this->kKundengruppe,
                $this->kSprache
            );
        } else {
            $area->oArtikel->kArtikel = $area->kArtikel;
            $area->oArtikel->cName    = $this->db->select(
                'tartikel',
                'kArtikel',
                $area->kArtikel,
                null,
                null,
                null,
                null,
                false,
                'cName'
            )->cName ?? '';
        }
        if (\mb_strlen($area->cTitel) === 0) {
            $area->cTitel = $area->oArtikel->cName;
        }
        if (\mb_strlen($area->cUrl) === 0) {
            $area->cUrl = $area->oArtikel->cURL;
        }
        if (\mb_strlen($area->cBeschreibung) === 0) {
            $area->cBeschreibung = $area->oArtikel->cKurzBeschreibung;
        }
    }

    public function save(string $title, string $imagePath, ?string $dateFrom, ?string $dateUntil): int
    {
        $ins            = new stdClass();
        $ins->cTitel    = $title;
        $ins->cBildPfad = $imagePath;
        $ins->vDatum    = $dateFrom ?? 'NOW()';
        $ins->bDatum    = $dateUntil ?? '_DBNULL_';

        return $this->db->insert('timagemap', $ins);
    }

    public function update(int $id, string $title, string $imagePath, ?string $dateFrom, ?string $dateUntil): bool
    {
        if (empty($dateFrom)) {
            $dateFrom = 'NOW()';
        }
        if (empty($dateUntil)) {
            $dateUntil = '_DBNULL_';
        }
        $upd            = new stdClass();
        $upd->cTitel    = $title;
        $upd->cBildPfad = $imagePath;
        $upd->vDatum    = $dateFrom;
        $upd->bDatum    = $dateUntil;

        return $this->db->update('timagemap', 'kImageMap', $id, $upd) >= 0;
    }

    public function delete(int $id): bool
    {
        $bannerData = $this->db->selectSingleRow('timagemap', 'kImageMap', $id);
        if (empty($bannerData)) {
            return false;
        }
        \unlink(\PFAD_ROOT . \PFAD_BILDER_BANNER . $bannerData->cBildPfad);
        $resultImagemap = $this->db->delete('timagemap', 'kImageMap', $id);
        $this->db->delete('timagemaparea', 'kImageMap', $id);

        return $resultImagemap >= 1;
    }

    public function saveAreas(stdClass $data): void
    {
        $this->db->delete('timagemaparea', 'kImageMap', (int)$data->kImageMap);
        foreach ($data->oArea_arr as $area) {
            $ins                = new stdClass();
            $ins->kImageMap     = $area->kImageMap;
            $ins->kArtikel      = $area->kArtikel;
            $ins->cStyle        = Text::filterXSS($area->cStyle);
            $ins->cTitel        = Text::filterXSS($area->cTitel);
            $ins->cUrl          = Text::filterXSS($area->cUrl);
            $ins->cBeschreibung = Text::filterXSS($area->cBeschreibung);
            $ins->cCoords       = (int)$area->oCoords->x . ',' .
                (int)$area->oCoords->y . ',' .
                (int)$area->oCoords->w . ',' .
                (int)$area->oCoords->h;

            $this->db->insert('timagemaparea', $ins);
        }
    }
}
