<?php

declare(strict_types=1);

namespace JTL\Backend\Settings;

use JTL\Backend\Menu;
use JTL\Backend\Settings\Sections\SectionInterface;
use JTL\DB\DbInterface;
use JTL\Helpers\Text;
use JTL\L10n\GetText;
use JTL\Router\Route;
use JTL\Shop;
use stdClass;

/**
 * Class Search
 * @package JTL\Backend\Settings
 */
class Search
{
    public const SEARCH_MODE_LIST = 1;

    public const SEARCH_MODE_RANGE = 2;

    public const SEARCH_MODE_ID = 3;

    public const SEARCH_MODE_TEXT = 4;

    private int $mode = 0;

    public string $title = '';

    public function __construct(protected DbInterface $db, protected GetText $getText, protected Manager $manager)
    {
    }

    private function getWhere(string $query): string
    {
        $where    = "(ec.cModulId IS NULL OR ec.cModulId = '')
            AND ec.kEinstellungenSektion != " . \CONF_EXPORTFORMATE . ' ';
        $idList   = \explode(',', $query);
        $isIdList = \count($idList) > 1;
        if ($isIdList) {
            foreach ($idList as $i => $item) {
                $idList[$i] = (int)$item;
                if ($idList[$i] === 0) {
                    $isIdList = false;
                    break;
                }
            }
        }

        if ($isIdList) {
            $where       .= ' AND kEinstellungenConf IN (' . \implode(', ', $idList) . ')';
            $this->mode  = self::SEARCH_MODE_LIST;
            $this->title = \sprintf(\__('searchForID'), \implode(', ', $idList));
        } else {
            $rangeList = \explode('-', $query);
            $isIdRange = \count($rangeList) === 2;
            if ($isIdRange) {
                $rangeList[0] = (int)$rangeList[0];
                $rangeList[1] = (int)$rangeList[1];
                if ($rangeList[0] === 0 || $rangeList[1] === 0) {
                    $isIdRange = false;
                }
            }
            if ($isIdRange) {
                $where       .= ' AND kEinstellungenConf BETWEEN ' . $rangeList[0] . ' AND ' . $rangeList[1];
                $where       .= " AND cConf = 'Y'";
                $this->mode  = self::SEARCH_MODE_RANGE;
                $this->title = \sprintf(\__('searchForIDRange'), $rangeList[0] . ' - ' . $rangeList[1]);
            } elseif ((int)$query > 0) {
                $this->mode  = self::SEARCH_MODE_ID;
                $this->title = \sprintf(\__('searchForID'), $query);
                $where       .= ' AND kEinstellungenConf = ' . (int)$query;
            } else {
                $where .= ' AND (' . $this->getTextSearchWhere($query) . ')';
            }
        }

        return $where;
    }

    private function getTextSearchWhere(string $query): string
    {
        $query              = \mb_convert_case($query, \MB_CASE_LOWER);
        $queryEnt           = Text::htmlentities($query);
        $this->mode         = self::SEARCH_MODE_TEXT;
        $this->title        = \sprintf(\__('searchForName'), $query);
        $configTranslations = $this->getText->getAdminTranslations('configs/configs');
        $valueNames         = [];
        if ($configTranslations === null) {
            throw new \InvalidArgumentException('Could not load config translations');
        }
        foreach ($configTranslations->getTranslations() as $translation) {
            $orig  = $translation->getOriginal();
            $trans = $translation->getTranslation();
            if (
                \mb_substr($orig, -5) === '_name'
                && (\mb_stripos($trans, $query) !== false || \mb_stripos($trans, $queryEnt) !== false)
            ) {
                $valueName    = \preg_replace('/(_name|_desc)$/', '', $orig);
                $valueNames[] = "'" . $valueName . "'";
            }
        }
        $where = 'cWertName IN (' . (\implode(', ', $valueNames) ?: "''") . ')';
        $where .= " AND cConf = 'Y'";

        $groupTranslations = $this->getText->getAdminTranslations('configs/groups');
        $valueNames        = [];
        foreach ($groupTranslations?->getTranslations() ?? [] as $translation) {
            $orig  = $translation->getOriginal();
            $trans = $translation->getTranslation();
            if (\mb_stripos($trans, $query) !== false || \mb_stripos($trans, $queryEnt) !== false) {
                $valueNames[] = "'" . $orig . "'";
            }
        }
        $where .= " OR cConf = 'N' AND cWertName IN (" . (\implode(',', $valueNames) ?: "''") . ')';

        return $where;
    }

    /**
     * @return SectionInterface[]
     */
    public function getResultSections(string $query): array
    {
        $data       = $this->db->getCollection(
            'SELECT ec.*, e.cWert AS currentValue, ed.cWert AS defaultValue
                FROM teinstellungenconf AS ec
                LEFT JOIN teinstellungen AS e
                  ON e.cName = ec.cWertName
                LEFT JOIN teinstellungen_default AS ed
                  ON ed.cName = ec.cWertName
                WHERE ' . $this->getWhere($query) . '
                    OR (ec.cModulId IS NULL OR ec.cModulId = "") AND e.cName LIKE :likeQuery
                ORDER BY ec.kEinstellungenSektion, nSort',
            ['likeQuery' => '%' . $query . '%']
        );
        $sectionIDs = \array_unique(\array_map('\intval', $data->pluck('kEinstellungenSektion')->toArray()));
        $configIDs  = \array_unique(\array_map('\intval', $data->pluck('kEinstellungenConf')->toArray()));
        $factory    = new SectionFactory();
        $sections   = [];
        $urlPrefix  = Shop::getAdminURL() . '/' . Route::CONFIG . '?einstellungen_suchen=1&cSuche=';
        $menu       = new Menu($this->db, Shop::Container()->getAdminAccount(), $this->getText);
        $structure  = $menu->getStructure();
        foreach ($sectionIDs as $sectionID) {
            $section = $factory->getSection($sectionID, $this->manager);
            $section->load();
            foreach ($section->getSubsections() as $subsection) {
                $subsection->setShow(false);
                if (\in_array($subsection->getID(), $configIDs, true)) {
                    $subsection->setShow(true);
                }
                $menuEntry = $this->mapConfigSectionToMenuEntry($sectionID, $structure);
                $url       = isset($menuEntry->link)
                    ? $menuEntry->link . ($menuEntry->settingsAnchor ?? '#' . $subsection->getValueName())
                    : $urlPrefix . $subsection->getID() . '#' . $subsection->getValueName();
                $subsection->setPath($menuEntry->path ?? '');
                $subsection->setURL($url);
                foreach ($subsection->getItems() as $idx => $item) {
                    $url = ($menuEntry->specialSetting ?? false) === false
                        ? $urlPrefix . $item->getID() . '#' . $item->getValueName()
                        : ($menuEntry->link ?? '') . ($menuEntry->settingsAnchor ?? '#' . $item->getValueName());
                    $item->setURL($url);
                    if (\in_array($item->getID(), $configIDs, true)) {
                        $subsection->setShow(true);
                        $item->setHighlight(true);
                    } elseif ($this->mode !== self::SEARCH_MODE_ID) {
                        $subsection->removeItemAtIndex($idx);
                    }
                }
            }
            $sections[] = $section;
        }

        return $sections;
    }

    /**
     * @param array<string, object{icon: string,
     *      items: array<string, string|stdClass|array<string, stdClass>>}&stdClass> $structure
     */
    private function mapConfigSectionToMenuEntry(int $sectionID, array $structure): stdClass
    {
        foreach ($structure as $item) {
            if (!isset($item->items)) {
                continue;
            }
            foreach ($item->items as $sub) {
                if (!\is_array($sub)) {
                    continue;
                }
                foreach ($sub as $sec) {
                    if (isset($sec->section) && $sec->section === $sectionID) {
                        return $sec;
                    }
                }
            }
        }

        return (object)[];
    }

    public function getMode(): int
    {
        return $this->mode;
    }

    public function setMode(int $mode): void
    {
        $this->mode = $mode;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getGetText(): GetText
    {
        return $this->getText;
    }

    public function setGetText(GetText $getText): void
    {
        $this->getText = $getText;
    }

    public function getDB(): DbInterface
    {
        return $this->db;
    }

    public function setDB(DbInterface $db): void
    {
        $this->db = $db;
    }
}
