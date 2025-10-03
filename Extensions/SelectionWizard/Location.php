<?php

declare(strict_types=1);

namespace JTL\Extensions\SelectionWizard;

use JTL\Catalog\Category\Kategorie;
use JTL\DB\DbInterface;
use JTL\Helpers\GeneralObject;
use JTL\Shop;
use stdClass;

/**
 * Class Location
 * @package JTL\Extensions\SelectionWizard
 */
class Location
{
    public int $kAuswahlAssistentOrt;

    public int $kAuswahlAssistentGruppe;

    public string $cKey;

    public int $kKey;

    /**
     * @var Location[]
     */
    public array $oOrt_arr;

    public ?string $cOrt = null;

    private DbInterface $db;

    public function __construct(int $locationID = 0, int $groupID = 0, bool $backend = false)
    {
        $this->db = Shop::Container()->getDB();
        if ($locationID > 0 || $groupID > 0) {
            $this->loadFromDB($locationID, $groupID, $backend);
        }
    }

    private function loadFromDB(int $locationID, int $groupID, bool $backend): void
    {
        if ($groupID > 0) {
            $this->oOrt_arr = [];
            $locationData   = $this->db->selectAll(
                'tauswahlassistentort',
                'kAuswahlAssistentGruppe',
                $groupID
            );
            foreach ($locationData as $loc) {
                $this->oOrt_arr[] = new self((int)$loc->kAuswahlAssistentOrt, 0, $backend);
            }

            return;
        }
        $location = $this->db->select(
            'tauswahlassistentort',
            'kAuswahlAssistentOrt',
            $locationID
        );
        if ($location === null) {
            return;
        }
        $this->kAuswahlAssistentGruppe = (int)$location->kAuswahlAssistentGruppe;
        $this->kAuswahlAssistentOrt    = (int)$location->kAuswahlAssistentOrt;
        $this->kKey                    = (int)$location->kKey;
        $this->cKey                    = $location->cKey;
        switch ($this->cKey) {
            case \AUSWAHLASSISTENT_ORT_KATEGORIE:
                $langID   = $this->getLanguage($this->kAuswahlAssistentGruppe);
                $category = new Kategorie($this->kKey, $langID, 0, false, $this->db);

                $this->cOrt = $category->getName($langID) . ' (' . \__('category') . ')';
                break;

            case \AUSWAHLASSISTENT_ORT_LINK:
                $language   = $this->db->select(
                    'tsprache',
                    'kSprache',
                    $this->getLanguage($this->kAuswahlAssistentGruppe)
                );
                $link       = $this->db->select(
                    'tlinksprache',
                    'kLink',
                    $this->kKey,
                    'cISOSprache',
                    $language->cISO ?? '',
                    null,
                    null,
                    false,
                    'cName'
                );
                $this->cOrt = $link !== null && isset($link->cName) ? ($link->cName . ' (CMS)') : null;
                break;

            case \AUSWAHLASSISTENT_ORT_STARTSEITE:
                $this->cOrt = 'Startseite';
                break;
        }
    }

    private function getLanguage(int $groupID): int
    {
        return \max(
            0,
            $this->db->getSingleInt(
                'SELECT kSprache
                    FROM tauswahlassistentgruppe
                    WHERE kAuswahlAssistentGruppe = :groupID',
                'kSprache',
                ['groupID' => $groupID]
            )
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    public function saveLocation(array $params, int $groupID): bool
    {
        if ($groupID <= 0 || \count($params) === 0) {
            return false;
        }
        if (isset($params['cKategorie']) && \mb_strlen($params['cKategorie']) > 0) {
            foreach (\explode(';', $params['cKategorie']) as $key) {
                $key = (int)$key;
                if ($key > 0 && \mb_strlen((string)$key) > 0) {
                    $ins                          = new stdClass();
                    $ins->kAuswahlAssistentGruppe = $groupID;
                    $ins->cKey                    = \AUSWAHLASSISTENT_ORT_KATEGORIE;
                    $ins->kKey                    = $key;

                    $this->db->insert('tauswahlassistentort', $ins);
                }
            }
        }
        if (GeneralObject::hasCount('kLink_arr', $params)) {
            foreach ($params['kLink_arr'] as $key) {
                $key = (int)$key;
                if ($key > 0) {
                    $ins                          = new stdClass();
                    $ins->kAuswahlAssistentGruppe = $groupID;
                    $ins->cKey                    = \AUSWAHLASSISTENT_ORT_LINK;
                    $ins->kKey                    = $key;

                    $this->db->insert('tauswahlassistentort', $ins);
                }
            }
        }
        if (isset($params['nStartseite']) && (int)$params['nStartseite'] === 1) {
            $ins                          = new stdClass();
            $ins->kAuswahlAssistentGruppe = $groupID;
            $ins->cKey                    = \AUSWAHLASSISTENT_ORT_STARTSEITE;
            $ins->kKey                    = 1;

            $this->db->insert('tauswahlassistentort', $ins);
        }

        return false;
    }

    /**
     * @param array<string, string> $params
     */
    public function updateLocation(array $params, int $groupID): bool
    {
        $rows = 0;
        if ($groupID > 0 && \count($params) > 0) {
            $rows = $this->db->delete(
                'tauswahlassistentort',
                'kAuswahlAssistentGruppe',
                $groupID
            );
        }

        return $rows >= 0 && $this->saveLocation($params, $groupID);
    }

    /**
     * @param array<string, mixed> $params
     * @param bool                 $update
     * @return array<string, int>
     */
    public function checkLocation(array $params, bool $update = false): array
    {
        $checks = [];
        // Ort
        if (
            (!isset($params['cKategorie']) || \mb_strlen($params['cKategorie']) === 0)
            && (!isset($params['kLink_arr'])
                || !\is_array($params['kLink_arr'])
                || \count($params['kLink_arr']) === 0)
            && (int)$params['nStartseite'] === 0
        ) {
            $checks['cOrt'] = 1;
        }
        $langID  = (int)($params['kSprache'] ?? 0);
        $groupID = (int)($params['kAuswahlAssistentGruppe'] ?? 0);
        // Ort Kategorie
        if (isset($params['cKategorie']) && \mb_strlen($params['cKategorie']) > 0) {
            $categories = \explode(';', $params['cKategorie']);
            if (\count($categories) === 0) {
                $checks['cKategorie'] = 1;
            }
            if (!\is_numeric($categories[0])) {
                $checks['cKategorie'] = 2;
            }
            foreach ($categories as $key) {
                $key = (int)$key;
                if ($key > 0) {
                    if ($update) {
                        if ($this->isCategoryTaken($key, $langID, $groupID)) {
                            $checks['cKategorie'] = 3;
                        }
                    } elseif ($this->isCategoryTaken($key, $langID)) {
                        $checks['cKategorie'] = 3;
                    }
                }
            }
        }
        // Ort Spezialseite
        if (GeneralObject::hasCount('kLink_arr', $params)) {
            foreach ($params['kLink_arr'] as $key) {
                $key = (int)$key;
                if ($key <= 0) {
                    continue;
                }
                if ($update) {
                    if ($this->isLinkTaken($key, $langID, $groupID)) {
                        $checks['kLink_arr'] = 1;
                    }
                } elseif ($this->isLinkTaken($key, $langID)) {
                    $checks['kLink_arr'] = 1;
                }
            }
        }
        // Ort Startseite
        if (isset($params['nStartseite']) && (int)$params['nStartseite'] === 1) {
            if ($update) {
                if ($this->isStartPageTaken($langID, $groupID)) {
                    $checks['nStartseite'] = 1;
                }
            } elseif ($this->isStartPageTaken($langID)) {
                $checks['nStartseite'] = 1;
            }
        }

        return $checks;
    }

    public function isCategoryTaken(int $categoryID, int $languageID, int $groupID = 0): bool
    {
        if ($categoryID === 0 || $languageID === 0) {
            return false;
        }
        $locationSQL = $groupID > 0
            ? ' AND o.kAuswahlAssistentGruppe != ' . $groupID
            : '';
        $item        = $this->db->getSingleObject(
            'SELECT kAuswahlAssistentOrt
                FROM tauswahlassistentort AS o
                JOIN tauswahlassistentgruppe AS g
                    ON g.kAuswahlAssistentGruppe = o.kAuswahlAssistentGruppe
                    AND g.kSprache = :langID
                WHERE o.cKey = :keyID' . $locationSQL . '
                    AND o.kKey = :catID',
            [
                'keyID'  => \AUSWAHLASSISTENT_ORT_KATEGORIE,
                'catID'  => $categoryID,
                'langID' => $languageID
            ]
        );

        return ($item->kAuswahlAssistentOrt ?? 0) > 0;
    }

    public function isLinkTaken(int $linkID, int $languageID, int $groupID = 0): bool
    {
        if ($linkID === 0 || $languageID === 0) {
            return false;
        }
        $condSQL = $groupID > 0
            ? ' AND o.kAuswahlAssistentGruppe != ' . $groupID
            : '';
        $data    = $this->db->getSingleObject(
            'SELECT kAuswahlAssistentOrt
                FROM tauswahlassistentort AS o
                JOIN tauswahlassistentgruppe AS g
                    ON g.kAuswahlAssistentGruppe = o.kAuswahlAssistentGruppe
                    AND g.kSprache = :langID
                WHERE o.cKey = :keyID' . $condSQL . '
                    AND o.kKey = :linkID',
            [
                'langID' => $languageID,
                'keyID'  => \AUSWAHLASSISTENT_ORT_LINK,
                'linkID' => $linkID
            ]
        );

        return ($data->kAuswahlAssistentOrt ?? 0) > 0;
    }

    public function isStartPageTaken(int $languageID, int $groupID = 0): bool
    {
        if ($languageID === 0) {
            return false;
        }
        $locationSQL = $groupID > 0
            ? ' AND o.kAuswahlAssistentGruppe != ' . $groupID
            : '';
        $item        = $this->db->getSingleObject(
            'SELECT kAuswahlAssistentOrt
                FROM tauswahlassistentort AS o
                JOIN tauswahlassistentgruppe AS g
                    ON g.kAuswahlAssistentGruppe = o.kAuswahlAssistentGruppe
                    AND g.kSprache = :langID
                WHERE o.cKey = :keyID' . $locationSQL . '
                    AND o.kKey = 1',
            ['langID' => $languageID, 'keyID' => \AUSWAHLASSISTENT_ORT_STARTSEITE]
        );

        return ($item->kAuswahlAssistentOrt ?? 0) > 0;
    }

    public function getLocation(string $keyName, int $id, int $languageID, bool $backend = false): ?self
    {
        $item = $this->db->getSingleInt(
            'SELECT kAuswahlAssistentOrt
                FROM tauswahlassistentort AS o
                JOIN tauswahlassistentgruppe AS g
                    ON g.kAuswahlAssistentGruppe = o.kAuswahlAssistentGruppe
                    AND g.kSprache = :langID
                WHERE o.cKey = :keyID
                    AND o.kKey = :kkey',
            'kAuswahlAssistentOrt',
            [
                'langID' => $languageID,
                'keyID'  => $keyName,
                'kkey'   => $id
            ]
        );

        return $item > 0
            ? new self($item, 0, $backend)
            : null;
    }
}
