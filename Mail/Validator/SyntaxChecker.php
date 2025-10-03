<?php

declare(strict_types=1);

namespace JTL\Mail\Validator;

use Exception;
use JTL\Backend\AdminIO;
use JTL\Language\LanguageHelper;
use JTL\Language\LanguageModel;
use JTL\Mail\Hydrator\HydratorInterface;
use JTL\Mail\Hydrator\TestHydrator;
use JTL\Mail\Renderer\RendererInterface;
use JTL\Mail\Renderer\SmartyRenderer;
use JTL\Mail\Template\Model;
use JTL\Mail\Template\TemplateFactory;
use JTL\Shop;
use JTL\Shopsetting;
use JTL\Smarty\MailSmarty;
use SmartyException;
use stdClass;

/**
 * Class SyntaxChecker
 * @package JTL\Mail\Validator
 */
final readonly class SyntaxChecker
{
    public function __construct(
        private TemplateFactory $factory,
        private RendererInterface $renderer,
        private HydratorInterface $hydrator
    ) {
    }

    private static function getHTMLState(Model $model): string
    {
        try {
            return Shop::Smarty()->assign('template', $model)->fetch('snippets/mailtemplate_state.tpl');
        } catch (Exception) {
            return '';
        }
    }

    private static function stripMessage(string $out, string $message): string
    {
        $message = \strip_tags($message);
        // strip possible call stack
        if (\preg_match('/(Stack trace|Call Stack):/', $message, $hits)) {
            $callstackPos = \mb_strpos($message, $hits[0]);
            if ($callstackPos !== false) {
                $message = \mb_substr($message, 0, $callstackPos);
            }
        }
        $errText  = '';
        $fatalPos = \mb_strlen($out);
        // strip smarty output if fatal error occurs
        if (\preg_match('/((Recoverable )?Fatal error|Uncaught Error):/ui', $out, $hits)) {
            $fatalPos = \mb_strpos($out, $hits[0]);
            if ($fatalPos !== false) {
                $errText = \mb_substr($out, 0, $fatalPos);
            }
        }
        // strip possible error position from smarty output
        $errText = (string)\preg_replace('/[\t\n]/', ' ', \mb_substr($errText, 0, $fatalPos ?: null));
        $len     = \mb_strlen($errText);
        if ($len > 75) {
            $errText = '...' . \mb_substr($errText, $len - 75);
        }

        return \htmlentities($message) . ($len > 0 ? '<br/>on line: ' . \htmlentities($errText) : '');
    }

    private function doCheck(LanguageModel $lang, Model $model): stdClass
    {
        $res = (object)[
            'state'   => 'ok',
            'message' => '',
        ];

        $id       = $model->getID() . '_' . $lang->getId();
        $moduleID = $model->getModuleID();
        try {
            $this->hydrator->getSmarty()->setErrorReporting(\E_ALL & ~\E_NOTICE & ~\E_STRICT & ~\E_DEPRECATED);
            $this->hydrator->hydrate(null, $lang);
            \executeHook(\HOOK_MAIL_SYNTAXCHECKER_AFTER_HYDRATE, [
                'hydrator' => $this->hydrator,
                'renderer' => $this->renderer,
                'model'    => $model,
            ]);
            $html = $this->renderer->renderHTML($id);
            $text = $this->renderer->renderText($id);
            if (
                !\in_array($moduleID, ['core_jtl_footer', 'core_jtl_header'], true)
                && (\trim($html) === '' || \trim($text) === '')
            ) {
                $model->setHasError(true);
                $res->state   = 'fail';
                $res->message = \__('Empty mail body');
            } elseif (!$model->getHasError()) {
                $model->setHasError(false);
            }
        } catch (Exception $e) {
            $model->setHasError(true);
            $res->state   = 'fail';
            $res->message = \__($e->getMessage());
        } finally {
            $model->save();
        }

        return $res;
    }

    /**
     * @throws Exception
     */
    public static function ioCheckSyntax(int $templateID): stdClass
    {
        \ini_set('html_errors', '0');
        \ini_set('display_errors', '1');
        \ini_set('log_errors', '0');
        \error_reporting(\E_ALL & ~\E_NOTICE & ~\E_DEPRECATED);

        Shop::Container()->getGetText()->loadAdminLocale('pages/emailvorlagen');
        $res = (object)[
            'result' => [],
            'state'  => '<span class="label text-warning">' . \__('untested') . '</span>',
        ];

        $db    = Shop::Container()->getDB();
        $model = new Model($db);
        \register_shutdown_function(static function () use ($model): void {
            $err = \error_get_last();
            if ($err !== null && ($err['type'] & !(\E_NOTICE | \E_DEPRECATED) !== 0)) {
                $out = \ob_get_clean();
                $res = (object)[
                    'result'  => 'fail',
                    'state'   => '<span class="label text-warning">' . \__('untested') . '</span>',
                    'message' => self::stripMessage($out ?: '', $err['message']),
                ];
                $model->setHasError(true);
                $model->save();
                $res->state = self::getHTMLState($model);

                $io = AdminIO::getInstance();
                $io->respondAndExit($res);
            }
        });
        \session_write_close();

        try {
            $renderer = new SmartyRenderer(new MailSmarty($db));
            $hydrator = new TestHydrator($renderer->getSmarty(), $db, Shopsetting::getInstance($db));
            $sc       = new self(new TemplateFactory($db), $renderer, $hydrator);
            $template = $sc->factory->getTemplateByID($templateID);
            \executeHook(
                \HOOK_MAIL_SYNTAX_CHECK,
                [
                    'smarty'     => $renderer->getSmarty(),
                    'templateID' => $templateID,
                    'template'   => $template
                ]
            );
            if ($template === null) {
                $res->result  = 'fail';
                $res->message = \__('errorTemplateMissing');

                return $res;
            }
            $model->load($template->getID());
            $model->setSyntaxCheck(Model::SYNTAX_NOT_CHECKED);
            $model->save();
            foreach (LanguageHelper::getAllLanguages(0, true, true) as $lang) {
                $template->load($lang->getId(), 1);
                $res->result[$lang->getCode()] = $sc->doCheck($lang, $model);
            }
            $res->state = self::getHTMLState($model);
        } catch (SmartyException $e) {
            $res->result  = 'fail';
            $res->message = \__($e->getMessage());
        }

        return $res;
    }
}
