<?php

declare(strict_types=1);

namespace JTL\Smarty;

use JTL\DB\DbInterface;
use JTL\Language\LanguageModel;
use Smarty;
use Smarty\Template;
use Smarty_Internal_Template;

/**
 * Class MailSmarty
 * @package JTL\Smarty
 */
class MailSmarty extends JTLSmarty
{
    public function __construct(protected DbInterface $db, string $context = ContextType::MAIL)
    {
        parent::__construct(true, $context);
        $this->setCaching(JTLSmarty::CACHING_OFF)
            ->setDebugging(false)
            ->registerResource(
                'db',
                \SMARTY_LEGACY_MODE
                    ? new Smarty4ResourceNiceDB($db, $context)
                    : new SmartyResourceNiceDB($db, $context)
            );
        $this->setCompileDir(\PFAD_ROOT . \PFAD_COMPILEDIR . \PFAD_EMAILTEMPLATES)
            ->setTemplateDir(\PFAD_ROOT . \PFAD_EMAILTEMPLATES);
        $this->registerPlugins();
    }

    protected function registerPlugins(): void
    {
        parent::registerPlugins();
        $this->registerPlugin(Smarty::PLUGIN_FUNCTION, 'includeMailTemplate', $this->includeMailTemplate(...))
            ->registerPlugin(Smarty::PLUGIN_MODIFIER, 'maskPrivate', $this->maskPrivate(...));
    }

    protected function initTemplate(): ?string
    {
        return null;
    }

    /**
     * @param string[] $params
     */
    public function includeMailTemplate(array $params, Smarty_Internal_Template|Template $template): string
    {
        if (!isset($params['template'], $params['type']) || $template->getTemplateVars('int_lang') === null) {
            return '';
        }
        $tpl = $this->db->select(
            'temailvorlage',
            'cDateiname',
            $params['template']
        );
        if ($tpl !== null && isset($tpl->kEmailvorlage) && $tpl->kEmailvorlage > 0) {
            $tpl->kEmailvorlage = (int)$tpl->kEmailvorlage;
            /** @var LanguageModel $lang */
            $lang = $template->getTemplateVars('int_lang');
            $row  = $params['type'] === 'html' ? 'cContentHtml' : 'cContentText';
            $res  = $this->db->getSingleObject(
                'SELECT ' . $row . ' AS content
                    FROM temailvorlagesprache
                    WHERE kSprache = :lid
                 AND kEmailvorlage = :tid',
                ['lid' => $lang->getId(), 'tid' => $tpl->kEmailvorlage]
            );
            if (isset($res->content)) {
                return $template->getSmarty()->fetch(
                    'db:' . $params['type'] . '_' . $tpl->kEmailvorlage . '_' . $lang->kSprache
                );
            }
        }

        return '';
    }

    public function maskPrivate(string $str, int $pre = 0, int $post = 4, string $mask = '****'): string
    {
        if (\mb_strlen($str) <= $pre + $post) {
            return $str;
        }

        return \mb_substr($str, 0, $pre) . $mask . \mb_substr($str, -$post);
    }
}
