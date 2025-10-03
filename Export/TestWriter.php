<?php

declare(strict_types=1);

namespace JTL\Export;

use JTL\Helpers\Text;
use JTL\Smarty\ExportSmarty;
use stdClass;

/**
 * Class TestWriter
 * @package JTL\Export
 */
class TestWriter implements ExportWriterInterface
{
    private string $header = '';

    private string $footer = '';

    private string $content = '';

    /**
     * @inheritdoc
     */
    public function __construct(
        private readonly Model $model,
        private readonly array $config,
        private readonly ?ExportSmarty $smarty = null
    ) {
    }

    public function start(): void
    {
    }

    /**
     * @inheritdoc
     */
    public function writeHeader(): int
    {
        if ($this->smarty === null) {
            return 0;
        }
        $this->header = $this->smarty->fetch('string:' . $this->model->getHeader());
        if (\mb_strlen($this->header) === 0) {
            return 0;
        }
        $encoding = $this->model->getEncoding();
        if ($encoding === 'UTF-8' || $encoding === 'UTF-8noBOM') {
            $this->header = Text::convertUTF8($this->header);
        }
        $this->header .= $this->getNewLine();

        return \mb_strlen($this->header);
    }

    /**
     * @inheritdoc
     */
    public function writeFooter(): int
    {
        if ($this->smarty === null) {
            return 0;
        }
        $this->footer = $this->smarty->fetch('string:' . $this->model->getFooter());
        if (\mb_strlen($this->footer) === 0) {
            return 0;
        }
        $encoding = $this->model->getEncoding();
        if ($encoding === 'UTF-8' || $encoding === 'UTF-8noBOM') {
            $this->footer = Text::convertUTF8($this->footer);
        }

        return \mb_strlen($this->footer);
    }

    /**
     * @inheritdoc
     */
    public function writeContent(string $data): int
    {
        $utf8 = ($this->model->getEncoding() === 'UTF-8' || $this->model->getEncoding() === 'UTF-8noBOM');

        $this->content = ($utf8 ? Text::convertUTF8($data) : $data);

        return \mb_strlen($this->content);
    }

    /**
     * @inheritdoc
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function finish(): bool
    {
        return true;
    }

    public function getData(): stdClass
    {
        $res          = new stdClass();
        $res->header  = [];
        $res->content = [];
        $res->footer  = [];
        $nl           = $this->getNewLine();
        $separator    = ',';
        if (\str_contains($this->getHeader(), "\t")) {
            $separator = "\t";
        } elseif (\str_contains($this->getHeader(), ';')) {
            $separator = ';';
        }
        $header  = \array_filter(\mb_split($nl, $this->getHeader()) ?: []);
        $content = \array_filter(\mb_split($nl, $this->getContent()) ?: []);
        $footer  = \array_filter(\mb_split($nl, $this->getFooter()) ?: []);
        foreach ($header as $item) {
            $res->header[] = \str_getcsv($item, $separator);
        }
        foreach ($content as $item) {
            $res->content[] = \str_getcsv($item, $separator);
        }
        foreach ($footer as $item) {
            $res->footer[] = \str_getcsv($item, $separator);
        }

        return $res;
    }

    public function deleteOldExports(): void
    {
    }

    public function deleteOldTempFile(): void
    {
    }

    /**
     * @inheritdoc
     */
    public function split(): ExportWriterInterface
    {
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getNewLine(): string
    {
        return ($this->config['exportformate_line_ending'] ?? 'LF') === 'LF' ? "\n" : "\r\n";
    }

    public function getHeader(): string
    {
        return $this->header;
    }

    public function setHeader(string $header): void
    {
        $this->header = $header;
    }

    public function getFooter(): string
    {
        return $this->footer;
    }

    public function setFooter(string $footer): void
    {
        $this->footer = $footer;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }
}
