<?php

declare(strict_types=1);

namespace JTL\ServiceReport\Report;

use JTL\Shop;
use JTL\Shopsetting;

class ShopConfig implements ReportInterface
{
    /**
     * @return array{config: array<string, array<string, mixed>>, log: array<\stdClass>}
     */
    public function getData(): array
    {
        $config = Shopsetting::getInstance()->getAll();
        unset(
            $config['fs']['ftp_pass'],
            $config['fs']['sftp_pass'],
            $config['caching']['caching_redis_pass'],
            $config['emails']['email_smtp_pass'],
            $config['newsletter']['newsletter_smtp_pass'],
        );

        return [
            'config' => $config,
            'log'    => $this->getLog(),
        ];
    }

    /**
     * @return \stdClass[]
     */
    private function getLog(): array
    {
        return Shop::Container()->getDB()->getObjects(
            'SELECT * FROM teinstellungenlog
                ORDER BY dDatum desc
                LIMIT 50'
        );
    }
}
