<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Backend\NotificationEntry;
use JTL\Mail\Template\Model;
use JTL\Mail\Template\TemplateFactory;
use JTL\Router\Route;

class EmailSyntaxFail extends AbstractStatusCheck
{
    protected int $messageType = NotificationEntry::TYPE_DANGER;

    public const CACHE_ID_EMAIL_SYNTAX_CHECK = 'validEMailSyntaxCheck';

    private int $emailSyntaxErrCount = 0;

    public function isOK(): bool
    {
        $cacheKey = self::CACHE_ID_EMAIL_SYNTAX_CHECK . Model::SYNTAX_FAIL;
        /** @var int|false $syntaxErrCnt */
        $syntaxErrCnt = $this->cache->get($cacheKey);
        if ($syntaxErrCnt === false) {
            $syntaxErrCnt = 0;
            $templates    = $this->db->getObjects(
                'SELECT cModulId, kPlugin FROM temailvorlage WHERE nFehlerhaft = :type',
                ['type' => Model::SYNTAX_FAIL]
            );
            $factory      = new TemplateFactory($this->db);
            foreach ($templates as $template) {
                $module = $template->cModulId;
                if ($template->kPlugin > 0) {
                    $module = 'kPlugin_' . $template->kPlugin . '_' . $template->cModulId;
                }
                $syntaxErrCnt += $factory->getTemplate($module) !== null ? 1 : 0;
            }

            $this->cache->set($cacheKey, $syntaxErrCnt, [\CACHING_GROUP_STATUS, self::CACHE_ID_EMAIL_SYNTAX_CHECK]);
        }
        $this->emailSyntaxErrCount = $syntaxErrCnt;

        return $syntaxErrCnt === 0;
    }

    public function getURL(): ?string
    {
        return $this->adminURL . Route::EMAILTEMPLATES;
    }

    public function getTitle(): string
    {
        return \__('getEmailTemplateSyntaxErrorCountTitle');
    }

    public function generateMessage(): void
    {
        $this->addNotification(\sprintf(\__('getEmailTemplateSyntaxErrorCountMessage'), $this->emailSyntaxErrCount));
    }
}
