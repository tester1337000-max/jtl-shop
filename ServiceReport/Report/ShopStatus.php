<?php

declare(strict_types=1);

namespace JTL\ServiceReport\Report;

use JTL\Backend\FileCheck;
use JTL\Backend\StatusCheck\Factory;
use JTL\Shop;

class ShopStatus implements ReportInterface
{
    /**
     * @return array{dbPhpTimeDiff: array{db: string, php: string, diff: int}, hasActiveProfiler: bool,
     *      hasDifferentTemplateVersion: bool,
     *      hasExtensionSOAP: bool, hasFullTextIndexError: bool, hasInsecureMailConfig: bool,
     *      hasInstalledStandardLang: bool, hasInstallDir: bool, hasNewPluginVersions: bool,
     *      hasOrphanedCategories: bool, hasPendingUpdates: bool, hasStandardTemplateIssue: bool,
     *      hasValidEnvironment: bool, validDBStructure: bool, validFolderPermissions: bool,
     *      validModifiedFileStruct: bool, validOrphanedFilesStruct: bool}
     * @throws \Exception
     */
    public function getData(): array
    {
        $factory = new Factory(
            Shop::Container()->getDB(),
            Shop::Container()->getCache(),
            Shop::getAdminURL() . '/'
        );

        return [
            ...$factory->getChecks(),
            'modifiedFiles' => $this->checkModifiedFiles(),
        ];
    }

    /**
     * @return \stdClass[]
     */
    private function checkModifiedFiles(): array
    {
        $fileCheck          = new FileCheck();
        $modifiedFilesCount = 0;
        $modifiedFiles      = [];
        $coreMD5HashFile    = \PFAD_ROOT . \PFAD_ADMIN . \PFAD_INCLUDES . \PFAD_SHOPMD5
            . $fileCheck->getVersionString() . '.csv';
        $fileCheck->validateCsvFile($coreMD5HashFile, $modifiedFiles, $modifiedFilesCount);

        return $modifiedFiles;
    }
}
