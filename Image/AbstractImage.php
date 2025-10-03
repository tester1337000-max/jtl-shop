<?php

declare(strict_types=1);

namespace JTL\Media\Image;

use Exception;
use Generator;
use JTL\DB\DbInterface;
use JTL\Media\Image;
use JTL\Media\IMedia;
use JTL\Media\MediaImageRequest;
use JTL\Shop;
use stdClass;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

use function Functional\every;
use function Functional\filter;
use function Functional\map;
use function Functional\select;

/**
 * Class AbstractImage
 * @package JTL\Media\Image
 */
abstract class AbstractImage implements IMedia
{
    public const TYPE = '';

    public const REGEX = '';

    public const REGEX_ALLOWED_CHARS = 'a-zA-Z0-9 äööüÄÖÜß\@\$\-\_\.\+\!\*\\\'\(\)\,%';

    /**
     * @var string[]
     */
    protected static array $imageExtensions = ['jpg', 'jpeg', 'webp', 'avif', 'gif', 'png', 'bmp'];

    protected DbInterface $db;

    public function __construct(?DbInterface $db = null)
    {
        $this->db = $db ?? Shop::Container()->getDB();
    }

    public function getDB(): DbInterface
    {
        return $this->db;
    }

    public function setDB(DbInterface $db): void
    {
        $this->db = $db;
    }

    public function handle(string $request): void
    {
        try {
            $request      = '/' . \ltrim($request, '/');
            $request      = \urldecode($request);
            $mediaReq     = $this->create($request);
            $allowedNames = $this->getImageNames($mediaReq);
            if (\count($allowedNames) === 0) {
                throw new Exception('No such image id: ' . $mediaReq->id);
            }

            $imgPath      = null;
            $matchFound   = false;
            $allowedFiles = [];
            foreach ($allowedNames as $allowedName) {
                $mediaReq->path = $allowedName . '.' . $mediaReq->getExt();
                $mediaReq->name = $allowedName;
                $imgPath        = static::getThumbByRequest($mediaReq);
                $allowedFiles[] = $imgPath;
                if ('/' . $imgPath === $request) {
                    $matchFound = true;
                    break;
                }
            }
            if ($matchFound === false) {
                \header('Location: ' . Shop::getImageBaseURL() . $allowedFiles[0], true, 301);
                exit;
            }
            if (!\is_file(\PFAD_ROOT . $imgPath)) {
                Image::render($mediaReq, true);
            }
        } catch (Exception $e) {
            $display = \strtolower(\ini_get('display_errors') ?: 'off');
            if (\in_array($display, ['on', '1', 'true'], true)) {
                echo $e->getMessage();
            }
            \http_response_code(404);
        }
        exit;
    }

    /**
     * @inheritdoc
     */
    public static function getThumb(
        string $type,
        int|string|null $id,
        mixed $mixed,
        string $size,
        int $number = 1,
        ?string $source = null
    ): string {
        $req   = static::getRequest($type, $id, $mixed, $size, $number, $source);
        $thumb = $req->getThumb($size);
        if (!\file_exists(\PFAD_ROOT . $thumb) && (($raw = $req->getRaw()) === null || !\file_exists($raw))) {
            $thumb = \BILD_KEIN_ARTIKELBILD_VORHANDEN;
        }

        return $thumb;
    }

    /**
     * @inheritdoc
     */
    public static function getThumbByRequest(MediaImageRequest $req): string
    {
        $thumb = $req->getThumb($req->getSizeType());
        if (!\file_exists(\PFAD_ROOT . $thumb) && (($raw = $req->getRaw()) === null || !\file_exists($raw))) {
            $thumb = \BILD_KEIN_ARTIKELBILD_VORHANDEN;
        }

        return $thumb;
    }

    /**
     * @inheritdoc
     */
    public static function getRequest(
        string $type,
        int|string|null $id,
        mixed $mixed,
        string $size,
        int $number = 1,
        ?string $sourcePath = null
    ): MediaImageRequest {
        return MediaImageRequest::create([
            'size'       => $size,
            'id'         => $id,
            'type'       => $type,
            'number'     => $number,
            'name'       => static::getCustomName($mixed),
            'ext'        => static::getFileExtension($sourcePath),
            'path'       => $sourcePath,
            'sourcePath' => $sourcePath
        ]);
    }

    /**
     * @inheritdoc
     */
    public static function getCustomName(mixed $mixed): string
    {
        return 'image';
    }

    /**
     * @inheritdoc
     */
    public static function isValid(string $request): bool
    {
        return self::parse($request) !== null;
    }

    /**
     * @inheritdoc
     */
    public static function getImageStmt(string $type, int $id): ?stdClass
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getImageNames(MediaImageRequest $req): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getPathByID(int|string $id, ?int $number = null): ?string
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public static function getStoragePath(): string
    {
        return \PFAD_MEDIA_IMAGE_STORAGE;
    }

    /**
     * @inheritdoc
     */
    public function getStats(bool $filesize = false, int $offset = 0): StatsItem
    {
        $result = new StatsItem();
        $result->setTotalCount($this->getTotalImageCount());
        foreach ($this->getAllImages($offset, \MAX_IMAGES_PER_STEP) as $image) {
            if ($image === null) {
                continue;
            }
            $raw = $image->getRaw();
            $result->addItem();
            if ($raw === null || !\file_exists($raw)) {
                if ($result->addCorrupted() < \MAX_CORRUPTED_IMAGES) {
                    $result->addCorruptedImageItem($this->getCorruptedImage($image));
                }
                continue;
            }
            foreach (Image::getAllSizes() as $size) {
                $thumb = $image->getThumb($size, true);
                if (!\file_exists($thumb)) {
                    continue;
                }
                $result->addGeneratedItem($size);
                if ($filesize !== true) {
                    continue;
                }
                $result->addGeneratedSizeItem($size, \filesize($thumb) ?: 0);
            }
        }
        $nextIndex = $offset + \MAX_IMAGES_PER_STEP;
        $result->setNextIndex($nextIndex);
        if ($nextIndex >= $result->getTotalCount()) {
            $result->setFinished(true);
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getCorruptedImage(MediaImageRequest $request): stdClass
    {
        return new stdClass();
    }

    protected static function getLimitStatement(?int $offset = null, ?int $limit = null): string
    {
        if ($limit === null) {
            return '';
        }
        $limitStmt = ' LIMIT ';
        if ($offset !== null) {
            $limitStmt .= $offset . ', ';
        }
        $limitStmt .= $limit;

        return $limitStmt;
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function getImages(bool $notCached = false, ?int $offset = null, ?int $limit = null): array
    {
        $requests = [];
        foreach ($this->getAllImages($offset, $limit) as $req) {
            if ($notCached && $this->isCached($req)) {
                continue;
            }
            $requests[] = $req;
        }

        return $requests;
    }

    /**
     * @inheritdoc
     */
    public function getAllImages(?int $offset = null, ?int $limit = null): Generator
    {
        yield from [];
    }

    /**
     * @inheritdoc
     */
    public function getUncachedImageCount(): int
    {
        return \count(
            select(
                $this->getAllImages(),
                function (MediaImageRequest $e): bool {
                    return !$this->isCached($e) && ($file = $e->getRaw()) !== null && \file_exists($file);
                }
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function cacheImage(MediaImageRequest $req, bool $overwrite = false): array
    {
        $result     = [];
        $rawPath    = $req->getRaw();
        $extensions = [$req->getExt() === 'auto' ? 'jpg' : $req->getExt()];
        if (Image::hasWebPSupport()) {
            $extensions[] = 'webp';
        }
        if (Image::hasAVIFSupport()) {
            $extensions[] = 'avif';
        }
        if ($overwrite === true) {
            static::clearCache($req->getID());
        }
        foreach ($extensions as $extension) {
            $req->setExt($extension);
            foreach (Image::getAllSizes() as $size) {
                $res = (object)[
                    'success'    => true,
                    'error'      => null,
                    'renderTime' => 0,
                    'cached'     => false,
                ];
                try {
                    $req->setSizeType($size);
                    $thumbPath   = $req->getThumb(null, true);
                    $res->cached = \is_file($thumbPath);
                    if ($res->cached === false) {
                        $renderStart = \microtime(true);
                        if ($rawPath !== null && !\is_file($rawPath)) {
                            throw new Exception(\sprintf('Image source "%s" does not exist', $rawPath));
                        }
                        Image::render($req);
                        $res->renderTime = (\microtime(true) - $renderStart) * 1000;
                    }
                } catch (Exception $e) {
                    $res->success = false;
                    $res->error   = $e->getMessage();
                }
                $result[$size] = $res;
            }
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public static function clearCache(int|array|string|null $id = null): bool
    {
        $baseDir     = \realpath(\PFAD_ROOT . MediaImageRequest::getCachePath(static::getType()));
        $ids         = \is_array($id) ? $id : [$id];
        $directories = filter(
            map($ids, fn($e) => $e === null ? $baseDir : \realpath($baseDir . '/' . $e)),
            fn($e): bool => $e !== false && \str_starts_with($e, $baseDir ?: '???')
        );
        if (\count($directories) === 0) {
            return false;
        }
        try {
            $res    = true;
            $logger = Shop::Container()->getLogService();
            $finder = new Finder();
            $finder->ignoreUnreadableDirs()->in($directories);
            /** @var SplFileInfo $file */
            foreach ($finder->files() as $file) {
                $real = $file->getRealPath();
                $loop = $real !== false && \unlink($real);
                $res  = $res && $loop;
                if ($real === false) {
                    $logger->warning('Cannot delete file {file} - invalid realpath?', ['file' => $file->getPathname()]);
                }
            }
            /** @var SplFileInfo $directory */
            foreach (\array_reverse(\iterator_to_array($finder->directories())) as $directory) {
                $real = $directory->getRealPath();
                $loop = $real !== false && \rmdir($real);
                $res  = $res && $loop;
                if ($real === false) {
                    $logger->warning(
                        'Cannot delete directory {dir} - invalid realpath?',
                        ['dir' => $directory->getPathname()]
                    );
                }
            }
            foreach ($directories as $dir) {
                if ($dir !== $baseDir && \is_dir($dir)) {
                    $loop = \rmdir($dir);
                    $res  = $res && $loop;
                }
            }
        } catch (Exception) {
            $res = false;
        }

        return $res;
    }

    /**
     * @inheritdoc
     */
    public function imageIsUsed(string $path): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getTotalImageCount(): int
    {
        return 0;
    }

    protected function isCached(MediaImageRequest $req): bool
    {
        return every(Image::getAllSizes(), fn($e): bool => \file_exists($req->getThumb($e, true)));
    }

    protected static function getFileExtension(?string $filePath = null): string
    {
        $config = Image::getSettings()['format'];

        return \in_array($config, ['auto', 'auto_webp', 'auto_avif'], true) && $filePath !== null
            ? \pathinfo($filePath)['extension'] ?? 'jpg'
            : $config;
    }

    /**
     * @param string|null $request
     * @return array<mixed>|null
     */
    protected static function parse(?string $request): ?array
    {
        if (!\is_string($request) || \mb_strlen($request) === 0) {
            return null;
        }
        if (\str_starts_with($request, '/')) {
            $request = \mb_substr($request, 1);
        }

        $result = \preg_match(static::REGEX, $request, $matches)
            ? \array_intersect_key($matches, \array_flip(\array_filter(\array_keys($matches), '\is_string')))
            : null;

        if ($result !== null) {
            $result['id']     = (int)($result['id'] ?? 0);
            $result['number'] = (int)($result['numer'] ?? 1);
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public static function toRequest(string $imageUrl): MediaImageRequest
    {
        return (new static())->create($imageUrl);
    }

    protected function create(?string $request): MediaImageRequest
    {
        return MediaImageRequest::create(self::parse($request));
    }

    /**
     * @inheritdoc
     */
    public static function getType(): string
    {
        return static::TYPE;
    }
}
