<?php

declare(strict_types=1);

namespace JTL\DB;

/**
 * Class ReturnType
 * @package JTL\DB
 */
abstract class ReturnType
{
    /**
     * return a single instance of \stdClass
     */
    public const SINGLE_OBJECT = 1;

    /**
     * return an array of instances of \stdClass
     */
    public const ARRAY_OF_OBJECTS = 2;

    /**
     * return the amount of affected rows as integer
     */
    public const AFFECTED_ROWS = 3;

    /**
     * return always true
     */
    public const DEFAULT = 4;

    /**
     * return the last inserted id (note: you should only use this, if you insert one row)
     */
    public const LAST_INSERTED_ID = 7;

    /**
     * Returns one result row as an assoc array
     */
    public const SINGLE_ASSOC_ARRAY = 8;

    /**
     * return the result set as an array of assoc arrays
     */
    public const ARRAY_OF_ASSOC_ARRAYS = 9;

    /**
     * Returns the PDOStatement after the query was executed
     */
    public const QUERYSINGLE = 10;

    /**
     * Equivalent to PDO's $stmt->fetchAll(PDO::FETCH_BOTH);
     */
    public const ARRAY_OF_BOTH_ARRAYS = 11;

    /**
     * return a collection object
     */
    public const COLLECTION = 12;

    /**
     * return TRUE if no error
     */
    public const QUERY_OK = 13;
}
