<?php

declare(strict_types=1);

namespace JTL\Smarty;

use DateTime;
use JTL\Backend\Revision;
use JTL\Catalog\Currency;
use JTL\DB\DbInterface;
use JTL\Helpers\Text;
use JTL\Shop;
use JTL\Update\Updater;
use Smarty\Smarty;
use Smarty\Template;

/**
 * Class BackendPlugins
 * @package JTL\Smarty
 */
readonly class BackendPlugins
{
    public function __construct(private DbInterface $db, private JTLSmarty $smarty)
    {
    }

    public function registerPlugins(): void
    {
        $this->smarty->registerPlugin(
            Smarty::PLUGIN_FUNCTION,
            'getCurrencyConversionSmarty',
            $this->getCurrencyConversion(...)
        )
            ->registerPlugin(
                Smarty::PLUGIN_FUNCTION,
                'getCurrencyConversionTooltipButton',
                $this->getCurrencyConversionTooltipButton(...)
            )->registerPlugin(Smarty::PLUGIN_FUNCTION, 'getCurrentPage', $this->getCurrentPage(...))
            ->registerPlugin(Smarty::PLUGIN_FUNCTION, 'SmartyConvertDate', $this->convertDate(...))
            ->registerPlugin(Smarty::PLUGIN_FUNCTION, 'getHelpDesc', $this->getHelpDesc(...))
            ->registerPlugin(Smarty::PLUGIN_FUNCTION, 'getExtensionCategory', $this->getExtensionCategory(...))
            ->registerPlugin(Smarty::PLUGIN_FUNCTION, 'formatVersion', $this->formatVersion(...))
            ->registerPlugin(Smarty::PLUGIN_MODIFIER, 'formatByteSize', Text::formatSize(...))
            ->registerPlugin(Smarty::PLUGIN_FUNCTION, 'getAvatar', $this->getAvatar(...))
            ->registerPlugin(Smarty::PLUGIN_FUNCTION, 'getRevisions', $this->getRevisions(...))
            ->registerPlugin(Smarty::PLUGIN_FUNCTION, 'captchaMarkup', $this->captchaMarkup(...));
    }

    /**
     * @param array{secondary?: bool, data?:mixed, show: bool, type: string, key: string|int} $params
     */
    private function getRevisions(array $params, Template|\Smarty_Internal_Template $template): string
    {
        return $template->getSmarty()->assign('secondary', $params['secondary'] ?? false)
            ->assign('data', $params['data'] ?? null)
            ->assign('show', $params['show'])
            ->assign('revisions', (new Revision($this->db))->getRevisions($params['type'], (int)$params['key']))
            ->fetch('tpl_inc/revisions.tpl');
    }

    /**
     * @param array{fPreisBrutto?:float|string, fPreisNetto?:float|string, bSteuer?: bool, cClass?: string} $params
     */
    private function getCurrencyConversion(array $params): string
    {
        $forceTax = !(isset($params['bSteuer']) && $params['bSteuer'] === false);
        if (!isset($params['fPreisBrutto'])) {
            $params['fPreisBrutto'] = 0;
        }
        if (!isset($params['fPreisNetto'])) {
            $params['fPreisNetto'] = 0;
        }
        if (!isset($params['cClass'])) {
            $params['cClass'] = '';
        }

        return Currency::getCurrencyConversion(
            $params['fPreisNetto'],
            $params['fPreisBrutto'],
            $params['cClass'],
            $forceTax
        );
    }

    /**
     * @param array{inputId?: string, placement?: string} $params
     */
    private function getCurrencyConversionTooltipButton(array $params): string
    {
        $placement = $params['placement'] ?? 'left';

        if (!isset($params['inputId'])) {
            return '';
        }
        $inputId = $params['inputId'];
        $button  = '<button type="button" class="btn btn-tooltip btn-link px-1" id="' .
            $inputId . 'Tooltip" data-html="true"';
        $button  .= ' data-toggle="tooltip" data-placement="' . $placement . '">';
        $button  .= '<i class="fa fa-eur"></i></button>';

        return $button;
    }

    /**
     * @param array{assign?: string} $params
     */
    private function getCurrentPage(array $params, Template|\Smarty_Internal_Template $template): void
    {
        $path = $_SERVER['REQUEST_URI'];
        $page = \basename($path, '.php');
        if ($page === \rtrim(\PFAD_ADMIN, '/')) {
            $page = 'index';
        }

        if (isset($params['assign'])) {
            $template->assign($params['assign'], $page);
        }
    }

    /**
     * @param array{placement: string, cID?: string, iconQuestion?: string, cDesc?: string} $params
     */
    private function getHelpDesc(array $params, Template|\Smarty_Internal_Template $template): string
    {
        $placement    = $params['placement'] ?? 'left';
        $cID          = !empty($params['cID']) ? $params['cID'] : null;
        $iconQuestion = !empty($params['iconQuestion']);
        $description  = isset($params['cDesc'])
            ? \str_replace('"', '\'', $params['cDesc'])
            : null;

        return $template->getSmarty()->assign('placement', $placement)
            ->assign('cID', $cID)
            ->assign('description', $description)
            ->assign('iconQuestion', $iconQuestion)
            ->fetch('tpl_inc/help_description.tpl');
    }

    /**
     * @param array{assign?: string, date?: string, format?: string} $params
     */
    private function convertDate(array $params, Template|\Smarty_Internal_Template $template): string
    {
        if (isset($params['date']) && \mb_strlen($params['date']) > 0) {
            $dateTime = new DateTime($params['date']);
            if (isset($params['format']) && \mb_strlen($params['format']) > 1) {
                $date = $dateTime->format($params['format']);
            } else {
                $date = $dateTime->format('d.m.Y H:i:s');
            }

            if (isset($params['assign'])) {
                $template->assign($params['assign'], $date);
            } else {
                return $date;
            }
        }

        return '';
    }

    /**
     * @param array{cat?: int} $params
     * @deprecated since 5.4.0
     */
    private function getExtensionCategory(array $params, Template|\Smarty_Internal_Template $template): void
    {
        if (!isset($params['cat'])) {
            return;
        }
        $catNames = [
            4  => 'Templates/Themes',
            5  => 'Sprachpakete',
            6  => 'Druckvorlagen',
            7  => 'Tools',
            8  => 'Marketing',
            9  => 'Zahlungsarten',
            10 => 'Import/Export',
            11 => 'SEO',
            12 => 'Auswertungen'
        ];
        $template->assign('catName', $catNames[$params['cat']] ?? null);
    }

    /**
     * @param array{value?: string|int} $params
     */
    private function formatVersion(array $params): ?string
    {
        if (!isset($params['value'])) {
            return null;
        }

        return \substr_replace((string)(int)$params['value'], '.', 1, 0);
    }

    /**
     * @param array{account: \stdClass} $params
     */
    private function getAvatar(array $params): string
    {
        $url = ($params['account']->attributes['useAvatar']->cAttribValue ?? '') === 'Ux'
            ? $params['account']->attributes['useAvatarUpload']->cAttribValue
            : Shop::getAdminURL() . '/templates/bootstrap/gfx/avatar-default.svg';
        if (!(new Updater($this->db))->hasPendingUpdates()) {
            \executeHook(\HOOK_BACKEND_FUNCTIONS_GRAVATAR, [
                'url'          => &$url,
                'AdminAccount' => $_SESSION['AdminAccount']
            ]);
        }

        return $url;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function captchaMarkup(array $params, Template|\Smarty_Internal_Template $template): string
    {
        $smarty = $template->getSmarty();

        if ($params['getBody'] ?? false) {
            return Shop::Container()->getCaptchaService()->getBodyMarkup($smarty);
        }

        return Shop::Container()->getCaptchaService()->getHeadMarkup($smarty);
    }
}
