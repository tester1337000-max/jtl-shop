<?php

declare(strict_types=1);

namespace JTL\CSV;

use Iterator;

/**
 * Class Export
 * @package JTL\CSV
 */
class Export
{
    /**
     * If the "Export CSV" button was clicked with the id $exporterId, offer a CSV download and stop execution of
     * current script. Call this function as soon as you can provide data to be exported but before any page output has
     * been done! Call this function for each CSV exporter on a page with its unique $exporterId!
     *
     * @param string               $id
     * @param string               $fileName
     * @param callable|\stdClass[] $source - array of objects to be exported as CSV or function that gives that
     * array back on demand. The function may also return an Iterator object
     * @param string[]             $fields - array of property/column names to be included or empty array
     * for all columns (taken from first item of $source)
     * @param string[]             $excluded - array of property/column names to be excluded
     * @param string               $delim
     * @param bool                 $head
     * @return void|bool - false = failure or exporter-id-mismatch
     */
    public function export(
        string $id,
        string $fileName,
        callable|array $source,
        array $fields = [],
        array $excluded = [],
        string $delim = ',',
        bool $head = true
    ) {
        if (\is_callable($source)) {
            $arr = $source();
        } else {
            $arr = $source;
        }
        if (\count($fields) === 0) {
            if ($arr instanceof Iterator) {
                /** @var Iterator $arr */
                $first = $arr->current();
            } elseif (!\is_array($arr) || \count($arr) === 0) {
                return false;
            } else {
                $first = $arr[0];
            }
            if ($first === null) {
                return false;
            }
            $assoc  = \get_object_vars($first);
            $fields = \array_keys($assoc);
            $fields = \array_diff($fields, $excluded);
            $fields = \array_filter($fields, '\is_string');
        }
        \header('Content-Disposition: attachment; filename=' . $fileName);
        \header('Content-Type: text/csv');
        $fs = \fopen('php://output', 'wb');
        if ($fs === false) {
            return false;
        }
        if ($head) {
            /** @noinspection PhpRedundantOptionalArgumentInspection */
            \fputcsv($fs, $fields, ',', '"', "\\");
        }
        foreach ($arr as $elem) {
            $csvRow = [];
            foreach ($fields as $field) {
                $csvRow[] = (string)($elem->$field ?? '');
            }
            /** @noinspection PhpRedundantOptionalArgumentInspection */
            \fputcsv($fs, $csvRow, $delim, '"', "\\");
        }
        exit;
    }
}
