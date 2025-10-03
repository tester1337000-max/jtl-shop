<?php

declare(strict_types=1);

namespace JTL;

/**
 * Class XML
 * @package JTL
 */
class XML
{
    private static string $lastParseError = '';

    public \XMLParser $parser;

    /**
     * @var array<mixed>|null
     */
    public ?array $document = null;

    /**
     * @var string|array<mixed>|null
     */
    public string|array|null $parent = null;

    /**
     * @var array<mixed>|null
     */
    public ?array $stack = null;

    public ?string $last_opened_tag = null;

    public ?string $data = null;

    public function __construct(string $encoding)
    {
        $this->parser = \xml_parser_create($encoding);
        \xml_parser_set_option($this->parser, \XML_OPTION_CASE_FOLDING, 0);
        \xml_parser_set_option($this->parser, \XML_OPTION_TARGET_ENCODING, \JTL_CHARSET);
        \xml_set_element_handler($this->parser, $this->open(...), $this->close(...));
        \xml_set_character_data_handler($this->parser, $this->data(...));
        $this->checkError();
    }

    public function destruct(): void
    {
        \xml_parser_free($this->parser);
    }

    /**
     * @return array<mixed>|null
     */
    public static function unserialize(string &$xml, string $encoding = 'UTF-8'): ?array
    {
        $parser = new self($encoding);
        $data   = $parser->parse($xml);
        $parser->checkError();
        $parser->destruct();

        return $data;
    }

    /**
     * @param array<mixed> $data
     */
    public static function serialize(array $data, int $level = 0, ?string $prevKey = null): string
    {
        return (new XMLParser())->serializeXML($data, $level, $prevKey) ?: '';
    }

    /**
     * @param mixed $data
     * @return array<mixed>|null
     */
    public function parse(mixed &$data): ?array
    {
        $this->document = [];
        $this->stack    = [];
        $this->parent   = &$this->document;

        return \xml_parse($this->parser, $data, true) ? $this->document : null;
    }

    /**
     * @param array<mixed> $attributes
     */
    public function open(\XMLParser $parser, string $tag, array $attributes): void
    {
        $this->data            = '';
        $this->last_opened_tag = $tag;

        $attr = $tag . ' attr';
        if (\is_array($this->parent) && \array_key_exists($tag, $this->parent)) {
            if (\is_array($this->parent[$tag]) && \array_key_exists(0, $this->parent[$tag])) {
                $key = $this->countNumericItems($this->parent[$tag]);
            } else {
                if (\array_key_exists($attr, $this->parent)) {
                    $arr = ['0 attr' => &$this->parent[$attr], &$this->parent[$tag]];
                    unset($this->parent[$attr]);
                } else {
                    $arr = [&$this->parent[$tag]];
                }
                $this->parent[$tag] = &$arr;
                $key                = 1;
            }
            $this->parent = &$this->parent[$tag];
        } else {
            $key = $tag;
        }
        if (!\is_array($this->parent)) {
            $this->parent = [];
        }
        if ($attributes) {
            $this->parent[$key . ' attr'] = $attributes;
        }
        $this->parent  = &$this->parent[$key];
        $this->stack[] = &$this->parent;
    }

    public function data(\XMLParser $parser, string $data): void
    {
        if ($this->last_opened_tag !== null) {
            $this->data .= $data;
        }
    }

    public function close(\XMLParser $parser, string $tag): void
    {
        if ($this->last_opened_tag === $tag) {
            $this->parent          = $this->data;
            $this->last_opened_tag = null;
        }
        if ($this->stack !== null) {
            \array_pop($this->stack);
        }
        if ($this->stack) {
            $this->parent = &$this->stack[\count($this->stack) - 1];
        }
    }

    private function countNumericItems(mixed $array): int
    {
        return \is_array($array) ? \count(\array_filter(\array_keys($array), '\is_numeric')) : 0;
    }

    private function checkError(): void
    {
        $errCode              = \xml_get_error_code($this->parser);
        self::$lastParseError = '';
        if ($errCode !== \XML_ERROR_NONE) {
            $lineNumber           = \xml_get_current_line_number($this->parser);
            self::$lastParseError = \xml_error_string($errCode) . ' - on line: ' . $lineNumber;
        }
    }

    public static function getLastParseError(): string
    {
        return self::$lastParseError;
    }
}
