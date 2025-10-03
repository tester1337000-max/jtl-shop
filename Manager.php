<?php

declare(strict_types=1);

namespace JTL\Media;

use DirectoryIterator;
use Exception;
use FilesystemIterator;
use InvalidArgumentException;
use JTL\DB\DbInterface;
use JTL\Helpers\URL;
use JTL\IO\IOError;
use JTL\L10n\GetText;
use JTL\Media\Image\StatsItem;
use JTL\Shop;
use LimitIterator;
use stdClass;

/**
 * Class Manager
 * @package JTL\Media
 */
readonly class Manager
{
    public function __construct(private DbInterface $db, GetText $getText)
    {
        $getText->loadAdminLocale('pages/bilderverwaltung');
    }

    /**
     * @return array<string, object{name: string, type: string}&stdClass>
     * @throws Exception
     */
    public function getItems(): array
    {
        return [
            Image::TYPE_PRODUCT              => (object)[
                'name' => \__('product'),
                'type' => Image::TYPE_PRODUCT
            ],
            Image::TYPE_CATEGORY             => (object)[
                'name' => \__('category'),
                'type' => Image::TYPE_CATEGORY
            ],
            Image::TYPE_MANUFACTURER         => (object)[
                'name' => \__('manufacturer'),
                'type' => Image::TYPE_MANUFACTURER
            ],
            Image::TYPE_CHARACTERISTIC       => (object)[
                'name' => \__('characteristic'),
                'type' => Image::TYPE_CHARACTERISTIC
            ],
            Image::TYPE_CHARACTERISTIC_VALUE => (object)[
                'name' => \__('characteristic value'),
                'type' => Image::TYPE_CHARACTERISTIC_VALUE
            ],
            Image::TYPE_VARIATION            => (object)[
                'name' => \__('variation'),
                'type' => Image::TYPE_VARIATION
            ],
            Image::TYPE_NEWS                 => (object)[
                'name' => \__('news'),
                'type' => Image::TYPE_NEWS
            ],
            Image::TYPE_NEWSCATEGORY         => (object)[
                'name' => \__('newscategory'),
                'type' => Image::TYPE_NEWSCATEGORY
            ],
            Image::TYPE_CONFIGGROUP          => (object)[
                'name' => \__('configgroup'),
                'type' => Image::TYPE_CONFIGGROUP
            ],
            Image::TYPE_OPC                  => (object)[
                'name' => \__('OPC'),
                'type' => Image::TYPE_OPC
            ]
        ];
    }

    public function loadStats(string $type, int $index = 0): StatsItem|IOError
    {
        // attention: this will parallelize async io stats
        \session_write_close();
        // but there should not be any session operations after this point
        try {
            $this->assertTypeExists($type);
            $class = Media::getClass($type);
            /** @var IMedia $instance */
            $instance = new $class($this->db);

            return $instance->getStats(true, $index);
        } catch (Exception $e) {
            return new IOError(
                \sprintf('Error loading stats for type "%s": %s', $type, $e->getMessage()),
                500
            );
        }
    }

    private function assertTypeExists(string $type): void
    {
        $valid = [
            Image::TYPE_PRODUCT,
            Image::TYPE_CATEGORY,
            Image::TYPE_OPC,
            Image::TYPE_CONFIGGROUP,
            Image::TYPE_VARIATION,
            Image::TYPE_MANUFACTURER,
            Image::TYPE_NEWS,
            Image::TYPE_NEWSCATEGORY,
            Image::TYPE_CHARACTERISTIC,
            Image::TYPE_CHARACTERISTIC_VALUE
        ];
        if (!\in_array($type, $valid, true)) {
            throw new InvalidArgumentException(
                \sprintf('Invalid image type "%s". Valid types are: %s', $type, \implode(', ', $valid))
            );
        }
    }

    public function cleanupStorage(string $type, int $index): stdClass
    {
        $startIndex = $index;
        $class      = Media::getClass($type);
        /** @var IMedia $instance */
        $instance  = new $class($this->db);
        $directory = \PFAD_ROOT . $instance::getStoragePath();
        $started   = \time();
        $result    = (object)[
            'total'         => 0,
            'cleanupTime'   => 0,
            'nextIndex'     => 0,
            'deletedImages' => 0,
            'deletes'       => []
        ];
        if ($index === 0) {
            // at the first run, check how many files actually exist in the storage dir
            $storageIterator           = new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS);
            $_SESSION['image_count']   = \iterator_count($storageIterator);
            $_SESSION['deletedImages'] = 0;
            $_SESSION['checkedImages'] = 0;
        }
        $total            = $_SESSION['image_count'];
        $checkedInThisRun = 0;
        $deletedInThisRun = 0;
        $i                = 0;
        /** @var DirectoryIterator $info */
        foreach (new LimitIterator(new DirectoryIterator($directory), $index, \IMAGE_CLEANUP_LIMIT) as $i => $info) {
            $fileName = $info->getFilename();
            if ($info->isDot() || $info->isDir() || \str_starts_with($fileName, '.git')) {
                continue;
            }
            ++$checkedInThisRun;
            if (!$instance->imageIsUsed($fileName)) {
                $result->deletes[] = $fileName;
                \unlink($info->getRealPath());
                ++$_SESSION['deletedImages'];
                ++$deletedInThisRun;
            }
        }
        // increment total number of checked files by the amount checked in this run
        $_SESSION['checkedImages'] += $checkedInThisRun;
        $index                     = $i > 0 ? $i + 1 - $deletedInThisRun : $total;
        // avoid infinite recursion
        if ($index === $startIndex && $deletedInThisRun === 0) {
            $index = $total;
        }
        $result->total             = $total;
        $result->cleanupTime       = \time() - $started;
        $result->nextIndex         = $index;
        $result->checkedFiles      = $checkedInThisRun;
        $result->checkedFilesTotal = $_SESSION['checkedImages'];
        $result->deletedImages     = $_SESSION['deletedImages'];
        if ($index >= $total) {
            // done.
            unset($_SESSION['image_count'], $_SESSION['deletedImages'], $_SESSION['checkedImages']);
        }

        return $result;
    }

    /**
     * @return array{msg: string, ok: bool}|array{}
     */
    public function clearImageCache(string $type, bool $isAjax = false): array
    {
        if (\preg_match('/[a-z]*/', $type)) {
            $instance = Media::getClass($type);
            $res      = $instance::clearCache();
            unset($_SESSION['image_count'], $_SESSION['renderedImages']);
            if ($isAjax === true) {
                return $res === true
                    ? ['msg' => \__('successCacheReset'), 'ok' => true]
                    : ['msg' => \__('errorCacheReset'), 'ok' => false];
            }
            Shop::Smarty()->assign('success', \__('successCacheReset'));
        }

        return [];
    }

    /**
     * @param string|null $type
     * @param int|null    $index
     * @return IOError|stdClass
     * @throws Exception
     */
    public function generateImageCache(?string $type, ?int $index): IOError|stdClass
    {
        if ($type === null || $index === null) {
            return new IOError('Invalid argument request', 500);
        }
        $class = Media::getClass($type);
        /** @var IMedia $instance */
        $instance = new $class($this->db);
        $started  = \time();
        $result   = (object)[
            'total'           => 0,
            'renderTime'      => 0,
            'nextIndex'       => 0,
            'renderedImages'  => 0,
            'lastRenderError' => null,
            'images'          => []
        ];
        if ($index === 0) {
            $_SESSION['image_count']    = $instance->getUncachedImageCount();
            $_SESSION['renderedImages'] = 0;
        }

        $total    = $_SESSION['image_count'];
        $images   = $instance->getImages(true, $index, \IMAGE_PRELOAD_LIMIT);
        $totalAll = $instance->getTotalImageCount();
        while (\count($images) === 0 && $index < $totalAll) {
            $index  += 10;
            $images = $instance->getImages(true, $index, \IMAGE_PRELOAD_TIMEOUT);
        }
        foreach ($images as $image) {
            $seconds = \time() - $started;
            if ($seconds >= 10) {
                break;
            }
            $cachedImage = $instance->cacheImage($image);
            foreach ($cachedImage as $sizeImg) {
                if ($sizeImg->success === false) {
                    $result->lastRenderError = $sizeImg->error;
                }
            }
            $result->images[] = $cachedImage;
            ++$index;
            ++$_SESSION['renderedImages'];
        }
        $result->total          = $total;
        $result->renderTime     = \time() - $started;
        $result->nextIndex      = $index;
        $result->renderedImages = $_SESSION['renderedImages'];
        if ($_SESSION['renderedImages'] >= $total) {
            unset($_SESSION['image_count'], $_SESSION['renderedImages']);
        }

        return $result;
    }

    /**
     * @return array<string, array<string,
     *     object{article: array<object{articleNr: string, articleURLFull: string}&stdClass>,
     *     picture: string}&stdClass>>
     * @throws Exception
     * @deprecated since 5.6.0
     */
    public function getCorruptedImages(string $type, int $limit): array
    {
        \trigger_error(__METHOD__ . ' is deprecated.', \E_USER_DEPRECATED);
        $class = Media::getClass($type);
        /** @var IMedia $instance */
        $instance        = new $class($this->db);
        $corruptedImages = [];
        $totalImages     = $instance->getTotalImageCount();
        $offset          = 0;
        $prefix          = Shop::getURL() . '/';
        do {
            foreach ($instance->getAllImages($offset, \MAX_IMAGES_PER_STEP) as $image) {
                $raw = $image->getRaw();
                if ($raw === null) {
                    continue;
                }
                if (!\file_exists($raw)) {
                    $corruptedImage = (object)[
                        'article' => [],
                        'picture' => ''
                    ];
                    $data           = $this->db->select(
                        'tartikel',
                        'kArtikel',
                        $image->getID()
                    );
                    if ($data === null) {
                        continue;
                    }
                    $data->cURLFull            = URL::buildURL($data, \URLART_ARTIKEL, true, $prefix);
                    $item                      = (object)[
                        'articleNr'      => $data->cArtNr,
                        'articleURLFull' => $data->cURLFull
                    ];
                    $corruptedImage->article[] = $item;
                    $corruptedImage->picture   = $image->getPath();
                    if (\array_key_exists($image->getPath() ?? '', $corruptedImages)) {
                        $corruptedImages[$corruptedImage->picture]->article[] = $item;
                    } else {
                        $corruptedImages[$corruptedImage->picture] = $corruptedImage;
                    }
                }
                if (\count($corruptedImages) >= $limit) {
                    Shop::Container()->getAlertService()->addError(
                        \__('Too many corrupted images'),
                        'too-many-corrupted-images'
                    );
                    break;
                }
            }
            $offset += \MAX_IMAGES_PER_STEP;
        } while (\count($corruptedImages) < $limit && $offset < $totalImages);

        return [$type => \array_slice($corruptedImages, 0, $limit)];
    }
}
