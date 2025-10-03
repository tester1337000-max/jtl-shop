<?php

declare(strict_types=1);

namespace JTL\Media;

use Generator;
use JTL\DB\DbInterface;
use JTL\Media\Image\StatsItem;
use stdClass;

/**
 * Interface IMedia
 * @package JTL\Media
 */
interface IMedia
{
    /**
     * @param DbInterface|null $db
     */
    public function __construct(?DbInterface $db = null);

    /**
     * @return DbInterface
     */
    public function getDB(): DbInterface;

    /**
     * @param DbInterface $db
     */
    public function setDB(DbInterface $db): void;

    /**
     * @param string $request
     * @return bool
     */
    public static function isValid(string $request): bool;

    /**
     * @param string $request
     */
    public function handle(string $request): void;

    /**
     * @param mixed $mixed
     * @return string
     */
    public static function getCustomName(mixed $mixed): string;

    /**
     * @param string          $type
     * @param int|string|null $id
     * @param mixed           $mixed
     * @param string          $size
     * @param int             $number
     * @param string|null     $source
     * @return string
     */
    public static function getThumb(
        string $type,
        int|string|null $id,
        mixed $mixed,
        string $size,
        int $number = 1,
        ?string $source = null
    ): string;

    /**
     * @param MediaImageRequest $req
     * @return string
     */
    public static function getThumbByRequest(MediaImageRequest $req): string;

    /**
     * @param string          $type
     * @param int|string|null $id
     * @param mixed           $mixed
     * @param string          $size
     * @param int             $number
     * @param string|null     $sourcePath
     * @return MediaImageRequest
     */
    public static function getRequest(
        string $type,
        int|string|null $id,
        mixed $mixed,
        string $size,
        int $number = 1,
        ?string $sourcePath = null
    ): MediaImageRequest;

    /**
     * @param int|string $id
     * @param int|null   $number
     * @return string|null
     */
    public function getPathByID(int|string $id, ?int $number = null): ?string;

    /**
     * @return string
     */
    public static function getStoragePath(): string;

    /**
     * @param bool $filesize
     * @param int  $offsetInit
     * @return StatsItem
     */
    public function getStats(bool $filesize = false, int $offsetInit = 0): StatsItem;

    /**
     * @param MediaImageRequest $request
     * @return stdClass
     */
    public function getCorruptedImage(MediaImageRequest $request): stdClass;

    /**
     * @param int|null $offset
     * @param int|null $limit
     * @return Generator<MediaImageRequest>
     */
    public function getAllImages(?int $offset = null, ?int $limit = null): Generator;

    /**
     * @return int
     */
    public function getTotalImageCount(): int;

    /**
     * @return int
     */
    public function getUncachedImageCount(): int;

    /**
     * @param bool     $notCached
     * @param int|null $offset
     * @param int|null $limit
     * @return MediaImageRequest[]
     */
    public function getImages(bool $notCached = false, ?int $offset = null, ?int $limit = null): array;

    /**
     * @param MediaImageRequest $req
     * @param bool              $overwrite
     * @return array<string, object{success: bool, error: string|null, renderTime: int, cached: bool}&stdClass>
     */
    public function cacheImage(MediaImageRequest $req, bool $overwrite = false): array;

    /**
     * @param int|string[]|int[]|string|null $id
     * @return bool
     */
    public static function clearCache(int|array|string|null $id = null): bool;

    /**
     * @param string $imageUrl
     * @return MediaImageRequest
     */
    public static function toRequest(string $imageUrl): MediaImageRequest;

    /**
     * @param string $path
     * @return bool
     */
    public function imageIsUsed(string $path): bool;

    /**
     * @param MediaImageRequest $req
     * @return string[]
     */
    public function getImageNames(MediaImageRequest $req): array;

    /**
     * @return string
     */
    public static function getType(): string;

    /**
     * @param string $type
     * @param int    $id
     * @return (stdClass&object{stmt: string, bind: array<string, int>})|null
     */
    public static function getImageStmt(string $type, int $id): ?stdClass;
}
