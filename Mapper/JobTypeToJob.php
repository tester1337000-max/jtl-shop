<?php

declare(strict_types=1);

namespace JTL\Mapper;

use InvalidArgumentException;
use JTL\Cron\Job\Export;
use JTL\Cron\Job\GeneralDataProtect;
use JTL\Cron\Job\GenerateXSelling;
use JTL\Cron\Job\ImageCache;
use JTL\Cron\Job\LicenseCheck;
use JTL\Cron\Job\Newsletter;
use JTL\Cron\Job\RedirectCleanup;
use JTL\Cron\Job\SendMailQueue;
use JTL\Cron\Job\Statusmail;
use JTL\Cron\Job\Store;
use JTL\Cron\Job\TopSeller;
use JTL\Cron\JobInterface;
use JTL\Cron\Type;
use JTL\Events\Dispatcher;
use JTL\Events\Event;

/**
 * Class JobTypeToJob
 * @package JTL\Mapper
 */
class JobTypeToJob
{
    /**
     * @return class-string<JobInterface>
     */
    public function map(string $type): string
    {
        $className = match ($type) {
            Type::IMAGECACHE       => ImageCache::class,
            Type::EXPORT           => Export::class,
            Type::STATUSMAIL       => Statusmail::class,
            Type::NEWSLETTER       => Newsletter::class,
            Type::DATAPROTECTION   => GeneralDataProtect::class,
            Type::STORE            => Store::class,
            Type::LICENSE_CHECK    => LicenseCheck::class,
            Type::TOPSELLER        => TopSeller::class,
            Type::MAILQUEUE        => SendMailQueue::class,
            Type::XSELLING         => GenerateXSelling::class,
            Type::REDIRECT_CLEANUP => RedirectCleanup::class,
            default                => null
        };
        if ($className !== null) {
            return $className;
        }
        $mapping = null;
        Dispatcher::getInstance()->fire(Event::MAP_CRONJOB_TYPE, ['type' => $type, 'mapping' => &$mapping]);
        if ($mapping === null) {
            throw new InvalidArgumentException('Invalid job type: ' . $type);
        }

        return $mapping;
    }
}
