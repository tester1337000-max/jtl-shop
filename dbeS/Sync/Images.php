<?php

declare(strict_types=1);

namespace JTL\dbeS\Sync;

use JTL\dbeS\Starter;
use JTL\Media\Image;
use JTL\Media\Media;
use JTL\Settings\Option\Image as ImageOption;
use JTL\Settings\Section;
use JTL\Settings\Settings;
use stdClass;

use function Functional\map;

/**
 * Class Images
 * @package JTL\dbeS\Sync
 */
final class Images extends AbstractSync
{
    private Settings $settings;

    private string $unzipPath;

    public const XML_INSERT = 'insert';

    public const XML_DELETE = 'delete';

    public const XML_UNKNOWN = 'unknown';

    public function handle(Starter $starter): void
    {
        $this->settings = Settings::fromSection(Section::IMAGE);
        $this->db->query('START TRANSACTION');
        foreach ($starter->getXML() as $item) {
            /**
             * @var string               $file
             * @var array<string, mixed> $xml
             */
            [$file, $xml] = [\key($item), \reset($item)];
            match ($this->getXMLType(\pathinfo($file, \PATHINFO_BASENAME))) {
                self::XML_INSERT => $this->handleInserts($xml, $starter->getUnzipPath()),
                self::XML_DELETE => $this->handleDeletes($xml),
                default          => null
            };
        }
        $this->db->query('COMMIT');
    }

    /**
     * @return self::XML_*
     */
    protected function getXMLType(string $file): string
    {
        $inserts = [
            'bilder_ka.xml',
            'bilder_a.xml',
            'bilder_k.xml',
            'bilder_v.xml',
            'bilder_m.xml',
            'bilder_mw.xml',
            'bilder_h.xml'
        ];
        $deletes = [
            'del_bilder_ka.xml',
            'del_bilder_a.xml',
            'del_bilder_k.xml',
            'del_bilder_v.xml',
            'del_bilder_m.xml',
            'del_bilder_mw.xml',
            'del_bilder_h.xml'
        ];
        if (\in_array($file, $deletes, true)) {
            return self::XML_DELETE;
        }
        if (\in_array($file, $inserts, true)) {
            return self::XML_INSERT;
        }

        return self::XML_UNKNOWN;
    }

    /**
     * @param array<mixed> $xml
     */
    private function handleInserts(array $xml, string $unzipPath): void
    {
        if (!\is_array($xml['bilder'])) {
            return;
        }
        $categoryImages     = $this->mapper->mapArray($xml['bilder'], 'tkategoriepict', 'mKategoriePict');
        $propertyImages     = $this->mapper->mapArray($xml['bilder'], 'teigenschaftwertpict', 'mEigenschaftWertPict');
        $manufacturerImages = $this->mapper->mapArray($xml['bilder'], 'therstellerbild', 'mEigenschaftWertPict');
        $charImages         = $this->mapper->mapArray($xml['bilder'], 'tMerkmalbild', 'mEigenschaftWertPict');
        $charValImages      = $this->mapper->mapArray($xml['bilder'], 'tmerkmalwertbild', 'mEigenschaftWertPict');
        $configGroupImages  = $this->mapper->mapArray($xml['bilder'], 'tkonfiggruppebild', 'mKonfiggruppePict');

        \executeHook(\HOOK_BILDER_XML_BEARBEITE, [
            'Pfad'             => $unzipPath,
            'Kategorie'        => &$categoryImages,
            'Eigenschaftswert' => &$propertyImages,
            'Hersteller'       => &$manufacturerImages,
            'Merkmalwert'      => &$charValImages,
            'Merkmal'          => &$charImages,
            'Konfiggruppe'     => &$configGroupImages
        ]);
        $this->unzipPath = $unzipPath;

        $this->handleCategoryImages($categoryImages);
        $this->handlePropertyImages($propertyImages);
        $this->handleManufacturerImages($manufacturerImages);
        $this->handleCharacteristicImages($charImages);
        $this->handleCharacteristicValueImages($charValImages);
        $this->handleConfigGroupImages($configGroupImages);
        if (\count($charImages) > 0 || \count($charValImages) > 0) {
            $this->cache->flushTags([\CACHING_GROUP_ATTRIBUTE, \CACHING_GROUP_FILTER_CHARACTERISTIC]);
        }

        \executeHook(\HOOK_BILDER_XML_BEARBEITE_ENDE, [
            'Kategorie'        => &$categoryImages,
            'Eigenschaftswert' => &$propertyImages,
            'Hersteller'       => &$manufacturerImages,
            'Merkmalwert'      => &$charValImages,
            'Merkmal'          => &$charImages,
            'Konfiggruppe'     => &$configGroupImages
        ]);
    }

    /**
     * @param stdClass[] $images
     */
    private function handleConfigGroupImages(array $images): void
    {
        $flushIDs = [];
        foreach ($images as $image) {
            if (empty($image->cPfad) || empty($image->kKonfiggruppe)) {
                continue;
            }
            $item                = new stdClass();
            $item->cBildPfad     = $image->cPfad;
            $item->kKonfiggruppe = (int)$image->kKonfiggruppe;
            $original            = $this->unzipPath . $item->cBildPfad;
            $extension           = $this->getExtension($original);
            $flushIDs[]          = $item->kKonfiggruppe;
            if (!$extension) {
                $this->logger->error(
                    'Bildformat des Konfiggruppenbildes konnte nicht ermittelt werden. Datei {ori} keine Bilddatei?',
                    ['ori' => $original]
                );
                continue;
            }
            $item->cBildPfad = $this->getNewFilename($item->kKonfiggruppe . '.' . $extension);
            \copy($original, \PFAD_ROOT . \STORAGE_CONFIGGROUPS . $item->cBildPfad);
            $this->db->update(
                'tkonfiggruppe',
                'kKonfiggruppe',
                $item->kKonfiggruppe,
                (object)['cBildPfad' => $item->cBildPfad]
            );
            \unlink($original);
        }
        $this->clearImageCache(Image::TYPE_CONFIGGROUP, $flushIDs);
    }

    /**
     * @param stdClass[] $images
     */
    private function handleCharacteristicValueImages(array $images): void
    {
        $flushIDs = [];
        foreach ($images as $image) {
            $image->kMerkmalWert = (int)$image->kMerkmalWert;
            if (empty($image->cPfad) || $image->kMerkmalWert <= 0) {
                continue;
            }
            $original   = $this->unzipPath . $image->cPfad;
            $extension  = $this->getExtension($original);
            $flushIDs[] = $image->kMerkmalWert;
            if (!$extension) {
                $this->logger->error(
                    'Bildformat des Merkmalwertbildes konnte nicht ermittelt werden. Datei {ori} keine Bilddatei?',
                    ['ori' => $original]
                );
                continue;
            }
            $image->cPfad = $this->getCharacteristicValueImageName($image, $extension);
            \copy($original, \PFAD_ROOT . \STORAGE_CHARACTERISTIC_VALUES . $image->cPfad);
            $this->db->update(
                'tmerkmalwert',
                'kMerkmalWert',
                $image->kMerkmalWert,
                (object)['cBildpfad' => $image->cPfad]
            );
            $charValImage               = new stdClass();
            $charValImage->kMerkmalWert = $image->kMerkmalWert;
            $charValImage->cBildpfad    = $image->cPfad;
            $this->upsert('tmerkmalwertbild', [$charValImage], 'kMerkmalWert');
            \unlink($original);
        }
        $this->clearImageCache(Image::TYPE_CHARACTERISTIC_VALUE, $flushIDs);
    }

    /**
     * @param stdClass[] $images
     */
    private function handleCharacteristicImages(array $images): void
    {
        $flushIDs = [];
        foreach ($images as $image) {
            if (empty($image->cPfad) || empty($image->kMerkmal)) {
                continue;
            }
            $image->kMerkmal = (int)$image->kMerkmal;
            $original        = $this->unzipPath . $image->cPfad;
            $extension       = $this->getExtension($original);
            $flushIDs[]      = $image->kMerkmal;
            if (!$extension) {
                $this->logger->error(
                    'Bildformat des Merkmalbildes konnte nicht ermittelt werden. Datei {ori} keine Bilddatei?',
                    ['ori' => $original]
                );
                continue;
            }
            $image->cPfad = $this->getCharacteristicImageName($image, $extension);
            \copy($original, \PFAD_ROOT . \STORAGE_CHARACTERISTICS . $image->cPfad);
            $this->db->update(
                'tmerkmal',
                'kMerkmal',
                $image->kMerkmal,
                (object)['cBildpfad' => $image->cPfad]
            );
            \unlink($original);
        }
        $this->clearImageCache(Image::TYPE_CHARACTERISTIC, $flushIDs);
    }

    /**
     * @param stdClass[] $images
     */
    private function handleManufacturerImages(array $images): void
    {
        $flushIDs = [];
        foreach ($images as $image) {
            if (empty($image->cPfad) || empty($image->kHersteller)) {
                continue;
            }
            $image->kHersteller = (int)$image->kHersteller;
            $original           = $this->unzipPath . $image->cPfad;
            $extension          = $this->getExtension($original);
            $flushIDs[]         = $image->kHersteller;
            if (!$extension) {
                $this->logger->error(
                    'Bildformat des Herstellerbildes konnte nicht ermittelt werden. Datei {ori} keine Bilddatei?',
                    ['ori' => $original]
                );
                continue;
            }
            $image->cPfad = $this->getManufacturerImageName($image, $extension);
            \copy($original, \PFAD_ROOT . \STORAGE_MANUFACTURERS . $image->cPfad);
            $this->db->update(
                'thersteller',
                'kHersteller',
                $image->kHersteller,
                (object)['cBildpfad' => $image->cPfad]
            );
            \unlink($original);
        }
        if (\count($flushIDs) === 0) {
            return;
        }
        $affectedProducts = $this->db->getInts(
            'SELECT kArtikel
                FROM tartikel
                WHERE kHersteller IN (' . \implode(',', $flushIDs) . ')',
            'kArtikel'
        );
        $this->cache->flushTags(map($affectedProducts, fn(int $pid): string => \CACHING_GROUP_ARTICLE . '_' . $pid));
        $this->cache->flushTags(map($flushIDs, fn(int $mid): string => \CACHING_GROUP_MANUFACTURER . '_' . $mid));
        $this->clearImageCache(Image::TYPE_MANUFACTURER, $flushIDs);
    }

    /**
     * @param stdClass[] $images
     */
    private function handlePropertyImages(array $images): void
    {
        $flushIDs = [];
        foreach ($images as $image) {
            if (empty($image->cPfad)) {
                continue;
            }
            $image->kEigenschaftWert = (int)($image->kEigenschaftWert ?? 0);
            $flushIDs[]              = $image->kEigenschaftWert;
            $original                = $this->unzipPath . $image->cPfad;
            $extension               = $this->getExtension($original);
            if (!$extension) {
                $this->logger->error(
                    'Bildformat des Eigenschaftwertbildes konnte nicht ermittelt werden. Datei {ori} keine Bilddatei?',
                    ['ori' => $original]
                );
                continue;
            }
            $image->cPfad = $this->getPropertiesImageName($image, $extension);
            $image->cPfad = $this->getNewFilename($image->cPfad);
            \copy($original, \PFAD_ROOT . \STORAGE_VARIATIONS . $image->cPfad);
            $this->upsert('teigenschaftwertpict', [$image], 'kEigenschaftWert');
            \unlink($original);
        }
        $this->clearImageCache(Image::TYPE_VARIATION, $flushIDs);
        if (\count($flushIDs) === 0) {
            return;
        }
        $affectedProducts = $this->db->getInts(
            'SELECT DISTINCT teigenschaft.kArtikel
                FROM teigenschaft
                JOIN teigenschaftwert 
                ON teigenschaftwert.kEigenschaft = teigenschaft.kEigenschaft
                JOIN teigenschaftwertpict 
                ON teigenschaftwert.kEigenschaftWert = teigenschaftwertpict.kEigenschaftWert
                WHERE teigenschaftwertpict.kEigenschaftWert IN (' . \implode(',', $flushIDs) . ')',
            'kArtikel'
        );
        $this->cache->flushTags(map($affectedProducts, fn(int $pid): string => \CACHING_GROUP_ARTICLE . '_' . $pid));
    }

    /**
     * @param stdClass[] $images
     */
    private function handleCategoryImages(array $images): void
    {
        $flushIDs = [];
        foreach ($images as $image) {
            if (empty($image->cPfad)) {
                continue;
            }
            $flushIDs[] = (int)$image->kKategorie;
            $original   = $this->unzipPath . $image->cPfad;
            $extension  = $this->getExtension($original);
            if (!$extension) {
                $this->logger->error(
                    'Bildformat des Kategoriebildes konnte nicht ermittelt werden. Datei {ori} keine Bilddatei?',
                    ['ori' => $original]
                );
                continue;
            }
            $existing     = $this->db->getSingleObject(
                'SELECT * 
                    FROM tkategoriepict
                    WHERE kKategoriePict = :iid
                        AND kKategorie = :cid',
                ['iid' => $image->kKategoriePict, 'cid' => $image->kKategorie]
            );
            $image->cPfad = $this->getCategoryImageName($image, $extension);
            if ($existing !== null && $image->cPfad !== $existing->cPfad) {
                $storage = \PFAD_ROOT . \STORAGE_CATEGORIES . \basename($existing->cPfad);
                if (\file_exists($storage)) {
                    @\unlink($storage);
                }
            }
            \copy($original, \PFAD_ROOT . \STORAGE_CATEGORIES . $image->cPfad);
            $this->upsert('tkategoriepict', [$image], 'kKategorie');
            \unlink($original);
        }
        $this->clearImageCache(Image::TYPE_CATEGORY, $flushIDs);
    }

    private function getPropertiesImageName(stdClass $image, string $extension): string
    {
        if (empty($image->kEigenschaftWert) || !$this->settings->int(ImageOption::VARIATION_NAMES)) {
            return (\stripos(\strrev($image->cPfad), \strrev($extension)) === 0)
                ? $image->cPfad
                : $image->cPfad . '.' . $extension;
        }
        $propValue = $this->db->getSingleObject(
            'SELECT kEigenschaftWert, cArtNr, cName, kEigenschaft
                FROM teigenschaftwert
                WHERE kEigenschaftWert = :aid',
            ['aid' => $image->kEigenschaftWert]
        );
        if ($propValue === null) {
            $this->logger->warning(
                'Eigenschaftswertbild fuer nicht existierenden Eigenschaftswert {id}',
                ['id' => $image->kEigenschaftWert]
            );
            return $image->cPfad;
        }
        $imageName = $propValue->kEigenschaftWert;
        if ($propValue->cName) {
            $imageName = $this->getImageName($propValue, $image);
        }

        return $this->removeSpecialChars($imageName) . '.' . $extension;
    }

    private function getCategoryImageName(stdClass $image, string $ext): string
    {
        $imageName = $image->cPfad;
        if (empty($image->kKategorie) || !$this->settings->raw(ImageOption::CATEGORY_NAMES)) {
            return $this->getNewFilename((\pathinfo($imageName, \PATHINFO_FILENAME)) . '.' . $ext);
        }
        $data = $this->db->getSingleObject(
            "SELECT tseo.cSeo, tkategorie.cName
                FROM tkategorie
                JOIN tseo
                    ON tseo.cKey = 'kKategorie'
                    AND tseo.kKey = tkategorie.kKategorie
                JOIN tsprache
                    ON tsprache.kSprache = tseo.kSprache
                WHERE tkategorie.kKategorie = :cid
                    AND tsprache.cShopStandard = 'Y'",
            ['cid' => (int)$image->kKategorie]
        );
        if ($data !== null && !empty($data->cName) && $this->settings->int(ImageOption::CATEGORY_NAMES) === 1) {
            $imageName = $this->removeSpecialChars($data->cSeo ?: $this->convertUmlauts($data->cName)) . '.' . $ext;
        } else {
            $imageName = \pathinfo($image->cPfad, \PATHINFO_FILENAME) . '.' . $ext;
        }

        return $this->getNewFilename($imageName);
    }

    private function getManufacturerImageName(stdClass $image, string $ext): string
    {
        $data = $this->db->getSingleObject(
            'SELECT cName, cSeo
                FROM thersteller
                WHERE kHersteller = :mid',
            ['mid' => $image->kHersteller]
        );
        if ($data !== null && !empty($data->cSeo) && $this->settings->int(ImageOption::MANUFACTURER_NAMES) === 1) {
            $imageName = $this->removeSpecialChars($data->cSeo) . '.' . $ext;
        } else {
            $imageName = \pathinfo($image->cPfad, \PATHINFO_FILENAME) . '.' . $ext;
        }

        return $this->getNewFilename($imageName);
    }

    private function getCharacteristicValueImageName(stdClass $image, string $ext): string
    {
        $conf = $this->settings->int(ImageOption::CHARACTERISTIC_VALUE_NAMES);
        if ($conf === 2) {
            $imageName = $image->cPfad . '.' . $ext;
        } else {
            $data = $this->db->getSingleObject(
                'SELECT tmerkmalwertsprache.cSeo, tmerkmalwertsprache.cWert
                    FROM tmerkmalwertsprache
                    JOIN tsprache
                        ON tsprache.kSprache = tmerkmalwertsprache.kSprache
                    WHERE kMerkmalWert = :cid
                        AND tsprache.cShopStandard = \'Y\'',
                ['cid' => $image->kMerkmalWert]
            );
            if ($data !== null && !empty($data->cSeo) && $conf === 1) {
                $imageName = $this->removeSpecialChars($data->cSeo) . '.' . $ext;
            } else {
                $imageName = \pathinfo($image->cPfad, \PATHINFO_FILENAME) . '.' . $ext;
            }
        }

        return $this->getNewFilename($imageName);
    }

    private function getCharacteristicImageName(stdClass $image, string $ext): string
    {
        $conf = $this->settings->int(ImageOption::CHARACTERISTIC_NAMES);
        if ($conf === 2) {
            $imageName = $image->cPfad . '.' . $ext;
        } else {
            $data = $this->db->getSingleObject(
                'SELECT cName
                    FROM tmerkmal
                    WHERE kMerkmal = :cid',
                ['cid' => $image->kMerkmal]
            );
            if ($data !== null && !empty($data->cName) && $conf === 1) {
                $imageName = $this->removeSpecialChars($this->convertUmlauts($data->cName)) . '.' . $ext;
            } else {
                $imageName = \pathinfo($image->cPfad, \PATHINFO_FILENAME) . '.' . $ext;
            }
        }

        return $this->getNewFilename($imageName);
    }

    private function convertUmlauts(string $str): string
    {
        $src = ['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü'];
        $rpl = ['ae', 'oe', 'ue', 'ss', 'AE', 'OE', 'UE'];

        return \str_replace($src, $rpl, $str);
    }

    private function removeSpecialChars(string $str): string
    {
        $str = \str_replace(['/', ' '], '-', $str);

        return \preg_replace('/[^a-zA-Z\d.\-_]/', '', $str) ?? $str;
    }

    /**
     * @param array<mixed> $xml
     */
    private function handleDeletes(array $xml): void
    {
        \executeHook(\HOOK_BILDER_XML_BEARBEITEDELETES, [
            'Kategorie'        => $xml['del_bilder']['kKategoriePict'] ?? [],
            'KategoriePK'      => $xml['del_bilder']['kKategorie'] ?? [],
            'Eigenschaftswert' => $xml['del_bilder']['kEigenschaftWertPict'] ?? [],
            'Hersteller'       => $xml['del_bilder']['kHersteller'] ?? [],
            'Merkmal'          => $xml['del_bilder']['kMerkmal'] ?? [],
            'Merkmalwert'      => $xml['del_bilder']['kMerkmalWert'] ?? [],
        ]);
        // Kategoriebilder löschen Wawi > .99923
        $this->deleteCategoryImages($xml);
        // Variationsbilder löschen Wawi > .99923
        $this->deleteVariationImages($xml);
        // Herstellerbilder löschen
        $this->deleteManufacturerImages($xml);
        // Merkmalbilder löschen
        $this->deleteCharacteristicImages($xml);
        // Merkmalwertbilder löschen
        $this->deleteCharacteristicValueImages($xml);
    }

    /**
     * @param array<mixed> $xml
     */
    private function deleteVariationImages(array $xml): void
    {
        $source = $xml['del_bilder']['kEigenschaftWert'] ?? [];
        if (\is_numeric($source)) {
            $source = [$source];
        }
        $ids = \array_filter(\array_map('\intval', $source));
        if (\count($ids) === 0) {
            return;
        }
        $oldImages = $this->db->getObjects(
            'SELECT cPfad AS path
                FROM teigenschaftwertpict
                WHERE kEigenschaftWert IN (' . \implode(',', $ids) . ')'
        );
        $this->db->query(
            'DELETE 
                FROM teigenschaftwertpict 
                WHERE kEigenschaftWert IN (' . \implode(',', $ids) . ')'
        );
        foreach ($oldImages as $image) {
            $storage = \PFAD_ROOT . \STORAGE_VARIATIONS . \basename($image->path);
            if (\file_exists($storage)) {
                @\unlink($storage);
            }
        }
        $this->clearImageCache(Image::TYPE_VARIATION, $ids);
    }

    /**
     * @param array<mixed> $xml
     */
    private function deleteCategoryImages(array $xml): void
    {
        $source = $xml['del_bilder']['kKategorie'] ?? [];
        if (\is_numeric($source)) {
            $source = [$source];
        }
        $ids = \array_filter(\array_map('\intval', $source));
        foreach ($ids as $id) {
            $this->db->delete('tkategoriepict', 'kKategorie', $id);
        }
        $this->clearImageCache(Image::TYPE_CATEGORY, $ids);
    }

    /**
     * @param array<mixed> $xml
     */
    private function deleteManufacturerImages(array $xml): void
    {
        $source = $xml['del_bilder']['kHersteller'] ?? [];
        if (\is_numeric($source)) {
            $source = [$source];
        }
        $ids = \array_filter(\array_map('\intval', $source));
        if (\count($ids) === 0) {
            return;
        }
        $oldImages = $this->db->getObjects(
            'SELECT cBildpfad AS path
                FROM thersteller
                WHERE kHersteller IN (' . \implode(',', $ids) . ')',
        );
        $this->db->executeQuery(
            'UPDATE thersteller 
                SET cBildpfad = \'\' 
                WHERE kHersteller IN (' . \implode(',', $ids) . ')'
        );
        $affectedProducts = $this->db->getInts(
            'SELECT kArtikel
                FROM tartikel
                WHERE kHersteller IN (' . \implode(',', $ids) . ')',
            'kArtikel'
        );
        $cacheTags        = map($affectedProducts, fn(int $pid): string => \CACHING_GROUP_ARTICLE . '_' . $pid);
        foreach ($oldImages as $image) {
            $storage = \PFAD_ROOT . \STORAGE_MANUFACTURERS . \basename($image->path);
            if (\file_exists($storage)) {
                @\unlink($storage);
            }
        }
        $this->cache->flushTags($cacheTags);
        $this->clearImageCache(Image::TYPE_MANUFACTURER, $ids);
    }

    /**
     * @param array<mixed> $xml
     */
    private function deleteCharacteristicImages(array $xml): void
    {
        $source = $xml['del_bilder']['kMerkmal'] ?? [];
        if (\is_numeric($source)) {
            $source = [$source];
        }
        $ids = \array_filter(\array_map('\intval', $source));
        if (\count($ids) === 0) {
            return;
        }
        $oldImages = $this->db->getObjects(
            'SELECT cBildpfad AS path 
                FROM tmerkmal 
                WHERE kMerkmal IN (' . \implode(',', $ids) . ')'
        );
        $this->db->query(
            'UPDATE tmerkmal 
                SET cBildpfad = \'\' 
                WHERE kMerkmal IN (' . \implode(',', $ids) . ')'
        );
        foreach ($oldImages as $image) {
            $storage = \PFAD_ROOT . \STORAGE_CHARACTERISTICS . \basename($image->path);
            if (\file_exists($storage)) {
                @\unlink($storage);
            }
        }
        $this->clearImageCache(Image::TYPE_CHARACTERISTIC, $ids);
    }

    /**
     * @param array<mixed> $xml
     */
    private function deleteCharacteristicValueImages(array $xml): void
    {
        $source = $xml['del_bilder']['kMerkmalWert'] ?? [];
        if (\is_numeric($source)) {
            $source = [$source];
        }
        $ids = \array_filter(\array_map('\intval', $source));
        if (\count($ids) === 0) {
            return;
        }
        $oldPaths = $this->db->getObjects(
            'SELECT cBildpfad AS path 
                FROM tmerkmalwert 
                WHERE kMerkmalWert IN (' . \implode(',', $ids) . ')'
        );
        $this->db->query(
            'UPDATE tmerkmalwert 
                SET cBildpfad = \'\' 
                WHERE kMerkmalWert IN (' . \implode(',', $ids) . ')'
        );
        $this->db->query(
            'DELETE 
                FROM tmerkmalwertbild 
                WHERE kMerkmalWert IN (' . \implode(',', $ids) . ')'
        );
        foreach ($oldPaths as $path) {
            $storage = \PFAD_ROOT . \STORAGE_CHARACTERISTIC_VALUES . \basename($path->path);
            if (\file_exists($storage)) {
                @\unlink($storage);
            }
        }
        $this->clearImageCache(Image::TYPE_CHARACTERISTIC_VALUE, $ids);
    }

    private function getExtension(string $filename): ?string
    {
        if (!\file_exists($filename)) {
            return null;
        }
        $size = \getimagesize($filename);

        return match ($size[2] ?? 0) {
            \IMAGETYPE_JPEG => 'jpg',
            \IMAGETYPE_PNG  => \function_exists('imagecreatefrompng') ? 'png' : null,
            \IMAGETYPE_GIF  => \function_exists('imagecreatefromgif') ? 'gif' : null,
            \IMAGETYPE_BMP  => \function_exists('imagecreatefromwbmp') ? 'bmp' : null,
            default         => null,
        };
    }

    private function getNewExtension(?string $sourcePath = null): string
    {
        $config = \mb_convert_case($this->settings->string(ImageOption::IMAGE_FORMAT), \MB_CASE_LOWER);
        if (!\in_array($config, ['auto', 'auto_webp', 'auto_avif'], true)) {
            return $config;
        }

        return \pathinfo($sourcePath ?? '', \PATHINFO_EXTENSION) ?: 'jpg';
    }

    private function getNewFilename(string $path): string
    {
        return \pathinfo($path, \PATHINFO_FILENAME) . '.' . $this->getNewExtension($path);
    }

    /**
     * @param int[] $ids
     */
    private function clearImageCache(string $class, array $ids): bool
    {
        if (\count($ids) === 0) {
            return false;
        }

        return (Media::getClass($class))::clearCache($ids);
    }

    public function getImageName(stdClass $propValue, stdClass $image): string
    {
        $imageName = $propValue->kEigenschaftWert;
        switch ($this->settings->int(ImageOption::VARIATION_NAMES)) {
            case 1:
                if (!empty($propValue->cArtNr)) {
                    $imageName = 'var' . $this->convertUmlauts($propValue->cArtNr);
                }
                break;

            case 2:
                $product = $this->db->getSingleObject(
                    "SELECT tartikel.cArtNr, tartikel.cBarcode, tartikel.cName, tseo.cSeo
                            FROM teigenschaftwert, teigenschaft, tartikel
                            JOIN tseo
                                ON tseo.cKey = 'kArtikel'
                                AND tseo.kKey = tartikel.kArtikel
                            JOIN tsprache
                                ON tsprache.kSprache = tseo.kSprache
                            WHERE teigenschaftwert.kEigenschaft = teigenschaft.kEigenschaft
                                AND tsprache.cShopStandard = 'Y'
                                AND teigenschaft.kArtikel = tartikel.kArtikel
                                AND teigenschaftwert.kEigenschaftWert = :cid",
                    ['cid' => $image->kEigenschaftWert]
                );
                if ($product !== null && !empty($product->cArtNr) && !empty($propValue->cArtNr)) {
                    $imageName = $this->convertUmlauts($product->cArtNr) .
                        '_' .
                        $this->convertUmlauts($propValue->cArtNr);
                }
                break;

            case 3:
                $product = $this->db->getSingleObject(
                    "SELECT tartikel.cArtNr, tartikel.cBarcode, tartikel.cName, tseo.cSeo
                            FROM teigenschaftwert, teigenschaft, tartikel
                            JOIN tseo
                                ON tseo.cKey = 'kArtikel'
                                AND tseo.kKey = tartikel.kArtikel
                            JOIN tsprache
                                ON tsprache.kSprache = tseo.kSprache
                            WHERE teigenschaftwert.kEigenschaft = teigenschaft.kEigenschaft
                                AND tsprache.cShopStandard = 'Y'
                                AND teigenschaft.kArtikel = tartikel.kArtikel
                                AND teigenschaftwert.kEigenschaftWert = :cid",
                    ['cid' => $image->kEigenschaftWert]
                );

                $attribute = $this->db->getSingleObject(
                    'SELECT cName FROM teigenschaft WHERE kEigenschaft = :aid',
                    ['aid' => $propValue->kEigenschaft]
                );
                if (
                    $attribute !== null
                    && (!empty($product->cSeo) || !empty($product->cName))
                    && !empty($attribute->cName)
                    && !empty($propValue->cName)
                ) {
                    if ($product->cSeo) {
                        $imageName = $product->cSeo . '_' .
                            $this->convertUmlauts($attribute->cName) . '_' .
                            $this->convertUmlauts($propValue->cName);
                    } else {
                        $imageName = $this->convertUmlauts($product->cName) . '_' .
                            $this->convertUmlauts($attribute->cName) . '_' .
                            $this->convertUmlauts($propValue->cName);
                    }
                }
                break;
            default:
                break;
        }

        return $imageName;
    }
}
