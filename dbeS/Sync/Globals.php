<?php

declare(strict_types=1);

namespace JTL\dbeS\Sync;

use JTL\dbeS\Starter;
use JTL\Helpers\GeneralObject;
use JTL\Language\LanguageHelper;

/**
 * Class Globals
 * @package JTL\dbeS\Sync
 */
final class Globals extends AbstractSync
{
    public function handle(Starter $starter): void
    {
        foreach ($starter->getXML() as $item) {
            /**
             * @var string               $file
             * @var array<string, mixed> $xml
             */
            [$file, $xml] = [\key($item), \reset($item)];
            if (\str_contains($file, 'del_globals.xml')) {
                $this->handleDeletes($xml);
            } elseif (\str_contains($file, 'globals.xml')) {
                $this->handleInserts($xml);
            }
        }
        $this->db->query('UPDATE tglobals SET dLetzteAenderung = NOW()');
    }

    /**
     * @param array<mixed> $xml
     */
    private function handleDeletes(array $xml): void
    {
        $source = $xml['del_globals_wg']['kWarengruppe'] ?? [];
        if (\is_numeric($source)) {
            $source = [$source];
        }
        foreach (\array_filter(\array_map('\intval', $source)) as $groupID) {
            $this->deleteProductTypeGroup($groupID);
        }
    }

    /**
     * @param array<mixed> $xml
     */
    private function handleInserts(array $xml): void
    {
        $source = $xml['globals'] ?? null;
        if (\is_array($source)) {
            $this->updateCompany($source);
            $this->updateLanguages($source);
            $this->xml2db($source, 'tlieferstatus', 'mLieferstatus');
            $this->xml2db($source, 'txsellgruppe', 'mXsellgruppe');
            $this->xml2db($source, 'teinheit', 'mEinheit');
            $this->xml2db($source, 'twaehrung', 'mWaehrung');
            $this->xml2db($source, 'tsteuerklasse', 'mSteuerklasse');
            $this->xml2db($source, 'tsteuersatz', 'mSteuersatz');
            $this->xml2db($source, 'tversandklasse', 'mVersandklasse');
            $this->updateTaxZone($source);
            $this->updateCustomerGroups($source);
            $this->updateWarehouses($source);
            $this->updateUnits($source);
        } elseif ($source !== null) {
            $this->logger->error(__METHOD__ . ': XML for globals is not correctly formatted.');
        }
        if (isset($xml['globals_wg']['tWarengruppe']) && \is_array($xml['globals_wg']['tWarengruppe'])) {
            $groups = $this->mapper->mapArray($xml['globals_wg'], 'tWarengruppe', 'mWarengruppe');
            $this->upsert('twarengruppe', $groups, 'kWarengruppe');
        }
    }

    /**
     * @param array<mixed> $source
     */
    private function updateCustomerGroups(array $source): void
    {
        if (!GeneralObject::isCountable('tkundengruppe', $source)) {
            return;
        }
        $customerGroups = $this->mapper->mapArray($source, 'tkundengruppe', 'mKundengruppe');
        $this->dbDelInsert('tkundengruppe', $customerGroups, true);
        $this->db->query('TRUNCATE TABLE tkundengruppensprache');
        $this->db->query('TRUNCATE TABLE tkundengruppenattribut');
        $cgCount = \count($customerGroups);
        for ($i = 0; $i < $cgCount; $i++) {
            $item = $cgCount < 2 ? $source['tkundengruppe'] : $source['tkundengruppe'][$i];
            $this->xml2db($item, 'tkundengruppensprache', 'mKundengruppensprache', false);
            $this->xml2db($item, 'tkundengruppenattribut', 'mKundengruppenattribut', false);
        }
        $this->cache->flushTags([\CACHING_GROUP_ARTICLE, \CACHING_GROUP_CATEGORY]);
    }

    /**
     * @param array<mixed> $source
     */
    private function updateCompany(array $source): void
    {
        if (
            isset($source['tfirma'], $source['tfirma attr']['kFirma'])
            && \is_array($source['tfirma'])
            && $source['tfirma attr']['kFirma'] > 0
        ) {
            $this->mapper->mapObject($company, $source['tfirma'], 'mFirma');
            $this->dbDelInsert('tfirma', [$company], true);
            $this->cache->flushTags([\CACHING_GROUP_CORE]);
        }
    }

    /**
     * @param array<mixed> $source
     */
    private function updateLanguages(array $source): void
    {
        $languages = $this->mapper->mapArray($source, 'tsprache', 'mSprache');
        foreach ($languages as $language) {
            $language->cStandard = $language->cWawiStandard;
            unset($language->cWawiStandard);
        }
        if (\count($languages) > 0) {
            $this->dbDelInsert('tsprache', $languages, true);
            $this->cache->flushTags([\CACHING_GROUP_LANGUAGE]);
        }
    }

    /**
     * @param array<mixed> $source
     */
    private function updateTaxZone(array $source): void
    {
        if (!GeneralObject::isCountable('tsteuerzone', $source)) {
            return;
        }
        $taxZones = $this->mapper->mapArray($source, 'tsteuerzone', 'mSteuerzone');
        $this->dbDelInsert('tsteuerzone', $taxZones, true);
        $this->db->query('DELETE FROM tsteuerzoneland');
        $taxCount = \count($taxZones);
        for ($i = 0; $i < $taxCount; $i++) {
            $this->upsert(
                'tsteuerzoneland',
                $this->mapper->mapArray(
                    $taxCount < 2 ? $source['tsteuerzone'] : $source['tsteuerzone'][$i],
                    'tsteuerzoneland',
                    'mSteuerzoneland'
                ),
                'kSteuerzone',
                'cISO'
            );
        }
    }

    /**
     * @param array<mixed> $source
     */
    private function updateWarehouses(array $source): void
    {
        if (!GeneralObject::isCountable('twarenlager', $source)) {
            return;
        }
        $warehouses = $this->mapper->mapArray($source, 'twarenlager', 'mWarenlager');
        $visibility = $this->db->getObjects('SELECT kWarenlager, nAktiv FROM twarenlager WHERE nAktiv = 1');
        // Alle Einträge in twarenlager löschen - Wawi 1.0.1 sendet immer alle Warenlager.
        $this->db->query('DELETE FROM twarenlager WHERE 1');
        $this->upsert('twarenlager', $warehouses, 'kWarenlager');
        foreach ($visibility as $lager) {
            $this->db->update('twarenlager', 'kWarenlager', $lager->kWarenlager, $lager);
        }
    }

    /**
     * @param array<mixed> $source
     */
    private function updateUnits(array $source): void
    {
        if (!GeneralObject::isCountable('tmasseinheit', $source)) {
            return;
        }
        $units = $this->mapper->mapArray($source, 'tmasseinheit', 'mMasseinheit');
        foreach ($units as $unit) {
            unset($unit->kBezugsMassEinheit);
        }
        $this->dbDelInsert('tmasseinheit', $units, true);
        $this->db->query('TRUNCATE TABLE tmasseinheitsprache');
        $meCount = \count($units);
        for ($i = 0; $i < $meCount; $i++) {
            $item = $meCount < 2 ? $source['tmasseinheit'] : $source['tmasseinheit'][$i];
            $this->xml2db($item, 'tmasseinheitsprache', 'mMasseinheitsprache', false);
        }
    }

    private function deleteProductTypeGroup(int $id): void
    {
        $this->db->delete('twarengruppe', 'kWarengruppe', $id);
        $this->logger->debug('Warengruppe geloescht: {id}', ['id' => $id]);
    }

    /**
     * @param array<mixed> $xml
     */
    private function xml2db(array $xml, string $table, string $toMap, bool $del = true): void
    {
        if (GeneralObject::isCountable($table, $xml)) {
            $objects = $this->mapper->mapArray($xml, $table, $toMap);
            $this->dbDelInsert($table, $objects, $del);
        }
    }

    /**
     * @param string           $tablename
     * @param array<\stdClass> $objects
     * @param bool             $del
     */
    private function dbDelInsert(string $tablename, array $objects, bool $del): void
    {
        $language = LanguageHelper::getInstance($this->db, $this->cache);
        if ($del) {
            if ($tablename === 'tsprache') {
                $this->db->query("DELETE FROM tsprache WHERE cISO != 'ger' AND cISO != 'eng'");
                $this->db->query("UPDATE tsprache SET active = 0, cShopStandard = 'N', cStandard = 'N'");
                foreach ($objects as $lang) {
                    $lang->active = 1;
                }
            } else {
                $this->db->query('DELETE FROM ' . $tablename);
            }
        }
        foreach ($objects as $object) {
            // hack? unset arrays/objects that would result in nicedb exceptions
            foreach (\get_object_vars($object) as $key => $var) {
                if (\is_array($var) || \is_object($var)) {
                    unset($object->$key);
                }
            }
            $key = $tablename === 'tsprache'
                ? $this->db->upsert($tablename, $object)
                : $this->db->insert($tablename, $object);
            if ($key < 0 || ($tablename !== 'tsprache' && $key === 0)) {
                $this->logger->error(__METHOD__ . ' failed: ' . $tablename . ', data: ' . \print_r($object, true));
            }
        }
        if ($tablename === 'tsprache') {
            foreach ($objects as $object) {
                $language->mappedImportCustomVariables($object->cISO);
            }
        }
    }
}
