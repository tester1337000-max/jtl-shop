<?php

declare(strict_types=1);

namespace JTL\Update;

use Exception;
use stdClass;

/**
 * Trait MigrationTableTrait
 * @package JTL\Update
 */
trait MigrationTableTrait
{
    /**
     * @return array<string, int>
     */
    public function getLocaleSections(): array
    {
        $result = [];
        foreach ($this->fetchAll('SELECT kSprachsektion AS id, cName AS name FROM tsprachsektion') as $item) {
            $result[(string)$item->name] = (int)$item->id;
        }

        return $result;
    }

    /**
     * @return array<string, int>
     */
    public function getLocales(): array
    {
        $result = [];
        foreach ($this->fetchAll('SELECT kSprachISO AS id, cISO AS name FROM tsprachiso') as $item) {
            $result[(string)$item->name] = (int)$item->id;
        }

        return $result;
    }

    public function dropColumn(string $table, string $column): void
    {
        if (!\array_key_exists($column, DBManager::getColumns($table))) {
            return;
        }
        try {
            $this->execute(\sprintf('ALTER TABLE `%s` DROP `%s`', $table, $column));
        } catch (Exception) {
        }
    }

    /**
     * Add or update a row in tsprachwerte
     *
     * @param string $locale locale iso code e.g. "ger"
     * @param string $section section e.g. "global". See tsprachsektion for all sections
     * @param string $key unique name to identify localization
     * @param string $value localized text
     * @param bool   $system optional flag for system-default.
     * @throws Exception if locale key or section is wrong
     */
    public function setLocalization(
        string $locale,
        string $section,
        string $key,
        string $value,
        bool $system = true
    ): void {
        $locales  = $this->getLocales();
        $sections = $this->getLocaleSections();
        if (!isset($locales[$locale])) {
            throw new Exception(\sprintf('Locale key "%s" not found', $locale));
        }
        if (!isset($sections[$section])) {
            throw new Exception(\sprintf('Section name "%s" not found', $section));
        }
        $this->execute(
            \sprintf(
                "INSERT INTO tsprachwerte SET
                kSprachISO = %d, 
                kSprachsektion = %d, 
                cName = '%s', 
                cWert = '%s', 
                cStandard = '%s', 
                bSystem = %d 
                ON DUPLICATE KEY UPDATE 
                    cWert = IF(cWert = cStandard, VALUES(cStandard), cWert), cStandard = VALUES(cStandard)",
                $locales[$locale],
                $sections[$section],
                $key,
                $value,
                $value,
                $system
            )
        );
    }

    public function removeLocalization(string $key, ?string $section = null): void
    {
        if ($section) {
            $this->getDB()->queryPrepared(
                'DELETE tsprachwerte
                    FROM tsprachwerte
                    INNER JOIN tsprachsektion USING(kSprachsektion)
                    WHERE tsprachwerte.cName = :langKey AND tsprachsektion.cName = :langSection',
                [
                    'langKey'     => $key,
                    'langSection' => $section
                ]
            );
        } else {
            $this->getDB()->queryPrepared(
                'DELETE FROM tsprachwerte WHERE cName = :langKey',
                ['langKey' => $key]
            );
        }
    }

    /**
     * @return string[]
     */
    private function getAvailableInputTypes(): array
    {
        return [
            'selectbox',
            'number',
            'pass',
            'text',
            'kommazahl',
            'listbox',
            'selectkdngrp',
            'color'
        ];
    }

    private function getLastId(string $table, string $column): int
    {
        /** @var stdClass $result */
        $result = $this->fetchOne(
            \sprintf(
                'SELECT `%s` AS last_id FROM `%s` ORDER BY `%s` DESC LIMIT 1',
                $column,
                $table,
                $column
            )
        );

        return ++$result->last_id;
    }

    /**
     * @param string        $configName internal config name
     * @param string|int    $configValue default config value
     * @param int           $configSectionID config section
     * @param string        $externalName displayed config name
     * @param string|null   $inputType config input type (set to NULL and set additionalProperties->cConf to "N" for
     *                                   section header)
     * @param int           $sort internal sorting number
     * @param stdClass|null $additionalProperties
     * @param bool          $overwrite force overwrite of already existing config
     * @throws Exception
     */
    public function setConfig(
        string $configName,
        string|int $configValue,
        int $configSectionID,
        string $externalName,
        string|null $inputType,
        int $sort,
        ?stdClass $additionalProperties = null,
        bool $overwrite = false
    ): void {
        $availableInputTypes = $this->getAvailableInputTypes();
        // input types that need $additionalProperties->inputOptions
        $inputTypeNeedsOptions = ['listbox', 'selectbox'];

        $confValueID = ($additionalProperties === null
            || !isset($additionalProperties->kEinstellungenConf)
            || !$additionalProperties->kEinstellungenConf)
            ? $this->getLastId('teinstellungenconf', 'kEinstellungenConf')
            : $additionalProperties->kEinstellungenConf;
        if (!$configName) {
            throw new Exception('configName not provided or empty / zero');
        }
        if (!$configSectionID) {
            throw new Exception('configSection not provided or empty / zero');
        }
        if (!$externalName) {
            throw new Exception('externalName not provided or empty / zero');
        }
        if (!$sort) {
            throw new Exception('sort not provided or empty / zero');
        }
        if (
            !$inputType
            && ($additionalProperties === null
                || !isset($additionalProperties->cConf)
                || $additionalProperties->cConf !== 'N')
        ) {
            throw new Exception('inputType has to be provided if additionalProperties->cConf is not set to "N"');
        }
        if (
            \in_array($inputType, $inputTypeNeedsOptions, true)
            && (!\is_object($additionalProperties)
                || !isset($additionalProperties->inputOptions)
                || !\is_array($additionalProperties->inputOptions)
                || \count($additionalProperties->inputOptions) === 0)
        ) {
            throw new Exception(
                'additionalProperties->inputOptions has to be provided if inputType is "' .
                $inputType . '"'
            );
        }
        if ($overwrite !== true) {
            $count = $this->fetchOne(
                "SELECT COUNT(*) AS count 
                    FROM teinstellungen 
                    WHERE cName = '" . $configName . "'"
            );
            if ($count !== null && (int)$count->count !== 0) {
                throw new Exception('another entry already present in teinstellungen and overwrite is disabled');
            }
            $count = $this->fetchOne(
                "SELECT COUNT(*) AS count 
                    FROM teinstellungenconf 
                    WHERE cWertName = '" . $configName . "' 
                        OR kEinstellungenConf = " . $confValueID
            );
            if ($count !== null && (int)$count->count !== 0) {
                throw new Exception('another entry already present in teinstellungenconf and overwrite is disabled');
            }
            $count = $this->fetchOne(
                'SELECT COUNT(*) AS count 
                    FROM teinstellungenconfwerte 
                    WHERE kEinstellungenConf = ' . $confValueID
            );
            if ($count !== null && (int)$count->count !== 0) {
                throw new Exception(
                    'another entry already present in ' .
                    'teinstellungenconfwerte and overwrite is disabled'
                );
            }
            unset($count);
            // $overwrite has to be set to true in order to create a new inputType
            if (
                ($additionalProperties === null
                    || !isset($additionalProperties->cConf)
                    || $additionalProperties->cConf !== 'N')
                && !\in_array($inputType, $availableInputTypes, true)
            ) {
                throw new Exception(
                    'inputType "' . $inputType .
                    '" not in available types and additionalProperties->cConf is not set to "N"'
                );
            }
        }
        $this->removeConfig($configName);

        $configurable = ($additionalProperties === null
            || !isset($additionalProperties->cConf)
            || $additionalProperties->cConf !== 'N')
            ? 'Y'
            : 'N';
        $inputType    = $configurable === 'N' ? '' : $inputType;
        $moduleID     = $additionalProperties->cModulId ?? '_DBNULL_';
        $description  = $additionalProperties->cBeschreibung ?? '';
        $viewDefault  = $additionalProperties->nStandardAnzeigen ?? 1;
        $moduleNumber = $additionalProperties->nModul ?? 0;

        $config                        = new stdClass();
        $config->kEinstellungenSektion = $configSectionID;
        $config->cName                 = $configName;
        $config->cWert                 = $configValue;
        $config->cModulId              = $moduleID;
        $this->getDB()->insert('teinstellungen', $config);
        if ($this->getDB()->getSingleObject("SHOW TABLES LIKE 'teinstellungen_default'") !== null) {
            $this->getDB()->insert('teinstellungen_default', $config);
        }
        unset($config);

        $confSetting                        = new stdClass();
        $confSetting->kEinstellungenConf    = $confValueID;
        $confSetting->kEinstellungenSektion = $configSectionID;
        $confSetting->cName                 = $externalName;
        $confSetting->cBeschreibung         = $description;
        $confSetting->cWertName             = $configName;
        $confSetting->cInputTyp             = $inputType;
        $confSetting->cModulId              = $moduleID;
        $confSetting->nSort                 = $sort;
        $confSetting->nStandardAnzeigen     = $viewDefault;
        $confSetting->nModul                = $moduleNumber;
        $confSetting->cConf                 = $configurable;
        $this->getDB()->insert('teinstellungenconf', $confSetting);
        unset($confSetting);
        if (
            \is_object($additionalProperties)
            && isset($additionalProperties->inputOptions)
            && \is_array($additionalProperties->inputOptions)
        ) {
            $sortIndex = 1;
            $confValue = new stdClass();
            foreach ($additionalProperties->inputOptions as $optionKey => $optionValue) {
                $confValue->kEinstellungenConf = $confValueID;
                $confValue->cName              = $optionValue;
                $confValue->cWert              = $optionKey;
                $confValue->nSort              = $sortIndex;
                $this->getDB()->insert('teinstellungenconfwerte', $confValue);
                $sortIndex++;
            }
            unset($confValue);
        }
    }

    public function removeConfig(string $key): void
    {
        $this->execute("DELETE FROM teinstellungen WHERE cName = '" . $key . "'");
        if ($this->getDB()->getSingleObject("SHOW TABLES LIKE 'teinstellungen_default'") !== null) {
            $this->execute("DELETE FROM teinstellungen_default WHERE cName = '" . $key . "'");
        }
        $this->execute(
            "DELETE FROM teinstellungenconfwerte 
                WHERE kEinstellungenConf = (
                    SELECT kEinstellungenConf 
                        FROM teinstellungenconf 
                        WHERE cWertName = '" . $key . "')"
        );
        $this->execute("DELETE FROM teinstellungenconf WHERE cWertName = '" . $key . "'");
    }
}
