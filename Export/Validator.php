<?php

declare(strict_types=1);

namespace JTL\Export;

use Exception;
use InvalidArgumentException;
use JTL\Backend\AdminIO;
use JTL\Cache\JTLCacheInterface;
use JTL\Cron\QueueEntry;
use JTL\DB\DbInterface;
use JTL\Export\Exporter\ExporterInterface;
use JTL\Export\Exporter\TestExporter;
use JTL\L10n\GetText;
use JTL\Smarty\JTLSmarty;
use Psr\Log\LoggerInterface;
use stdClass;

/**
 * Class Validator
 * @package JTL\Export
 * @since 5.3.0
 */
class Validator
{
    public const SYNTAX_FAIL = 1;

    public const SYNTAX_NOT_CHECKED = -1;

    public const SYNTAX_OK = 0;

    private int $id = 0;

    public int $errorCode = self::SYNTAX_NOT_CHECKED;

    private ExporterInterface $exporter;

    public function __construct(
        private readonly DbInterface $db,
        private readonly JTLCacheInterface $cache,
        private readonly GetText $getText,
        private readonly JTLSmarty $smarty,
        private readonly LoggerInterface $logger
    ) {
        $this->getText->loadAdminLocale('pages/exportformate');
        $this->exporter = new TestExporter(
            $this->db,
            $this->logger,
            $this->cache
        );
    }

    /**
     * @param array<string, string> $post
     * @param Model                 $model
     * @return array<string, int>|Model
     */
    public function check(array $post, Model $model): array|Model
    {
        $validation = [];
        if (empty($post['cName'])) {
            $validation['cName'] = 1;
        } else {
            $model->setName($post['cName']);
        }
        $info      = \pathinfo(\PFAD_ROOT . \PFAD_EXPORT . $post['cDateiname']);
        $whitelist = \array_map('\strtolower', \explode(',', \EXPORTFORMAT_ALLOWED_FORMATS));
        $realpath  = \realpath($info['dirname']);
        if (empty($post['cDateiname'])) {
            $validation['cDateiname'] = 1;
        } elseif (!\str_contains($post['cDateiname'], '.')) { // Dateiendung fehlt
            $validation['cDateiname'] = 2;
        } elseif ($realpath === false || !\str_contains($realpath, \realpath(\PFAD_ROOT) ?: '')) {
            $validation['cDateiname'] = 3;
        } elseif (!\in_array(\mb_convert_case($info['extension'] ?? '', \MB_CASE_LOWER), $whitelist, true)) {
            $validation['cDateiname'] = 4;
        } else {
            $model->setFilename($post['cDateiname']);
        }
        if (!isset($post['nSplitgroesse'])) {
            $post['nSplitgroesse'] = '0';
        }
        if (empty($post['cContent'])) {
            $validation['cContent'] = 1;
        } elseif (
            !\EXPORTFORMAT_ALLOW_PHP
            && (
                \str_contains($post['cContent'], '{php}')
                || \str_contains($post['cContent'], '<?php')
                || \str_contains($post['cContent'], '<%')
                || \str_contains($post['cContent'], '<%=')
                || \str_contains($post['cContent'], '<script language="php">')
            )
        ) {
            $validation['cContent'] = 2;
        } else {
            $model->setContent(\str_replace('<tab>', "\t", $post['cContent']));
        }
        if (!isset($post['kSprache']) || (int)$post['kSprache'] === 0) {
            $validation['kSprache'] = 1;
        } else {
            $model->setLanguageID((int)$post['kSprache']);
        }
        if (!isset($post['kWaehrung']) || (int)$post['kWaehrung'] === 0) {
            $validation['kWaehrung'] = 1;
        } else {
            $model->setCurrencyID((int)$post['kWaehrung']);
        }
        if (!isset($post['kKundengruppe']) || (int)$post['kKundengruppe'] === 0) {
            $validation['kKundengruppe'] = 1;
        } else {
            $model->setCustomerGroupID((int)$post['kKundengruppe']);
        }
        if (\count($validation) > 0) {
            return $validation;
        }
        $model->setUseCache((int)$post['nUseCache']);
        $model->setVarcombOption((int)$post['nVarKombiOption']);
        $model->setSplitSize((int)$post['nSplitgroesse']);
        $model->setIsSpecial(0);
        $model->setEncoding($post['cKodierung'] ?? 'UTF-8');
        $model->setPluginID((int)($post['kPlugin'] ?? 0));
        $model->setId((int)($post['kExportformat'] ?? 0));
        $model->setCampaignID((int)($post['kKampagne'] ?? 0));
        if (isset($post['cFusszeile'])) {
            $model->setFooter(\str_replace('<tab>', "\t", $post['cFusszeile']));
        }
        if (isset($post['cKopfzeile'])) {
            $model->setHeader(\str_replace('<tab>', "\t", $post['cKopfzeile']));
        }

        return $model;
    }

    private function getHTMLState(): string
    {
        try {
            return $this->smarty->assign('exportformat', (object)['nFehlerhaft' => $this->errorCode])
                ->fetch('snippets/exportformat_state.tpl');
        } catch (Exception) {
            return '';
        }
    }

    private function stripMessage(string $out, string $message): string
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

    private function getQueue(): QueueEntry
    {
        $test = (object)[
            'jobQueueID'    => 0,
            'cronID'        => 0,
            'foreignKeyID'  => 0,
            'taskLimit'     => 10,
            'tasksExecuted' => 0,
            'lastProductID' => 0,
            'jobType'       => 'test',
            'foreignKey'    => 'test',
            'tableName'     => 'test',
        ];

        return new QueueEntry($test);
    }

    private function doCheck(): stdClass
    {
        $model = Model::load(['id' => $this->id], $this->db);
        $this->exporter->initialize($this->id, $model, false, false);
        $res = (object)[
            'result'  => 'ok',
            'message' => '',
            'state'   => ''
        ];
        try {
            $this->exporter->start($this->getQueue(), 1);
            $this->updateError(self::SYNTAX_OK);
        } catch (ExportException $e) {
            $this->updateError(self::SYNTAX_FAIL);
            $res->result  = 'fail';
            $res->message = \__($e->getMessage());
        }
        $res->state = $this->getHTMLState();

        return $res;
    }

    public function ioCheckSyntax(int $id): stdClass
    {
        $this->id = $id;
        \ini_set('html_errors', '0');
        \ini_set('display_errors', '1');
        \ini_set('log_errors', '0');
        \error_reporting(\E_ALL & ~\E_NOTICE & ~\E_STRICT & ~\E_DEPRECATED);

        \register_shutdown_function(function (): void {
            $err = \error_get_last();
            if ($err !== null && ($err['type'] & !(\E_NOTICE | \E_STRICT | \E_DEPRECATED) !== 0)) {
                $out = \ob_get_clean() ?: '';
                $res = (object)[
                    'result'  => 'fail',
                    'state'   => '<span class="label text-warning">' . \__('untested') . '</span>',
                    'message' => $this->stripMessage($out, $err['message']),
                ];
                $this->updateError(self::SYNTAX_FAIL);
                $res->state = $this->getHTMLState();
                AdminIO::getInstance()->respondAndExit($res);
            }
        });
        $this->updateError(self::SYNTAX_NOT_CHECKED);

        return $this->doCheck();
    }

    public function preview(int $exportID): stdClass
    {
        $error = null;
        try {
            $model = Model::load(['id' => $exportID], $this->db, Model::ON_NOTEXISTS_FAIL);
        } catch (Exception) {
            throw new InvalidArgumentException('Cannot find export with id ' . $exportID);
        }
        $this->exporter->initialize($exportID, $model, false, false);
        try {
            $this->exporter->start($this->getQueue(), 1);
            /** @var TestWriter $writer */
            $writer = $this->exporter->getWriter();
            $res    = $writer->getData();
        } catch (ExportException $e) {
            $error = $e->getMessage();
            $res   = (object)[
                'header'  => [],
                'content' => [],
                'footer'  => []
            ];
        }

        $res->html = $this->smarty->assign('error', $error)
            ->assign('header', $res->header)
            ->assign('content', $res->content)
            ->assign('footer', $res->footer)
            ->fetch('tpl_inc/exportformate_testresult.tpl');

        return $res;
    }

    public function updateError(int $error): void
    {
        $this->db->update(
            'texportformat',
            'kExportformat',
            $this->id,
            (object)['nFehlerhaft' => $error]
        );
        $this->errorCode = $error;
    }
}
