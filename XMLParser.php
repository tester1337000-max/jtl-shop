<?php

declare(strict_types=1);

namespace JTL;

use JTL\Helpers\Text;

/**
 * Class XMLParser
 * @package JTL
 */
class XMLParser
{
    /**
     * @param string $fileName
     * @return array<mixed>
     * @throws \RuntimeException
     */
    public function parse(string $fileName): array
    {
        $xml = \file_get_contents($fileName);
        if ($xml === false) {
            throw new \RuntimeException('Could not read file ' . $fileName);
        }

        return $this->getArrangedArray($this->unserializeXML($xml)) ?? [];
    }

    /**
     * takes raw XML as a parameter (a string)
     * and returns an equivalent PHP data structure
     * @param string $xml
     * @param string $encoding
     * @return array<mixed>|null
     */
    public function unserializeXML(string &$xml, string $encoding = 'UTF-8'): ?array
    {
        $parser = new XML($encoding);
        $data   = $parser->parse($xml);
        $parser->destruct();

        return $data;
    }

    /**
     * serializes any PHP data structure into XML
     *
     * @param array<mixed> $data
     * @param int          $level
     * @param string|null  $prevKey
     * @return string|void
     */
    public function serializeXML(array &$data, int $level = 0, ?string $prevKey = null)
    {
        if ($level === 0) {
            \ob_start();
            echo '<?xml version="1.0" ?>', "\n";
        }
        foreach ($data as $key => $value) {
            $key = (string)$key;
            if (!\mb_strpos($key, ' attr')) {
                if (\is_array($value) && \array_key_exists(0, $value)) {
                    $this->serializeXML($value, $level, $key);
                } else {
                    $tag = $prevKey ?: $key;
                    echo \str_repeat("\t", $level), '<', $tag;
                    if (\array_key_exists($key . ' attr', $data)) { // if there's an attribute for this element
                        foreach ($data[$key . ' attr'] as $attr_name => $attr_value) {
                            echo ' ', $attr_name, '="', Text::htmlspecialchars((string)$attr_value), '"';
                        }
                        \reset($data[$key . ' attr']);
                    }

                    if ($value === null) {
                        echo " />\n";
                    } elseif (!\is_array($value)) {
                        echo '>', Text::htmlspecialchars((string)$value), '</' . $tag . ">\n";
                    } else {
                        echo ">\n", $this->serializeXML($value, $level + 1),
                        \str_repeat("\t", $level), '</' . $tag . ">\n";
                    }
                }
            }
        }
        \reset($data);
        if ($level === 0) {
            return \ob_get_clean() ?: '';
        }
    }

    /**
     * @param array<mixed>|mixed $xml
     * @param int                $level
     * @return ($xml is array ? array<mixed> : mixed)
     */
    public function getArrangedArray(mixed $xml, int $level = 1): mixed
    {
        if (!\is_array($xml)) {
            return $xml;
        }
        $keys  = \array_keys($xml);
        $count = \count($xml);
        for ($i = 0; $i < $count; $i++) {
            if (\str_contains((string)$keys[$i], ' attr')) {
                // attribut array -> nicht beachten -> weiter
                continue;
            }
            if ($level === 0 || (int)$keys[$i] > 0 || $keys[$i] === 0 || $keys[$i] === '0') {
                // int Arrayelement -> in die Tiefe gehen
                $xml[$keys[$i]] = $this->getArrangedArray($xml[$keys[$i]]);
            } elseif (isset($xml[$keys[$i]][0])) {
                $xml[$keys[$i]] = $this->getArrangedArray($xml[$keys[$i]]);
            } else {
                if ($xml[$keys[$i]] === '') {
                    continue;
                }
                // kein Attributzweig, kein numerischer Anfang
                $tmp           = [];
                $tmp['0 attr'] = $xml[$keys[$i] . ' attr'] ?? null;
                $tmp['0']      = $xml[$keys[$i]];
                unset($xml[$keys[$i]], $xml[$keys[$i] . ' attr']);
                $xml[$keys[$i]] = $tmp;
                if (\is_array($xml[$keys[$i]]['0'])) {
                    $xml[$keys[$i]]['0'] = $this->getArrangedArray($xml[$keys[$i]]['0']);
                }
            }
        }

        return $xml;
    }
}
