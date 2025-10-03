<?php

declare(strict_types=1);

namespace JTL\Extensions\SelectionWizard;

use JTL\DB\DbInterface;
use JTL\Shop;
use stdClass;

/**
 * Class Group
 * @package JTL\Extensions\SelectionWizard
 */
class Group
{
    public int $kAuswahlAssistentGruppe = 0;

    public int $kSprache = 0;

    public string $cName = '';

    public string $cBeschreibung = '';

    public int $nAktiv = 0;

    /**
     * @var Question[]
     */
    public array $oAuswahlAssistentFrage_arr = [];

    /**
     * @var Location[]
     */
    public array $oAuswahlAssistentOrt_arr = [];

    public string $cSprache = '';

    public int $nStartseite = 0;

    public string $cKategorie = '';

    private DbInterface $db;

    public function __construct(int $groupID = 0, bool $active = true, bool $activeOnly = true, bool $backend = false)
    {
        $this->db = Shop::Container()->getDB();
        if ($groupID > 0) {
            $this->loadFromDB($groupID, $active, $activeOnly, $backend);
        }
    }

    private function loadFromDB(int $groupID, bool $active, bool $activeOnly, bool $backend): void
    {
        $activeSQL = $active ? ' AND nAktiv = 1' : '';
        $group     = $this->db->getSingleObject(
            'SELECT *
                FROM tauswahlassistentgruppe
                WHERE kAuswahlAssistentGruppe = :groupID' .
            $activeSQL,
            ['groupID' => $groupID]
        );
        if ($group === null || $group->kAuswahlAssistentGruppe <= 0) {
            return;
        }
        $question = new Question();

        $this->kAuswahlAssistentGruppe    = (int)$group->kAuswahlAssistentGruppe;
        $this->kSprache                   = (int)$group->kSprache;
        $this->nAktiv                     = (int)$group->nAktiv;
        $this->cName                      = $group->cName;
        $this->cBeschreibung              = $group->cBeschreibung;
        $this->oAuswahlAssistentFrage_arr = $question->getQuestions($groupID, $activeOnly);
        $location                         = new Location(0, $groupID, $backend);
        $this->oAuswahlAssistentOrt_arr   = $location->oOrt_arr;
        foreach ($this->oAuswahlAssistentOrt_arr as $location) {
            if ($location->cKey === \AUSWAHLASSISTENT_ORT_KATEGORIE) {
                $this->cKategorie .= $location->kKey . ';';
            }
            if ($location->cKey === \AUSWAHLASSISTENT_ORT_STARTSEITE) {
                $this->nStartseite = 1;
            }
        }
        $language       = $this->db->getSingleObject(
            'SELECT cNameDeutsch 
                FROM tsprache 
                WHERE kSprache = :langID',
            ['langID' => $this->kSprache]
        );
        $this->cSprache = $language->cNameDeutsch ?? '';
    }

    /**
     * @return Group[]
     */
    public function getGroups(int $langID, bool $active = true, bool $activeOnly = true, bool $backend = false): array
    {
        $groups    = [];
        $activeSQL = $active ? ' AND nAktiv = 1' : '';
        $groupData = $this->db->getInts(
            'SELECT kAuswahlAssistentGruppe AS id
                FROM tauswahlassistentgruppe
                WHERE kSprache = :langID' . $activeSQL,
            'id',
            ['langID' => $langID]
        );
        foreach ($groupData as $groupID) {
            $groups[] = new self($groupID, $active, $activeOnly, $backend);
        }

        return $groups;
    }

    /**
     * @param array<string, mixed> $params
     * @param bool                 $primary
     * @return array<string, int>|bool|int
     */
    public function saveGroup(array $params, bool $primary = false): array|bool|int
    {
        $checks = $this->checkGroup($params);
        if (\count($checks) !== 0) {
            return $checks;
        }
        $data                = new stdClass();
        $data->kSprache      = $this->kSprache;
        $data->cName         = $this->cName;
        $data->cBeschreibung = $this->cBeschreibung;
        $data->nAktiv        = $this->nAktiv;

        $groupID = $this->db->insert('tauswahlassistentgruppe', $data);
        if ($groupID < 1) {
            return false;
        }
        $location = new Location();
        $location->saveLocation($params, $groupID);

        return $primary ? $groupID : true;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, int>|bool
     */
    public function updateGroup(array $params): array|bool
    {
        $validation = $this->checkGroup($params, true);
        if (\count($validation) !== 0) {
            return $validation;
        }
        $upd                = new stdClass();
        $upd->kSprache      = $this->kSprache;
        $upd->cName         = $this->cName;
        $upd->cBeschreibung = $this->cBeschreibung;
        $upd->nAktiv        = $this->nAktiv;

        $this->db->update(
            'tauswahlassistentgruppe',
            'kAuswahlAssistentGruppe',
            $this->kAuswahlAssistentGruppe,
            $upd
        );
        $location = new Location();
        $location->updateLocation($params, $this->kAuswahlAssistentGruppe);

        return true;
    }

    /**
     * @param array<string, mixed> $params
     * @param bool                 $update
     * @return array<string, int>
     */
    public function checkGroup(array $params, bool $update = false): array
    {
        $validation = [];
        if (empty($this->cName)) {
            $validation['cName'] = 1;
        }
        if ($this->kSprache === 0) {
            $validation['kSprache'] = 1;
        }
        if ($this->nAktiv !== 0 && $this->nAktiv !== 1) {
            $validation['nAktiv'] = 1;
        }
        $location = (new Location())->checkLocation($params, $update);

        return \array_merge($location, $validation);
    }

    /**
     * @param int[]|numeric-string[] $groupIDs
     * @return bool
     */
    public function deleteGroup(array $groupIDs): bool
    {
        foreach (\array_map('\intval', $groupIDs) as $groupID) {
            $this->db->queryPrepared(
                'DELETE tag, taf, tao
                    FROM tauswahlassistentgruppe tag
                    LEFT JOIN tauswahlassistentfrage taf
                        ON taf.kAuswahlAssistentGruppe = tag.kAuswahlAssistentGruppe
                    LEFT JOIN tauswahlassistentort tao
                        ON tao.kAuswahlAssistentGruppe = tag.kAuswahlAssistentGruppe
                    WHERE tag.kAuswahlAssistentGruppe = :groupID',
                ['groupID' => (int)$groupID]
            );
        }

        return true;
    }
}
