<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;

readonly class Factory
{
    public function __construct(
        private DbInterface $db,
        private JTLCacheInterface $cache,
        private string $adminURL
    ) {
    }

    /**
     * @return StatusCheckInterface[]
     */
    public function getChecks(): array
    {
        return [
            'hasPendingUpdates'                 => new PendingUpdates($this->db, $this->cache, $this->adminURL),
            'noInsecureAdminUsers'              => new Admin2FA($this->db, $this->cache, $this->adminURL),
            'validDBStructure'                  => new DBStructure($this->db, $this->cache, $this->adminURL),
            'hasInstalledStandardLang'          => new DefaultLanguage($this->db, $this->cache, $this->adminURL),
            'emailSyntaxOK'                     => new EmailSyntaxFail($this->db, $this->cache, $this->adminURL),
            'emailSyntaxNotChecked'             => new EmailSyntaxUnchecked($this->db, $this->cache, $this->adminURL),
            'noExpiredLicenses'                 => new ExpiredLicenses($this->db, $this->cache, $this->adminURL),
            'noExportSyntaxErrors'              => new ExportsSyntaxFail($this->db, $this->cache, $this->adminURL),
            'noExportSyntaxUnchecked'           => new ExportsSyntaxUnchecked($this->db, $this->cache, $this->adminURL),
            'validFolderPermissions'            => new FolderPermissions($this->db, $this->cache, $this->adminURL),
            'fullTextIndexOK'                   => new FulltextIndex($this->db, $this->cache, $this->adminURL),
            'hasInstallDir'                     => new InstallDir($this->db, $this->cache, $this->adminURL),
            'noDuplicateLinkGroupTemplateNames' => new LinkGroupTemplateNames($this->db, $this->cache, $this->adminURL),
            'secureMailConfig'                  => new MailConfiguration($this->db, $this->cache, $this->adminURL),
            'noMobileTemplate'                  => new MobileTemplate($this->db, $this->cache, $this->adminURL),
            'noFolderPermissionProblems'        => new FolderPermissions($this->db, $this->cache, $this->adminURL),
            'validModifiedFileStruct'           => new ModifiedFiles($this->db, $this->cache, $this->adminURL),
            'dbPhpTimeDiff'                     => new MySqlPhpTime($this->db, $this->cache, $this->adminURL),
            'validOrphanedFilesStruct'          => new OrphanedFiles($this->db, $this->cache, $this->adminURL),
            'noMissingPageTranslations'         => new PageTranslations($this->db, $this->cache, $this->adminURL),
            'noOldPasswordResetLinks'           => new PasswordResetTemplate($this->db, $this->cache, $this->adminURL),
            'noOldPluginVersions'               => new PluginUpdates($this->db, $this->cache, $this->adminURL),
            'noActiveProfiler'                  => new Profiler($this->db, $this->cache, $this->adminURL),
            'hasExtensionSOAP'                  => new SOAPSupport($this->db, $this->cache, $this->adminURL),
            'noDuplicateSpecialLinks'           => new SpecialLinks($this->db, $this->cache, $this->adminURL),
            'noMissingSystemPages'              => new SystemPages($this->db, $this->cache, $this->adminURL),
            'noStandardTemplateIssues'          => new Template($this->db, $this->cache, $this->adminURL),
            'noOldTwoFAHashes'                  => new TwoFAHashes($this->db, $this->cache, $this->adminURL),
            'noOrphanedCategories'              => new OrphanedCategories($this->db, $this->cache, $this->adminURL),
            'hasValidEnvironment'               => new Environment($this->db, $this->cache, $this->adminURL),
            'hasValidTemplateVersion'           => new TemplateVersion($this->db, $this->cache, $this->adminURL),
            'hasShopVersionUpgrade'             => new ShopVersionUpgrade($this->db, $this->cache, $this->adminURL),
            'hazardousDefinesSet'               => new HazardousDefinesSet($this->db, $this->cache, $this->adminURL),
            'hasMailErrors'                     => new MailErrors($this->db, $this->cache, $this->adminURL),
            'hasMissingLanguageVariables'       => new LanguageVariables($this->db, $this->cache, $this->adminURL),
        ];
    }

    /**
     * @param class-string<StatusCheckInterface> $className
     */
    public function getCheckByClassName(string $className): StatusCheckInterface
    {
        if (\class_exists($className)) {
            $instance = new $className($this->db, $this->cache, $this->adminURL);
            if ($instance instanceof StatusCheckInterface) {
                return $instance;
            }
        }
        throw new \InvalidArgumentException('Invalid class name');
    }
}
