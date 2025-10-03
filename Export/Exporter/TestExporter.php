<?php

declare(strict_types=1);

namespace JTL\Export\Exporter;

use Exception;
use JTL\Cron\QueueEntry;
use JTL\Export\AsyncCallback;
use JTL\Export\ExportException;
use JTL\Export\Product;
use JTL\Export\TestWriter;
use JTL\Export\Validator;

/**
 * Class TestExporter
 * @package JTL\Export\Exporter
 * @since 5.3.0
 */
class TestExporter extends AbstractExporter
{
    public function handleException(Exception $e): bool
    {
        return false;
    }

    public function progress(QueueEntry $queueEntry): void
    {
    }

    public function getFileWriterClass(): string
    {
        return TestWriter::class;
    }

    public function getExportSQL(bool $countOnly = false): string
    {
        return 'SELECT tartikel.kArtikel
                FROM tartikel
                LEFT JOIN tartikelsichtbarkeit ON tartikel.kArtikel = tartikelsichtbarkeit.kArtikel
                    AND tartikelsichtbarkeit.kKundengruppe = ' . (int)$_SESSION['kKundengruppe'] . "
                WHERE tartikel.kVaterArtikel = 0
                    AND (tartikel.cLagerBeachten = 'N' OR tartikel.fLagerbestand > 0)
                    AND tartikelsichtbarkeit.kArtikel IS NULL
                    LIMIT 1";
    }

    /**
     * @throws ExportException
     */
    protected function renderProduct(Product $product): string
    {
        $this->smarty->setErrorReporting(\E_ALL & ~\E_NOTICE & ~\E_STRICT & ~\E_DEPRECATED);
        try {
            return parent::renderProduct($product);
        } catch (Exception $e) {
            throw new ExportException($e->getMessage(), Validator::SYNTAX_FAIL);
        }
    }

    public function finish(AsyncCallback $cb): void
    {
        $this->getWriter()->writeFooter();
        $this->getWriter()->finish();
    }
}
