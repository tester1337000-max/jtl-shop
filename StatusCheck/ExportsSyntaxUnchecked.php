<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Export\Validator;
use JTL\Router\Route;

class ExportsSyntaxUnchecked extends AbstractStatusCheck
{
    public const CACHE_ID_EXPORT_SYNTAX_CHECK = 'validExportSyntaxCheck';

    private int $expSyntaxErrorCount = 0;

    private string $hash = '';

    public function isOK(): bool
    {
        $cacheKey = self::CACHE_ID_EXPORT_SYNTAX_CHECK . Validator::SYNTAX_NOT_CHECKED;
        /** @var int|false $syntaxErrCnt */
        $syntaxErrCnt = $this->cache->get($cacheKey);
        if ($syntaxErrCnt === false) {
            $syntaxErrCnt = $this->db->getSingleInt(
                'SELECT COUNT(*) AS cnt FROM texportformat WHERE nFehlerhaft = :type',
                'cnt',
                ['type' => Validator::SYNTAX_NOT_CHECKED]
            );
            $this->cache->set($cacheKey, $syntaxErrCnt, [\CACHING_GROUP_STATUS, self::CACHE_ID_EXPORT_SYNTAX_CHECK]);
        }
        $this->expSyntaxErrorCount = $syntaxErrCnt;
        $this->hash                = \md5('hasUncheckedExportTemplates_' . $syntaxErrCnt);

        return $syntaxErrCnt === 0;
    }

    public function getURL(): ?string
    {
        return $this->adminURL . Route::EXPORT;
    }

    public function getTitle(): string
    {
        return \__('getExportFormatUncheckedCountTitle');
    }

    public function generateMessage(): void
    {
        $this->addNotification(
            \sprintf(\__('getExportFormatUncheckedCountMessage'), $this->expSyntaxErrorCount),
            $this->hash
        );
    }
}
