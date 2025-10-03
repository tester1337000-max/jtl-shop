<?php

declare(strict_types=1);

namespace JTL\Cron\Job;

use JTL\Cron\Job;
use JTL\Cron\JobInterface;
use JTL\Cron\QueueEntry;
use JTL\Media\IMedia;
use JTL\Media\Media;

/**
 * Class ImageCache
 * @package JTL\Cron\Job
 */
final class ImageCache extends Job
{
    private int $nextIndex = 0;

    /**
     * @inheritdoc
     */
    public function hydrate(object $data): self
    {
        parent::hydrate($data);
        if (\JOBQUEUE_LIMIT_IMAGE_CACHE_IMAGES > 0) {
            $this->setLimit(\JOBQUEUE_LIMIT_IMAGE_CACHE_IMAGES);
        }

        return $this;
    }

    /**
     * @throws \Exception
     */
    private function generateImageCache(int $index, IMedia $instance): bool
    {
        $rendered = 0;
        $limit    = $this->getLimit();
        $uncached = $instance->getUncachedImageCount();
        $images   = $instance->getImages(true, $index, $limit);
        $totalAll = $instance->getTotalImageCount();
        $this->logger->debug(\sprintf('Uncached %s images: %d/%d', $instance::getType(), $uncached, $totalAll));
        if ($index >= $totalAll) {
            $index  = 0;
            $images = $instance->getImages(true, $index, $limit);
        }
        while (\count($images) === 0 && $index < $totalAll) {
            $index  += $limit;
            $images = $instance->getImages(true, $index, $limit);
        }
        $thisRun = \count($images);
        foreach ($images as $image) {
            $instance->cacheImage($image);
            ++$index;
            ++$rendered;
            $this->logger->debug('generated image {cnt}/{rn}', ['cnt' => $rendered, 'rn' => $thisRun]);
            if ($index % 10 === 0) {
                // this may be a long running loop without any db interaction - so query something from time to time
                $this->db->query('SELECT 1 AS avoidTimeout');
            }
        }
        $this->logger->info(
            'Generated cache for {cnt} {tp} images',
            ['cnt' => $rendered, 'tp' => $instance::getType()]
        );
        $this->nextIndex = $uncached === 0 || ($uncached - $rendered === 0) ? 0 : $index;

        return $this->nextIndex === 0;
    }

    /**
     * @inheritdoc
     */
    public function start(QueueEntry $queueEntry): JobInterface
    {
        parent::start($queueEntry);
        $this->logger->debug('Generating image cache - max. {cnt}', ['cnt' => $this->getLimit()]);
        $res = true;
        foreach (Media::getInstance()->getRegisteredClasses() as $type) {
            $res = $this->generateImageCache($queueEntry->tasksExecuted, $type) && $res;
        }
        $queueEntry->tasksExecuted = $this->nextIndex;
        $this->setFinished($res);

        return $this;
    }
}
