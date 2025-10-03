<?php

declare(strict_types=1);

namespace JTL\Model;

use Exception;
use Illuminate\Support\Collection;
use JTL\DB\DbInterface;
use stdClass;

/**
 * Interface DataModelInterface
 * @package JTL\Model
 */
interface DataModelInterface
{
    public const NONE                = 0;
    public const ON_NOTEXISTS_CREATE = 0x001;
    public const ON_NOTEXISTS_NEW    = 0x002;
    public const ON_EXISTS_UPDATE    = 0x004;
    public const ON_NOTEXISTS_FAIL   = 0x008;
    public const ON_INSERT_IGNORE    = 0x0A0;

    public const ERR_NOT_FOUND      = 0x101;
    public const ERR_DUPLICATE      = 0x102;
    public const ERR_INVALID_PARAM  = 0x104;
    public const ERR_NO_PRIMARY_KEY = 0x108;
    public const ERR_DATABASE       = 0x1A0;

    /**
     * fill and load from database or create and store if item does not exist
     *
     * @param array<string, string|int> $attributes
     * @param int                       $option
     * @return static
     * @throws Exception
     */
    public function init(array $attributes, int $option = self::NONE): self;

    /**
     * Create model in database and return created instance
     *
     * @param object|array<mixed> $attributes - the base attributes to create this model as an array or simple object
     * @param DbInterface         $db
     * @param int                 $option - can be NONE or ON_EXISTS_UPDATE
     *      - NONE: throws Exception with ERR_DUPLICATE if model already exists
     *      - ON_EXISTS_UPDATE: update if model already exists
     *
     * @return static
     * @throws Exception - throws Exception with ERR_DUPLICATE if model already exists and ON_EXISTS_UPDATE is not
     *     specified
     *
     */
    public static function create(
        object|array $attributes,
        DbInterface $db,
        int $option = self::NONE
    ): static;

    /**
     * Load model from database and return new instance
     *
     * @param object|array<mixed> $attributes - the base attributes to load this model as an array or simple object
     *      - Should be at least the primary key
     * @param DbInterface         $db
     * @param int                 $option - can be NONE, ON_NOTEXISTS_CREATE, ON_NOTEXISTS_FAIL or ON_NOTEXISTS_NEW
     *      - ON_NOTEXISTS_FAIL: throws exception if model doesn't exist
     *      - ON_NOTEXISTS_CREATE/NONE: creates model in database and returns created instance if model doesn't exist
     *      - ON_NOTEXISTS_NEW: instantiate an empty new model if model doesn't exist
     *
     * @return static
     * @throws Exception - throws Exception with ERR_NOT_FOUND if model doesn't exist and option NONE is specified
     */
    public static function load(
        object|array $attributes,
        DbInterface $db,
        int $option = self::ON_NOTEXISTS_NEW
    ): static;

    /**
     * @param array<mixed> $attributes
     * @throws Exception
     * @see DataModelInterface::load()
     */
    public static function loadByAttributes(
        array $attributes,
        DbInterface $db,
        int $option = self::ON_NOTEXISTS_NEW
    ): static;

    /**
     * @param DbInterface     $db
     * @param string|string[] $key
     * @param mixed           $value
     * @return Collection<int, static>
     * @throws Exception
     */
    public static function loadAll(DbInterface $db, string|array $key, mixed $value): Collection;

    /**
     * Fill the data model with values from attributes and return itself.
     * Simple creation of a model instance without database operation
     *
     * @param object|array<mixed> $attributes - the base attributes to fill this model as an array or simple object
     * @return static
     */
    public function fill(object|array $attributes): self;

    /**
     * Save the model to database and return true if successful - false otherwise
     *
     * @param string[]|null $partial - if specified, save only this partiell attributes
     * @param bool          $updateChildModels
     * @return bool
     */
    public function save(?array $partial = null, bool $updateChildModels = true): bool;

    /**
     * Delete the model from database and return true if successful deleted or no model where found - false otherwise
     *
     * @return bool
     */
    public function delete(): bool;

    /**
     * Reload the model from database and return model itself
     *
     * @return static
     */
    public function reload(): static;

    /**
     * Get the mapped name for given real attribute name
     */
    public function getMapping(string $attribName): string;

    /**
     * Get the value of the primary key of this model
     *
     * @return int
     */
    public function getKey(): int;

    /**
     * Set the value of the primary key of this model and return model itself
     *
     * @param mixed $value - new value for primary key
     * @return static
     */
    public function setKey(mixed $value): static;

    /**
     * Get the name of the primary key of this model
     * @param bool $realName
     * @return string
     * @throws Exception - throws an ERR_NO_PRIMARY_KEY if no primary key exists
     * @see DataModelInterface::getKeyName()
     */
    public function getKeyName(bool $realName = false): string;

    /**
     * Get the names of all keys of this model
     * @param bool $realName
     * @return string[]
     * @throws Exception - throws an ERR_NO_PRIMARY_KEY if no primary key exists
     */
    public function getAllKeyNames(bool $realName = false): array;

    /**
     * Set the name of the primary key of this model
     *
     * @param string $keyName - new name for primary key
     * @throws Exception - throws an ERR_INVALID_PARAM if keyName is not a property of this model
     */
    public function setKeyName(string $keyName): void;

    /**
     * Get the value for an attribute
     *
     * @param string     $attribName - name of the attribute
     * @param null|mixed $default - default value if specified attribute not currently set
     *
     * @return mixed
     * @throws Exception - throws an ERR_INVALID_PARAM if attribName is not a property of this model
     */
    public function getAttribValue(string $attribName, mixed $default = null);

    /**
     * Set the value for an attribute and return model itself
     *
     * @param string $attribName - name of the attribute
     * @param mixed  $value - new value for the attribute
     *
     * @return static
     * @throws Exception - throws an ERR_INVALID_PARAM if attribName is not a property of this model
     */
    public function setAttribValue(string $attribName, mixed $value): static;

    /**
     * Get all attribute definitions of this model as an assoziative array of {@link DataAttribute}(s) (attribute name
     * as key)
     * @return DataAttribute[]
     * @see DataAttribute
     */
    public function getAttributes(): array;

    /**
     * Get model data as json encoded string
     *
     * @param int  $options - bitmask of JSON_ constants, @see json_encode
     * @param bool $iterated
     * @return string|false
     */
    public function rawJSON(int $options = 0, bool $iterated = false): string|false;

    /**
     * Get model data as an assoziative array
     *
     * @param bool $iterated
     * @return array<string, mixed>
     */
    public function rawArray(bool $iterated = false): array;

    /**
     * Get model data as a stdClass instance
     *
     * @param bool $iterated = false
     * @return stdClass
     */
    public function rawObject(bool $iterated = false): stdClass;

    /**
     * @param bool $noPrimary
     * @return stdClass
     */
    public function getSqlObject(bool $noPrimary = false): stdClass;

    /**
     * Clone the model into a new, non-existing instance
     *
     * @param string[]|null $except - property names which will not be transferred
     * @return static
     */
    public function replicate(?array $except = null): static;

    /**
     *
     */
    public function wasLoaded(): void;

    /**
     * @return bool
     */
    public function getWasLoaded(): bool;

    /**
     * @param bool $loaded
     */
    public function setWasLoaded(bool $loaded): void;

    /**
     * Get the name of the corresponding database table
     *
     * @return string
     */
    public function getTableName(): string;

    /**
     * @return DbInterface
     */
    public function getDB(): DbInterface;

    /**
     * @param DbInterface $db
     */
    public function setDB(DbInterface $db): void;
}
