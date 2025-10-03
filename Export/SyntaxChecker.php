<?php

/** @noinspection PhpDeprecationInspection */

declare(strict_types=1);

namespace JTL\Export;

use Exception;
use InvalidArgumentException;
use JTL\Backend\AdminIO;
use JTL\Cron\QueueEntry;
use JTL\DB\DbInterface;
use JTL\Session\Frontend;
use JTL\Shop;
use JTL\Smarty\ExportSmarty;
use stdClass;

/**
 * Class SyntaxChecker
 * @package JTL\Export
 * @deprecated since 5.3.0
 */
class SyntaxChecker
{
    public const SYNTAX_FAIL        = 1;
    public const SYNTAX_NOT_CHECKED = -1;
    public const SYNTAX_OK          = 0;

    private ExportSmarty $smarty;

    public int $errorCode = self::SYNTAX_NOT_CHECKED;

    public function __construct(private readonly int $id, private readonly DbInterface $db)
    {
    }

    /**
     * @param array<mixed> $post
     * @param Model        $model
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
            $post['nSplitgroesse'] = 0;
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
        $model->setEncoding($post['cKodierung']);
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

    private static function getHTMLState(int $error): string
    {
        try {
            return Shop::Smarty()->assign('exportformat', (object)['nFehlerhaft' => $error])
                ->fetch('snippets/exportformat_state.tpl');
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

    private function initSmarty(): void
    {
        $this->smarty = new ExportSmarty($this->db);
        $this->smarty->assign('URL_SHOP', Shop::getURL())
            ->assign('Waehrung', Frontend::getCurrency())
            ->assign('Einstellungen', []);
    }

    private function doCheck(): stdClass
    {
        $res      = (object)[
            'result'  => 'ok',
            'message' => '',
        ];
        $session  = new Session();
        $model    = Model::load(['id' => $this->id], $this->db);
        $confData = $this->db->selectAll(
            'texportformateinstellungen',
            'kExportformat',
            $this->id
        );
        $config   = [];
        foreach ($confData as $conf) {
            $config[$conf->cName] = $conf->cWert;
        }
        $session->initSession($model, $this->db, $config);
        $this->initSmarty();
        $product   = null;
        $productID = $this->db->getSingleInt(
            "SELECT tartikel.kArtikel
                FROM tartikel
                LEFT JOIN tartikelsichtbarkeit ON tartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                    AND tartikelsichtbarkeit.kKundengruppe = :groupID
                WHERE tartikel.kVaterArtikel = 0
                    AND (tartikel.cLagerBeachten = 'N' OR tartikel.fLagerbestand > 0)
                    AND tartikelsichtbarkeit.kArtikel IS NULL
                    LIMIT 1",
            'kArtikel',
            ['groupID' => $_SESSION['Kundengruppe']->getID()]
        );
        if ($productID > 0) {
            $product = new Product($this->db, $_SESSION['Kundengruppe'], $_SESSION['Waehrung']);
            $product->fuelleArtikel($productID, Product::getExportOptions());
            $product->kSprache                 = $model->getLanguageID();
            $product->kKundengruppe            = $model->getCustomerGroupID();
            $product->kWaehrung                = $model->getCurrencyID();
            $product->campaignValue            = $model->getCampaignValue();
            $product->currencyConversionFactor = $_SESSION['Waehrung']->getConversionFactor();
            $product->cDeeplink                = '';
            $product->Artikelbild              = '';
            $product->augmentProduct($config, $model);
            $product->addCategoryData();
        }
        try {
            $this->smarty->setErrorReporting(\E_ALL & ~\E_NOTICE & ~\E_STRICT & ~\E_DEPRECATED);
            $this->smarty->assign('Artikel', $product)
                ->fetch('db:' . $this->id);
            $this->updateError(self::SYNTAX_OK);
        } catch (Exception $e) {
            $this->updateError(self::SYNTAX_FAIL);
            $res->result  = 'fail';
            $res->message = \__($e->getMessage());
        }

        return $res;
    }

    public static function ioCheckSyntax(int $id): stdClass
    {
        \ini_set('html_errors', '0');
        \ini_set('display_errors', '1');
        \ini_set('log_errors', '0');
        \error_reporting(\E_ALL & ~\E_NOTICE & ~\E_STRICT & ~\E_DEPRECATED);

        Shop::Container()->getGetText()->loadAdminLocale('pages/exportformate');
        \register_shutdown_function(static function () use ($id): void {
            $err = \error_get_last();
            if ($err !== null && ($err['type'] & !(\E_NOTICE | \E_STRICT | \E_DEPRECATED) !== 0)) {
                $out = \ob_get_clean();
                $res = (object)[
                    'result'  => 'fail',
                    'state'   => '<span class="label text-warning">' . \__('untested') . '</span>',
                    'message' => self::stripMessage($out ?: '', $err['message']),
                ];
                $ef  = new self($id, Shop::Container()->getDB());
                $ef->updateError(self::SYNTAX_FAIL);
                $res->state = self::getHTMLState(self::SYNTAX_FAIL);
                AdminIO::getInstance()->respondAndExit($res);
            }
        });

        $ef = new self($id, Shop::Container()->getDB());
        $ef->updateError(self::SYNTAX_NOT_CHECKED);

        try {
            $res = $ef->doCheck();
        } catch (Exception $e) {
            $res = (object)[
                'result'  => 'fail',
                'message' => \__($e->getMessage()),
            ];
        }
        $res->state = self::getHTMLState($ef->errorCode);

        return $res;
    }

    public static function testExport(int $exportID): stdClass
    {
        $db = Shop::Container()->getDB();
        try {
            $model = Model::load(['id' => $exportID], $db, Model::ON_NOTEXISTS_FAIL);
        } catch (Exception) {
            throw new InvalidArgumentException('Cannot find export with id ' . $exportID);
        }
        $smarty  = new ExportSmarty($db);
        $factory = new ExporterFactory($db, Shop::Container()->getLogService(), Shop::Container()->getCache());
        $ef      = $factory->getExporter($exportID);
        $writer  = new TestWriter($model, $ef->getConfig(), $smarty);
        $test    = (object)[
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
        $ef->setWriter($writer);
        $ef->startExport(
            $exportID,
            new QueueEntry($test),
            false,
            false,
            true,
            10
        );
        $nl        = $writer->getNewLine();
        $separator = ',';
        if (\str_contains($writer->getHeader(), "\t")) {
            $separator = "\t";
        } elseif (\str_contains($writer->getHeader(), ';')) {
            $separator = ';';
        }
        $header  = \array_filter(\mb_split($nl, $writer->getHeader()) ?: []);
        $content = \array_filter(\mb_split($nl, $writer->getContent()) ?: []);
        $footer  = \array_filter(\mb_split($nl, $writer->getFooter()) ?: []);

        $res          = new stdClass();
        $res->header  = [];
        $res->content = [];
        $res->footer  = [];

        foreach ($header as $item) {
            $res->header[] = \str_getcsv($item, $separator);
        }
        foreach ($content as $item) {
            $res->content[] = \str_getcsv($item, $separator);
        }
        foreach ($footer as $item) {
            $res->footer[] = \str_getcsv($item, $separator);
        }
        $res->html = Shop::Smarty()
            ->assign('header', $res->header)
            ->assign('content', $res->content)
            ->assign('footer', $res->footer)
            ->fetch('tpl_inc/exportformate_testresult.tpl');

        return $res;
    }

    public function updateError(int $error): void
    {
        if (Shop::getShopDatabaseVersion()->getMajor() < 5) {
            return;
        }
        if (
            $this->db->update(
                'texportformat',
                'kExportformat',
                $this->id,
                (object)['nFehlerhaft' => $error]
            ) !== -1
        ) {
            $this->errorCode = $error;
        }
    }
}
