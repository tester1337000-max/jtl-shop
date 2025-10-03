<?php

declare(strict_types=1);

namespace JTL\CSV;

use InvalidArgumentException;
use JTL\Customer\Customer;
use JTL\DB\DbInterface;
use JTL\Helpers\URL;
use JTL\Language\LanguageHelper;
use JTL\Redirect\Helpers\Normalizer;
use JTL\Redirect\Repositories\RedirectRefererRepository;
use JTL\Redirect\Repositories\RedirectRepository;
use JTL\Redirect\Services\RedirectService;
use JTL\Redirect\Type;
use stdClass;
use TypeError;

/**
 * Class Import
 * @package JTL\CSV
 */
class Import
{
    public const TYPE_TRUNCATE_BEFORE = 0;

    public const TYPE_OVERWRITE_EXISTING = 1;

    public const TYPE_INSERT_NEW = 2;

    /**
     * @var string[]
     */
    private array $errors = [];

    private int $importCount = 0;

    private int $errorCount = 0;

    public function __construct(private DbInterface $db)
    {
    }

    /**
     * If the "Import CSV" button was clicked with the id $importerId, try to insert entries from the CSV file uploaded
     * into to the table $target or call a function for each row to be imported. Call this function before you read the
     * data from the table again! Make sure, the CSV contains all important fields to form a valid row in your
     * DB-table!
     * Missing fields in the CSV will be set to the DB-tables default value if your DB is configured so.
     *
     * @param string          $id
     * @param callable|string $target - either target table name or callback function that takes an object to be
     *     imported
     * @param string[]        $fields - array of names of the fields in the order they appear in one data row. If and
     *     only if this array is empty, a header line of field names is expected, otherwise not.
     * @param string|null     $delim - delimiter character or null to guess it from the first row
     * @param int             $importType -
     *      0 = clear table, then import (careful!!! again: this will clear the table denoted by $target)
     *      1 = insert new, overwrite existing
     *      2 = insert only non-existing
     * @return bool
     * @throws TypeError
     * @throws InvalidArgumentException
     */
    public function import(
        string $id,
        callable|string $target,
        array $fields = [],
        ?string $delim = null,
        int $importType = self::TYPE_INSERT_NEW
    ): bool {
        if ($importType !== 0 && $importType !== 1 && $importType !== 2) {
            throw new InvalidArgumentException('$importType must be 0, 1 or 2');
        }
        $csvFilename = $_FILES['csvfile']['tmp_name'] ?? null;
        if ($csvFilename === null) {
            throw new InvalidArgumentException(\__('No input file provided.'));
        }
        $csvMime = $_FILES['csvfile']['type'] ?? null;
        $allowed = [
            'application/vnd.ms-excel',
            'text/csv',
            'application/csv',
            'application/vnd.msexcel'
        ];
        if (!\in_array($csvMime, $allowed, true)) {
            $this->errors[]   = \__('csvImportInvalidMime');
            $this->errorCount = 1;

            return false;
        }
        $delim = $delim ?? self::getCsvDelimiter($csvFilename);
        $fs    = \fopen($_FILES['csvfile']['tmp_name'], 'rb');
        if ($fs === false) {
            $this->errors[]   = \__('somethingHappend');
            $this->errorCount = 1;

            return false;
        }
        $this->errorCount  = 0;
        $this->importCount = 0;
        $importDeleteDone  = false;
        $oldRedirectFormat = false;
        $defLanguage       = LanguageHelper::getDefaultLanguage();
        $rowIndex          = 2;
        if (\count($fields) === 0) {
            /** @noinspection PhpRedundantOptionalArgumentInspection */
            $fields = \fgetcsv($fs, 0, $delim, '"', "\\");
            if ($fields === false) {
                $this->errors[]   = \__('somethingHappend');
                $this->errorCount = 1;

                return false;
            }
        }
        $columns           = [];
        $customerIDPresent = false;
        $customerNoPresent = false;
        $articleNoPresent  = false;
        $destUrlPresent    = false;
        foreach ($fields as &$field) {
            if ($field === 'sourceurl') {
                $field             = 'cFromUrl';
                $oldRedirectFormat = true;
            } elseif ($field === 'destinationurl') {
                $field             = 'cToUrl';
                $oldRedirectFormat = true;
                $destUrlPresent    = true;
            } elseif ($field === 'articlenumber') {
                $field             = 'cArtNr';
                $oldRedirectFormat = true;
                $articleNoPresent  = true;
            } elseif ($field === 'languageiso') {
                $field             = 'cIso';
                $oldRedirectFormat = true;
            } elseif ($field === 'cArtNr') {
                $articleNoPresent = true;
            } elseif ($field === 'kKunde') {
                $customerIDPresent = true;
            } elseif ($field === 'cKundenNr') {
                $customerNoPresent = true;
            }
        }
        unset($field);

        if ($oldRedirectFormat) {
            if ($destUrlPresent === false && $articleNoPresent === false) {
                $this->errors[]   = \__('csvImportNoArtNrOrDestUrl');
                $this->errorCount = 1;

                return false;
            }

            if ($destUrlPresent === true && $articleNoPresent === true) {
                $this->errors[] = \__('csvImportArtNrAndDestUrlError');
            }
        }
        if (\is_string($target)) {
            if ($importType === 0) {
                $this->db->query('TRUNCATE ' . $target);
            }
            $columns = $this->db->getCollection('SHOW COLUMNS FROM ' . $target)->pluck('Field')->toArray();
        }
        $service = new RedirectService(
            new RedirectRepository($this->db),
            new RedirectRefererRepository($this->db),
            new Normalizer()
        );
        /** @noinspection PhpRedundantOptionalArgumentInspection */
        while (($row = \fgetcsv($fs, 0, $delim, '"', "\\")) !== false) {
            $obj = new stdClass();
            foreach ($fields as $i => $field) {
                $obj->$field = $row[$i];
            }
            if ($oldRedirectFormat) {
                $parsed = \parse_url($obj->cFromUrl);
                $from   = $parsed['path'] ?? '';
                if (isset($parsed['query'])) {
                    $from .= '?' . $parsed['query'];
                }
                $obj->cFromUrl = $from;
            }
            if ($articleNoPresent) {
                $this->addProductDataByArtNo($obj, $obj->cIso ?? $defLanguage->getCode());
                if (empty($obj->cToUrl)) {
                    ++$this->errorCount;
                    $this->errors[] = \sprintf(\__('csvImportArtNrNotFound'), $obj->cArtNr);
                    continue;
                }
                unset($obj->cArtNr, $obj->cIso);
            }
            if ($customerNoPresent && !$customerIDPresent) {
                $this->addCustomerDataByCustomerNo($obj);
            } elseif ($customerIDPresent) {
                $this->addCustomerDataByCustomerID($obj);
            }
            if (\is_callable($target)) {
                if ($target($obj, $importDeleteDone, $importType) === false) {
                    ++$this->errorCount;
                } else {
                    ++$this->importCount;
                }
            } else { // is_string($target)
                $table = $target;
                if ($importType === self::TYPE_OVERWRITE_EXISTING) {
                    /** @var string[] $fields */
                    $this->db->delete($target, $fields, $row);
                }
                if (isset($obj->cFromUrl, $obj->cToUrl)) {
                    // is redirect import
                    $dto = $service->createDO(
                        $obj->cFromUrl,
                        $obj->cToUrl,
                        (int)($obj->paramHandling ?? 0),
                        Type::IMPORT
                    );
                    if (!$service->save($dto, false, $importType === self::TYPE_OVERWRITE_EXISTING)) {
                        ++$this->errorCount;
                        $this->errors[] = \sprintf(\__('csvImportSaveError'), $rowIndex);
                    }
                } else {
                    // is other import
                    foreach (\get_object_vars($obj) as $key => $value) {
                        if (!\in_array($key, $columns, true)) {
                            unset($obj->$key);
                        }
                    }
                    if ($this->db->insert($table, $obj) === 0) {
                        ++$this->errorCount;
                        $this->errors[] = \sprintf(\__('csvImportSaveError'), $rowIndex);
                    } else {
                        ++$this->importCount;
                    }
                }
            }
            ++$rowIndex;
        }

        return $this->errorCount === 0;
    }

    protected function addCustomerDataByCustomerID(stdClass $obj): void
    {
        $customerID = $obj->kKunde ?? null;
        if ($customerID === null || $customerID < 1) {
            return;
        }
        $obj->customer = new Customer((int)$obj->kKunde, null, $this->db);
    }

    protected function addCustomerDataByCustomerNo(stdClass $obj): void
    {
        $customerNo = $obj->cKundenNr ?? $obj->kundenNr ?? $obj->customerNo ?? null;
        if ($customerNo === null) {
            return;
        }
        $obj->kKunde = $this->db->getSingleInt(
            'SELECT kKunde
                FROM tkunde
                WHERE cKundenNr = :cn',
            'kKunde',
            ['cn' => $customerNo]
        );
        $this->addCustomerDataByCustomerID($obj);
    }

    protected function addProductDataByArtNo(stdClass $data, string $iso): void
    {
        if (!isset($data->cArtNr)) {
            return;
        }
        $item = $this->db->getSingleObject(
            "SELECT tartikel.kArtikel, tartikel.cName, tseo.cSeo
                FROM tartikel
                LEFT JOIN tsprache
                    ON tsprache.cISO = :iso
                LEFT JOIN tseo
                    ON tseo.kKey = tartikel.kArtikel
                    AND tseo.cKey = 'kArtikel'
                    AND tseo.kSprache = tsprache.kSprache
                WHERE tartikel.cArtNr = :artno
                LIMIT 1",
            ['iso' => \mb_convert_case($iso, \MB_CASE_LOWER), 'artno' => $data->cArtNr]
        );
        if ($item === null) {
            return;
        }
        $data->cToUrl = URL::buildURL($item, \URLART_ARTIKEL);
        if (!isset($data->cSeo)) {
            $data->cSeo = $item->cSeo;
        }
        if (!isset($data->kArtikel)) {
            $data->kArtikel = (int)$item->kArtikel;
        }
        if (!isset($data->cName)) {
            $data->cName = $item->cName;
        }
        if (!isset($data->productName)) {
            $data->productName = $item->cName;
        }
    }

    /**
     * @former getCsvDelimiter()
     * @since 5.2.0
     * @throws InvalidArgumentException
     */
    public static function getCsvDelimiter(string $filename): string
    {
        $file = \fopen($filename, 'rb');
        if ($file === false) {
            throw new InvalidArgumentException('Cannot open file ' . $filename);
        }
        $firstLine = \fgets($file);
        if ($firstLine === false) {
            throw new InvalidArgumentException('Cannot read file ' . $filename);
        }
        foreach ([';', ',', '|', '\t'] as $delim) {
            if (\str_contains($firstLine, $delim)) {
                \fclose($file);

                return $delim;
            }
        }
        \fclose($file);

        return ';';
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @param string[] $errors
     */
    public function setErrors(array $errors): void
    {
        $this->errors = $errors;
    }

    public function getImportCount(): int
    {
        return $this->importCount;
    }

    public function setImportCount(int $importCount): void
    {
        $this->importCount = $importCount;
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    public function setErrorCount(int $errorCount): void
    {
        $this->errorCount = $errorCount;
    }

    public function getDB(): DbInterface
    {
        return $this->db;
    }

    public function setDB(DbInterface $db): void
    {
        $this->db = $db;
    }
}
