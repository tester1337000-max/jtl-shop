<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Backend\NotificationEntry;
use JTL\Export\Validator;
use JTL\Router\Route;

class ExportsSyntaxFail extends AbstractStatusCheck
{
    protected int $messageType = NotificationEntry::TYPE_DANGER;

    public const CACHE_ID_EXPORT_SYNTAX_CHECK = 'validExportSyntaxCheck';

    private int $expSyntaxErrorCount = 0;

    public function isOK(): bool
    {
        $cacheKey = self::CACHE_ID_EXPORT_SYNTAX_CHECK . Validator::SYNTAX_FAIL;
        /** @var int|false $syntaxErrCnt */
        $syntaxErrCnt = $this->cache->get($cacheKey);
        if ($syntaxErrCnt === false) {
            $syntaxErrCnt = $this->db->getSingleInt(
                'SELECT COUNT(*) AS cnt FROM texportformat WHERE nFehlerhaft = :type',
                'cnt',
                ['type' => Validator::SYNTAX_FAIL]
            );
            $this->cache->set($cacheKey, $syntaxErrCnt, [\CACHING_GROUP_STATUS, self::CACHE_ID_EXPORT_SYNTAX_CHECK]);
        }
        $this->expSyntaxErrorCount = $syntaxErrCnt;

        return $syntaxErrCnt === 0;
    }

    public function getURL(): ?string
    {
        return $this->adminURL . Route::EXPORT;
    }

    public function getTitle(): string
    {
        return \__('getExportFormatErrorCountTitle');
    }

    public function generateMessage(): void
    {
        $this->addNotification(\sprintf(\__('getExportFormatErrorCountMessage'), $this->expSyntaxErrorCount));
    }
}
